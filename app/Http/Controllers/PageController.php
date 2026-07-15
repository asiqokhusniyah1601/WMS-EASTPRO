<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Models\Technician;
use App\Models\Accessory;
use App\Models\Device;
use App\Models\DeviceTransaction;
use App\Models\DeliveryOrder;
use App\Models\GsmSimcard;
use App\Models\SimcardTransaction;
use App\Models\DeviceModel;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DeviceInspection;
use App\Models\AccessoryTransaction;
use App\Models\CustomerDevice;
use App\Models\WarehouseAccessory;
use App\Models\HolderAccessory;
use App\Models\StockOpnameSession;
use App\Models\StockOpnameItem;
use App\Models\WarehouseLocation;

use App\Services\ReportService;
use App\Services\WarehouseSessionService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PageController extends Controller
{
    // ==========================================
    // HELPER METHODS (DRY - Don't Repeat Yourself)
    // ==========================================

    /**
     * Create a device transaction log entry.
     */
    private function logDeviceTransaction(Device $device, string $action, string $from, string $to, string $operator = 'Warehouse Operator', string $scannedBy = 'Scanner-HID-01', ?string $notes = null): void
    {
        DeviceTransaction::create([
            'device_id'  => $device->id,
            'device_sn'  => $device->serial_number,
            'action'     => $action,
            'from_location' => $from,
            'to_location'   => $to,
            'operator'   => $operator,
            'scanned_by' => $scannedBy,
            'via_web'    => true,
            'notes'      => $notes,
        ]);
    }

    /**
     * Create an accessory transaction log entry.
     */
    private function logAccessoryTransaction(string $accCode, int $qty, string $action, ?string $from, ?string $to, ?string $technicianCode = null, ?string $notes = null): void
    {
        AccessoryTransaction::create([
            'accessory_code'  => $accCode,
            'qty'             => $qty,
            'action'          => $action,
            'from_location'   => $from,
            'to_location'     => $to,
            'technician_code' => $technicianCode,
            'notes'           => $notes,
        ]);
    }

    /**
     * Catat pergerakan kartu SIM (audit + sumber data monitoring real-time).
     */
    private function logSimcardTransaction(GsmSimcard $sim, string $action, ?string $from, ?string $to, ?string $warehouseCode = null, ?string $notes = null): void
    {
        SimcardTransaction::create([
            'gsm_simcard_id' => $sim->id,
            'msisdn'         => $sim->msisdn,
            'action'         => $action,
            'from_location'  => $from,
            'to_location'    => $to,
            'warehouse_code' => $warehouseCode,
            'operator'       => optional(auth()->user())->name ?? 'System',
            'notes'          => $notes,
        ]);
    }

    /**
     * Broadcast pembaruan stok ke dashboard secara resilien.
     * Kegagalan broadcast (mis. Reverb mati / payload terlalu besar) tidak boleh
     * menggagalkan operasi gudang inti seperti issue, receiving, transfer, dsb.
     */
    private function dispatchStockUpdate(): void
    {
        try {
            event(new \App\Events\GlobalStockUpdated());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast GlobalStockUpdated gagal: ' . $e->getMessage());
        }
    }

    /**
     * Adjust per-warehouse accessory stock (increment or decrement).
     * Uses upsert: if the row doesn't exist, creates it.
     */
    private function adjustWarehouseAccessoryStock(string $warehouseCode, string $accCode, int $qty, string $direction = 'increment'): void
    {
        $record = WarehouseAccessory::firstOrCreate(
            ['warehouse_code' => $warehouseCode, 'accessory_code' => $accCode],
            ['qty' => 0]
        );

        if ($direction === 'increment') {
            $record->increment('qty', $qty);
        } else {
            $record->decrement('qty', min($record->qty, $qty));
        }

        // Stok per-gudang adalah sumber kebenaran tunggal. Stok global selalu
        // direkonsiliasi dari penjumlahan seluruh gudang agar tidak pernah drift.
        $this->syncAccessoryGlobalQty($accCode);
    }

    /**
     * Rekonsiliasi qty global aksesoris = total qty di seluruh gudang.
     * Dipanggil setiap kali stok per-gudang berubah sehingga angka global
     * (yang ditampilkan di master data) tidak pernah menyimpang.
     */
    private function syncAccessoryGlobalQty(string $accCode): void
    {
        $total = (int) WarehouseAccessory::where('accessory_code', $accCode)->sum('qty');
        Accessory::where('code', $accCode)->update(['qty' => $total]);
    }

    /**
     * Adjust saldo aksesoris yang dipegang holder non-gudang (teknisi/customer).
     * Saldo nyata ini menggantikan perhitungan "inferred" pada laporan.
     */
    private function adjustHolderAccessoryStock(string $holderType, string $holderCode, ?string $holderName, string $accCode, int $qty, string $direction = 'increment'): void
    {
        $record = HolderAccessory::firstOrCreate(
            ['holder_type' => $holderType, 'holder_code' => $holderCode, 'accessory_code' => $accCode],
            ['qty' => 0, 'holder_name' => $holderName]
        );

        if ($direction === 'increment') {
            $record->increment('qty', $qty);
        } else {
            $record->decrement('qty', min($record->qty, $qty));
        }

        // Jaga agar nama holder tetap mutakhir untuk tampilan laporan.
        if ($holderName && $record->holder_name !== $holderName) {
            $record->update(['holder_name' => $holderName]);
        }
    }

    /**
     * Build AI Suggestion data for accessories based on transaction frequency.
     *
     * @param string $actionFilter  The transaction action to filter by (e.g. 'RECEIVING', 'OUT', 'RETURN').
     * @param int    $limit         Max number of suggestions.
     * @return array
     */
    private function getAccessorySuggestions(string $actionFilter, int $limit = 5): array
    {
        $suggestions = DB::table('accessory_transactions')
            ->join('accessories', 'accessory_transactions.accessory_code', '=', 'accessories.code')
            ->select('accessories.code', 'accessories.name', DB::raw('COUNT(*) as total'))
            ->where('accessory_transactions.action', '=', $actionFilter)
            ->groupBy('accessories.code', 'accessories.name')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

        if (empty($suggestions)) {
            $suggestions = Accessory::limit($limit)->get()->toArray();
        }

        return $suggestions;
    }

    /**
     * Build AI Suggestion data for devices based on stock frequency.
     */
    private function getDeviceSuggestions(int $limit = 5): array
    {
        $suggestions = DB::table('devices')
            ->join('device_models', function ($join) {
                $join->on('devices.type', '=', 'device_models.type')
                     ->on('devices.model', '=', 'device_models.model');
            })
            ->select('device_models.brand', 'device_models.type', 'device_models.model', DB::raw('COUNT(*) as total'))
            ->groupBy('device_models.brand', 'device_models.type', 'device_models.model')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

        if (empty($suggestions)) {
            $suggestions = DB::table('device_models')
                ->select('brand', 'type', 'model')
                ->limit($limit)
                ->get()
                ->map(fn($item) => (array)$item)
                ->toArray();
        }

        return $suggestions;
    }

    /**
     * Build AI Suggestion for transfer routes (most frequent warehouse pairs).
     */
    private function getTransferRouteSuggestions(int $limit = 3): array
    {
        return DB::table('delivery_orders')
            ->join('warehouses as wf', 'delivery_orders.from_warehouse_code', '=', 'wf.code')
            ->join('warehouses as wt', 'delivery_orders.to_warehouse_code', '=', 'wt.code')
            ->select(
                'delivery_orders.from_warehouse_code',
                'wf.name as from_name',
                'delivery_orders.to_warehouse_code',
                'wt.name as to_name',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('delivery_orders.from_warehouse_code', 'wf.name', 'delivery_orders.to_warehouse_code', 'wt.name')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();
    }

    /**
     * Validasi server-side: pastikan qty aksesoris yang dikeluarkan tidak melebihi
     * stok gudang sumber. Mengembalikan pesan error (string) bila gagal, atau null bila valid.
     */
    private function validateAccessoryStock(Request $request, ?string $warehouseCode): ?string
    {
        if (!$request->has('acc_types')) {
            return null;
        }

        $isTechUser = auth()->check() && auth()->user()->hasRole('technician');

        // Jika bukan teknisi tapi gudang tidak dipilih (seharusnya sudah dicegat validasi form)
        if (!$isTechUser && !$warehouseCode) {
            return null;
        }

        foreach ($request->acc_types as $idx => $accCode) {
            $qty = intval($request->acc_qtys[$idx] ?? 0);
            if ($qty <= 0) continue;

            if ($isTechUser) {
                $techRecord = \App\Models\Technician::where('name', auth()->user()->name)->first();
                $techCode   = $techRecord?->code;
                $techName   = auth()->user()->name;

                $stock = (int) (\App\Models\HolderAccessory::where('holder_type', 'TECHNICIAN')
                    ->where(function($q) use ($techCode, $techName) {
                        if ($techCode) {
                            $q->where('holder_code', $techCode);
                        } else {
                            $q->where('holder_name', $techName);
                        }
                    })
                    ->where('accessory_code', $accCode)
                    ->value('qty') ?? 0);
                
                $sourceName = "teknisi";
            } else {
                $stock = (int) (\App\Models\WarehouseAccessory::where('warehouse_code', $warehouseCode)
                    ->where('accessory_code', $accCode)
                    ->value('qty') ?? 0);
                
                $sourceName = "gudang asal";
            }

            if ($qty > $stock) {
                $name = \App\Models\Accessory::where('code', $accCode)->value('name') ?? $accCode;
                return "Qty aksesoris \"{$name}\" ({$qty}) melebihi stok {$sourceName} ({$stock}).";
            }
        }

        return null;
    }

    /**
     * Process accessory qty arrays from a form and execute stock + log operations.
     * Used by Issue (OUT), Return (RETURN), Receiving (RECEIVING).
     */
    private function processAccessoryQtyForm(Request $request, string $action, ?string $warehouseCode, string $from, string $to, ?string $technicianCode = null, ?string $notes = null, ?string $holderType = null, ?string $holderCode = null, ?string $holderName = null): void
    {
        if (!$request->has('acc_types')) {
            return;
        }

        foreach ($request->acc_types as $idx => $accCode) {
            $qty = intval($request->acc_qtys[$idx] ?? 0);
            if ($qty <= 0) continue;

            $acc = Accessory::find($accCode);
            if (!$acc) {
                $accName = $request->acc_names[$idx] ?? $accCode;
                $accUnit = $request->acc_units[$idx] ?? 'pcs';
                $acc = Accessory::create([
                    'code' => $accCode,
                    'name' => $accName,
                    'qty' => 0,
                    'unit' => $accUnit
                ]);
            }

            // Stok per-gudang adalah sumber kebenaran; qty global otomatis
            // tersinkron di dalam adjustWarehouseAccessoryStock().
            if ($warehouseCode) {
                $direction = in_array($action, ['RECEIVING', 'RETURN']) ? 'increment' : 'decrement';
                $this->adjustWarehouseAccessoryStock($warehouseCode, $accCode, $qty, $direction);
            }

            // Save Serial Numbers if provided and it is a RECEIVING action
            if ($action === 'RECEIVING' && $request->has('acc_sns')) {
                $sns = $request->input("acc_sns.{$accCode}", []);
                if (is_array($sns)) {
                    foreach ($sns as $sn) {
                        $sn = trim((string)$sn);
                        if ($sn === '') continue;
                        \App\Models\AccessorySerialNumber::updateOrCreate(
                            ['accessory_code' => $accCode, 'serial_number' => $sn],
                            [
                                'warehouse_code' => $warehouseCode,
                                'status' => 'IN_STOCK',
                                'notes' => 'Received from DO'
                            ]
                        );
                    }
                }
            }

            // Adjust saldo holder (teknisi/customer): OUT menambah, RETURN mengurangi.
            if ($holderType && $holderCode) {
                if ($action === 'OUT') {
                    $this->adjustHolderAccessoryStock($holderType, $holderCode, $holderName, $accCode, $qty, 'increment');
                } elseif ($action === 'RETURN') {
                    $this->adjustHolderAccessoryStock($holderType, $holderCode, $holderName, $accCode, $qty, 'decrement');
                }
            }

            $this->logAccessoryTransaction($accCode, $qty, $action, $from, $to, $technicianCode, $notes);
        }
    }

    // ==========================================
    // WAREHOUSE SESSION SELECTOR
    // ==========================================

    public function selectWarehouse()
    {
        $user = auth()->user();

        // Hanya Super Admin dan Admin yang boleh memilih gudang.
        if (!$user->canSelectWarehouse()) {
            return redirect()->route('dashboard');
        }

        $query = Warehouse::orderBy('name');
        
        if ($user->hasRole(\App\Models\User::ROLE_ADMIN)) {
            $query->where(function ($q) use ($user) {
                $q->whereRaw('LOWER(type) = ?', ['pusat'])
                  ->orWhere('code', $user->warehouse_code);
            });
        }

        $warehouses = $query->get();
        $allRegions = Warehouse::whereRaw('LOWER(type) != ?', ['pusat'])
                               ->whereNotNull('region')
                               ->pluck('region')->unique()->sort()->values();

        return view('select_warehouse', compact('warehouses', 'allRegions'));
    }

    public function setWarehouse(Request $request)
    {
        $user = $request->user();

        if (!$user->canSelectWarehouse()) {
            return redirect()->route('dashboard');
        }

        if ($request->warehouse_code === '__global__') {
            // Global mode: Super Admin & Admin.
            if (!$user->hasRole(\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_ADMIN)) {
                return redirect()->back()->withErrors(['msg' => 'Anda tidak memiliki akses untuk menggunakan mode Global.']);
            }
            WarehouseSessionService::clear();
            return redirect()->route('dashboard')->with('success', 'Mode Global diaktifkan (semua gudang).');
        }

        // Regional mode: __region_EAST__, __region_WEST__
        if (preg_match('/^__region_([A-Z]+)__$/', $request->warehouse_code, $matches)) {
            $region = $matches[1]; // EAST or WEST
            if (!$user->hasRole(\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_ADMIN)) {
                return redirect()->back()->withErrors(['msg' => 'Anda tidak memiliki akses untuk menggunakan mode Regional.']);
            }
            // Simpan session khusus region
            session([
                'active_warehouse_code' => $request->warehouse_code, // __region_EAST__
                'active_warehouse_name' => ucfirst(strtolower($region)) . ' Area',
                'active_warehouse_type' => 'PUSAT',
                'active_warehouse_region' => $region,
            ]);
            session()->forget('global_mode');
            return redirect()->route('dashboard')->with('success', 'Mode Regional ' . ucfirst(strtolower($region)) . ' Area diaktifkan (summary semua cabang ' . $region . ').');
        }

        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
        ]);

        $wh = Warehouse::where('code', $request->warehouse_code)->first();

        // Admin: bisa pilih semua gudang (untuk view-only jika bukan gudangnya sendiri)
        // Super Admin: bebas pilih semua
        // Tidak perlu validasi tambahan karena akses CRUD di-block oleh middleware berdasarkan sesi

        WarehouseSessionService::bind($wh);

        return redirect()->route('dashboard')->with('success', "Gudang aktif disetel ke: {$wh->name} ({$wh->code})");
    }

    // ==========================================
    // DASHBOARD
    // ==========================================

    public function dashboard(Request $request)
    {
        $service = new \App\Services\DashboardInsightService();

        // User terikat gudang: paksa scope ke gudangnya. Super Admin/Admin: default ke gudang aktif dari session.
        $user = $request->user();

        // ── Teknisi: dashboard menampilkan HANYA data milik teknisi itu sendiri ──
        if ($user->hasRole('technician')) {
            // Cari data teknisi yang ditautkan ke user ini (by name match)
            $techRecord = \App\Models\Technician::where('name', $user->name)->first();
            $techName   = $user->name;
            $techCode   = $techRecord?->code;

            // Perangkat yang sedang dipegang teknisi (ISSUED/INSTALLED)
            $holderLike = 'Technician: ' . $techName;
            $techDevices = Device::whereIn('status', ['ISSUED', 'INSTALLED', 'PENDING_ACCEPTANCE'])
                ->where('current_holder', $holderLike)
                ->get();

            $metrics = [
                'total_devices'     => $techDevices->count(),
                'total_in_stock'    => 0,
                'total_pending_qc'  => 0,
                'total_qc_done'     => 0,
                'total_in_transit'  => $techDevices->where('status', 'IN_TRANSIT')->count(),
                'total_issued'      => $techDevices->whereIn('status', ['ISSUED', 'PENDING_ACCEPTANCE'])->count(),
                'total_installed'   => $techDevices->where('status', 'INSTALLED')->count(),
                'total_rejected'    => 0,
                'total_flagged'     => $techDevices->where('status', 'FLAGGED')->count(),
            ];

            // Aksesori yang dipegang teknisi
            $techAccs = \App\Models\HolderAccessory::where('holder_type', 'TECHNICIAN')
                ->where(function($q) use ($techCode, $techName) {
                    if ($techCode) $q->where('holder_code', $techCode);
                    else $q->where('holder_name', $techName);
                })
                ->get();

            $areaStock = [
                $techName => [
                    'devices'     => $techDevices->count(),
                    'sim'         => 0,
                    'accessories' => $techAccs->sum('qty'),
                ],
            ];

            $pendingHandover = Device::where('status', 'PENDING_ACCEPTANCE')
                ->where('pending_handover_to_user_id', $user->id)
                ->count();

            $recent_tx = DeviceTransaction::where(function($q) use ($holderLike) {
                    $q->where('to_location', 'like', '%' . $holderLike . '%')
                      ->orWhere('from_location', 'like', '%' . $holderLike . '%');
                })
                ->latest()->take(5)->get()->map(fn($tx) => [
                    'device_sn'  => $tx->device_sn,
                    'action'     => $tx->action,
                    'from'       => $tx->from_location,
                    'to'         => $tx->to_location,
                    'operator'   => $tx->operator,
                    'scanned_by' => $tx->scanned_by,
                    'timestamp'  => $tx->created_at->format('Y-m-d H:i:s'),
                ])->toArray();

            $insights  = [];
            $burnRate  = ['labels' => [], 'issued' => [], 'returned' => []];
            $distribution = [];
            $stockAlerts  = [];
            $warehouses   = [$user->warehouse_code ?? 'teknisi' => 'Stok Teknisi: ' . $techName];
            $view         = $user->warehouse_code ?? 'teknisi';
            $stockMetricsLabel = 'Teknisi: ' . $techName;
            $pendingIncoming   = $pendingHandover;

            return view('dashboard', compact(
                'metrics', 'insights', 'recent_tx', 'burnRate', 'distribution',
                'warehouses', 'view', 'stockAlerts', 'pendingIncoming',
                'areaStock', 'stockMetricsLabel'
            ));
        }

        if ($user->isWarehouseBound()) {
            $view = $user->warehouse_code;
        } else {
            $view = $request->query('view', session('active_warehouse_code') ?: 'global');
        }
        $scope = null;
        if ($view !== 'global') {
            // Handle __region_EAST__ / __region_WEST__ virtual codes
            if (preg_match('/^__region_([A-Z]+)__$/', $view, $regionMatch)) {
                $scope = Warehouse::where('region', $regionMatch[1])
                                  ->pluck('code')->toArray();
                if (empty($scope)) $scope = ['NONE'];
            } else {
                $wh = Warehouse::find($view);
                if ($wh && strtolower($wh->type) === 'pusat') {
                    // Aggregate: get all branches in this region
                    $scope = Warehouse::where('region', $wh->region)
                                      ->whereRaw('LOWER(type) != ?', ['pusat'])
                                      ->pluck('code')->toArray();
                    if (empty($scope)) $scope = ['NONE'];
                } else {
                    $scope = $view;
                }
            }
        }

        $warehouses = [];
        if ($user->isWarehouseBound()) {
            $wh = Warehouse::find($user->warehouse_code);
            if ($wh) $warehouses[$wh->code] = $wh->name;
        } else {
            $sessionWhCode = session('active_warehouse_code');
            if (empty($sessionWhCode) || $sessionWhCode === '__global__') {
                $warehouses['global'] = 'Global (Semua Gudang)';
                $warehouses['__region_EAST__'] = 'East Area';
                $warehouses['__region_WEST__'] = 'West Area';
            } elseif (preg_match('/^__region_([A-Z]+)__$/', $sessionWhCode, $regionMatch)) {
                $region = $regionMatch[1];
                $warehouses[$sessionWhCode] = ucfirst(strtolower($region)) . ' Area (Semua Cabang)';
                $branches = Warehouse::where('region', $region)->orderBy('name')->pluck('name', 'code')->toArray();
                foreach ($branches as $code => $name) {
                    $warehouses[$code] = $name;
                }
            } else {
                $wh = Warehouse::find($sessionWhCode);
                if ($wh) $warehouses[$wh->code] = $wh->name;
            }
        }

        // ----- Stock Preview Scope: ikuti gudang kerja aktif (session) -----
        $sessionWhCode = session('active_warehouse_code');
        $stockMetricsScope = null;
        $stockMetricsLabel = 'Global (Semua Gudang)';
        if ($user->isWarehouseBound()) {
            $stockMetricsScope = $user->warehouse_code;
            $stockMetricsLabel = $warehouses[$user->warehouse_code] ?? $user->warehouse_code;
        } elseif ($sessionWhCode) {
            // Handle __region_EAST__ / __region_WEST__ virtual codes
            if (preg_match('/^__region_([A-Z]+)__$/', $sessionWhCode, $regionMatch)) {
                $stockMetricsScope = Warehouse::where('region', $regionMatch[1])
                    ->pluck('code')->toArray();
                if (empty($stockMetricsScope)) $stockMetricsScope = ['NONE'];
                $stockMetricsLabel = ucfirst(strtolower($regionMatch[1])) . ' Area (Region)';
            } else {
                // Super Admin / Admin yang sudah pilih gudang kerja di session
                $sessionWh = Warehouse::find($sessionWhCode);
                if ($sessionWh && strtolower($sessionWh->type) === 'pusat') {
                    $stockMetricsScope = Warehouse::where('region', $sessionWh->region)
                        ->whereRaw('LOWER(type) != ?', ['pusat'])
                        ->pluck('code')->toArray();
                    if (empty($stockMetricsScope)) $stockMetricsScope = ['NONE'];
                    $stockMetricsLabel = $sessionWh->name . ' (Region)';
                } else {
                    $stockMetricsScope = $sessionWhCode;
                    $stockMetricsLabel = session('active_warehouse_name') ?? $sessionWhCode;
                }
            }
        }

        $metrics      = $service->getGlobalMetrics($stockMetricsScope);
        $insights     = $service->getInsights(is_array($stockMetricsScope) ? ($stockMetricsScope[0] ?? null) : $stockMetricsScope);
        $burnRate     = $service->getBurnRateSeries($scope);
        $distribution = $service->getDistribution($scope);

        $recent_tx = DeviceTransaction::when($scope, function ($q) use ($scope) {
                $q->where(function ($sub) use ($scope) {
                    if (is_array($scope)) {
                        $sub->whereIn('from_location', $scope)
                            ->orWhereIn('to_location', $scope);
                    } else {
                        $sub->where('from_location', $scope)
                            ->orWhere('to_location', $scope);
                    }
                });
            })
            ->latest()->take(5)->get()->map(fn($tx) => [
                'device_sn'  => $tx->device_sn,
                'action'     => $tx->action,
                'from'       => $tx->from_location,
                'to'         => $tx->to_location,
                'operator'   => $tx->operator,
                'scanned_by' => $tx->scanned_by,
                'timestamp'  => $tx->created_at->format('Y-m-d H:i:s'),
            ])->toArray();

        // Stok gudang per AREA (IN_STOCK)
        $areaStock = $this->getWarehouseAreaStock($stockMetricsScope);

        // Alert Center terintegrasi: peringatan stok minimum + transfer masuk
        // yang masih menunggu diterima di gudang (untuk Priority Stream & feed).
        $stockAlerts = $service->getStockAlerts($stockMetricsScope);
        $pendingIncoming = DeliveryOrder::where('status', 'IN_TRANSIT')
            ->when($scope, function ($q) use ($scope) {
                if (is_array($scope)) {
                    $q->whereIn('to_warehouse_code', $scope);
                } else {
                    $q->where('to_warehouse_code', $scope);
                }
            })
            ->count();

        return view('dashboard', compact('metrics', 'insights', 'recent_tx', 'burnRate', 'distribution', 'warehouses', 'view', 'stockAlerts', 'pendingIncoming', 'areaStock', 'stockMetricsLabel'));
    }

    private function getWarehouseAreaStock($scope = null): array
    {
        $agg = [];
        $bucket = function (string $area) use (&$agg): void {
            if (!isset($agg[$area])) {
                $agg[$area] = ['devices' => 0, 'sim' => 0, 'accessories' => 0];
            }
        };

        $warehouses = \App\Models\Warehouse::when($scope, function ($q) use ($scope) {
            if (is_array($scope)) {
                $q->whereIn('code', $scope);
            } else {
                $q->where('code', $scope);
            }
        })->get(['code', 'name']);
        
        $warehouseMap = $warehouses->pluck('name', 'code')->toArray();

        // Device IN_STOCK
        \App\Models\Device::where('status', 'IN_STOCK')
            ->when($scope, function ($q) use ($scope) {
                if (is_array($scope)) {
                    $q->whereIn('warehouse_code', $scope);
                } else {
                    $q->where('warehouse_code', $scope);
                }
            })
            ->selectRaw('warehouse_code, count(*) as total')
            ->groupBy('warehouse_code')
            ->get()
            ->each(function ($d) use (&$agg, $warehouseMap, $bucket) {
                $area = $warehouseMap[$d->warehouse_code] ?? 'Tanpa Area';
                $bucket($area);
                $agg[$area]['devices'] += (int) $d->total;
            });

        // SIM IN_STOCK
        \App\Models\GsmSimcard::where('status', 'IN_STOCK')
            ->when($scope, function ($q) use ($scope) {
                if (is_array($scope)) {
                    $q->whereIn('warehouse_code', $scope);
                } else {
                    $q->where('warehouse_code', $scope);
                }
            })
            ->selectRaw('warehouse_code, count(*) as total')
            ->groupBy('warehouse_code')
            ->get()
            ->each(function ($s) use (&$agg, $warehouseMap, $bucket) {
                $area = $warehouseMap[$s->warehouse_code] ?? 'Tanpa Area';
                $bucket($area);
                $agg[$area]['sim'] += (int) $s->total;
            });


        // Accessories IN_STOCK
        \App\Models\WarehouseAccessory::when($scope, function ($q) use ($scope) {
                if (is_array($scope)) {
                    $q->whereIn('warehouse_code', $scope);
                } else {
                    $q->where('warehouse_code', $scope);
                }
            })
            ->selectRaw('warehouse_code, sum(qty) as total')
            ->groupBy('warehouse_code')
            ->get()
            ->each(function ($a) use (&$agg, $warehouseMap, $bucket) {
                $area = $warehouseMap[$a->warehouse_code] ?? 'Tanpa Area';
                $bucket($area);
                $agg[$area]['accessories'] += (int) $a->total;
            });

        uasort($agg, fn ($a, $b) => $b['devices'] <=> $a['devices']);

        return $agg;
    }

    /**
     * Drill-down detail untuk kartu angka di Dashboard (dibuka sebagai modal).
     * Mengembalikan JSON generik {title, columns, rows} agar modal bersifat reusable.
     */
    public function dashboardDrilldown(Request $request)
    {
        $metric = (string) $request->query('metric', '');
        $view   = $request->query('view', 'global');
        $scope  = $view === 'global' ? null : $view;
        $limit  = 100;

        $deviceScope = fn ($q) => $scope ? $q->where('warehouse_code', $scope) : $q;

        $mapDevices = function ($rows) {
            return $rows->map(fn ($d) => [
                $d->serial_number,
                $d->model ?: $d->type,
                $d->status,
                $d->unit_condition ?: '-',
                $d->warehouse_code ?: '-',
                $d->current_holder ?: '-',
            ])->all();
        };
        $deviceColumns = ['Serial Number', 'Model', 'Status', 'Kondisi', 'Gudang', 'Pemegang'];

        switch ($metric) {
            case 'in_stock':
                $q = Device::where('status', 'IN_STOCK')
                    ->where('warehouse_code', 'LIKE', 'WH-AREA-%');
                $deviceScope($q);
                return response()->json([
                    'title'   => 'Warehouse (IN STOCK Area)',
                    'columns' => $deviceColumns,
                    'rows'    => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total'   => (clone $q)->count(),
                ]);

            case 'stock_baru':
            case 'stock_bekas':
                $q = Device::where('status', 'IN_STOCK');
                $deviceScope($q);
                if ($metric === 'stock_baru') $q->where('unit_condition', 'BARU');
                if ($metric === 'stock_bekas') $q->where('unit_condition', 'BEKAS');
                $title = $metric === 'stock_baru' ? 'Perangkat IN STOCK — Kondisi BARU'
                       : 'Perangkat IN STOCK — Kondisi BEKAS';
                return response()->json([
                    'title'   => $title,
                    'columns' => $deviceColumns,
                    'rows'    => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total'   => (clone $q)->count(),
                ]);

            case 'pending_qc':
                $q = Device::where('status', 'PENDING_QC');
                $deviceScope($q);
                return response()->json([
                    'title'   => 'Perangkat Menunggu QC (PENDING_QC)',
                    'columns' => $deviceColumns,
                    'rows'    => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total'   => (clone $q)->count(),
                ]);

            case 'qc_done':
                $q = Device::where('status', 'IN_STOCK')
                    ->where(function ($x) {
                        $x->where('warehouse_code', 'LIKE', 'WH-REG-%')
                          ->orWhere('warehouse_code', 'WH-PUSAT');
                    });
                $deviceScope($q);
                return response()->json([
                    'title'   => 'QC Done (OK / Reject) di Pusat/Regional',
                    'columns' => $deviceColumns,
                    'rows'    => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total'   => (clone $q)->count(),
                ]);

            case 'in_transit':
                $q = Device::where('status', 'IN_TRANSIT');
                $deviceScope($q);
                return response()->json([
                    'title' => 'Perangkat Sedang Transfer (IN_TRANSIT)',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'flagged':
                $q = Device::where('status', 'FLAGGED');
                $deviceScope($q);
                return response()->json([
                    'title' => 'Perangkat Bermasalah (FLAGGED)',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'issued':
                $q = Device::where('status', 'ISSUED')
                    ->where(function ($x) {
                        $x->where('current_holder', 'not like', 'Customer:%')
                          ->orWhereNull('current_holder');
                    });
                $deviceScope($q);
                return response()->json([
                    'title' => 'Perangkat Dipegang Teknisi (ISSUED)',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'at_customer':
                $q = Device::whereIn('status', ['ISSUED', 'INSTALLED'])
                    ->where('current_holder', 'like', 'Customer:%');
                $deviceScope($q);
                return response()->json([
                    'title' => 'Stok di Customer',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'installed':
                $q = Device::where('status', 'INSTALLED');
                $deviceScope($q);
                return response()->json([
                    'title' => 'Perangkat Terpasang (INSTALLED)',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'rejected':
                $q = Device::where('status', 'REJECTED');
                $deviceScope($q);
                return response()->json([
                    'title' => 'Perangkat Rusak/Reject (REJECTED)',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'total_devices':
                $q = Device::query();
                $deviceScope($q);
                return response()->json([
                    'title' => 'Semua Perangkat',
                    'columns' => $deviceColumns,
                    'rows' => $mapDevices($q->latest('updated_at')->limit($limit)->get()),
                    'total' => (clone $q)->count(),
                ]);

            case 'accessories':
                $q = WarehouseAccessory::query()->where('qty', '>', 0)
                    ->when($scope, fn ($x) => $x->where('warehouse_code', $scope));
                $names = Accessory::pluck('name', 'code');
                $rows = $q->orderByDesc('qty')->limit($limit)->get()->map(fn ($w) => [
                    $w->accessory_code,
                    $names[$w->accessory_code] ?? $w->accessory_code,
                    $w->warehouse_code,
                    (int) $w->qty,
                ])->all();
                return response()->json([
                    'title' => 'Stok Aksesoris di Gudang',
                    'columns' => ['Kode', 'Nama', 'Gudang', 'Qty'],
                    'rows' => $rows,
                    'total' => (clone $q)->count(),
                ]);

            case 'area_field':
                $area = (string) $request->query('area', '');
                $areaByName = Technician::pluck('area', 'name');
                // Holder string perangkat = "Technician: NAMA".
                $holdersWithArea = $areaByName
                    ->filter(fn ($a) => !empty($a))
                    ->keys()
                    ->map(fn ($name) => 'Technician: ' . $name)
                    ->all();

                $q = Device::where('status', 'ISSUED');
                if ($area === 'Tanpa Area' || $area === '') {
                    // Dipegang teknisi tanpa area (atau holder non-teknisi terdaftar).
                    $q->where(function ($x) use ($holdersWithArea) {
                        $x->whereNull('current_holder')->orWhere('current_holder', '');
                        if (!empty($holdersWithArea)) {
                            $x->orWhereNotIn('current_holder', $holdersWithArea);
                        }
                    });
                } else {
                    $names = $areaByName->filter(fn ($a) => $a === $area)->keys()
                        ->map(fn ($name) => 'Technician: ' . $name)->all();
                    $q->whereIn('current_holder', $names ?: ['__none__']);
                }

                $rows = $q->latest('updated_at')->limit($limit)->get()->map(fn ($d) => [
                    $d->serial_number,
                    $d->model ?: $d->type,
                    $d->unit_condition ?: '-',
                    str_replace('Technician: ', '', (string) $d->current_holder) ?: '-',
                    $d->gsm_simcard_id ? 'Ada' : '-',
                ])->all();
                return response()->json([
                    'title'   => 'Perangkat di Lapangan — Area ' . ($area ?: 'Tanpa Area'),
                    'columns' => ['Serial Number', 'Model', 'Kondisi', 'Teknisi', 'GSM'],
                    'rows'    => $rows,
                    'total'   => (clone $q)->count(),
                ]);

            case 'sim_stock':
            case 'sim_installed':
                $status = $metric === 'sim_installed' ? 'INSTALLED' : 'IN_STOCK';
                $q = GsmSimcard::where('status', $status)
                    ->when($scope && $status === 'IN_STOCK', fn ($x) => $x->where('warehouse_code', $scope));
                $rows = $q->orderBy('provider')->limit($limit)->get()->map(fn ($s) => [
                    $s->msisdn,
                    $s->provider ?: '-',
                    $s->category ?: '-',
                    $s->warehouse_code ?: '-',
                ])->all();
                return response()->json([
                    'title' => $status === 'INSTALLED' ? 'Kartu SIM Terpasang (INSTALLED)' : 'Kartu SIM Siap di Gudang',
                    'columns' => ['MSISDN', 'Provider', 'Kategori', 'Gudang'],
                    'rows' => $rows,
                    'total' => (clone $q)->count(),
                ]);
        }

        return response()->json(['title' => 'Detail', 'columns' => [], 'rows' => [], 'total' => 0], 422);
    }

    // ==========================================
    // RECEIVING (Device & Accessory)
    // ==========================================

    public function receiving()
    {
        $warehouses         = Warehouse::pluck('name', 'code')->toArray();
        $deviceModels       = DeviceModel::all()->toArray();
        $accessories        = Accessory::all()->toArray();
        $suggestedDevices   = $this->getDeviceSuggestions();
        $suggestedAccessories = $this->getAccessorySuggestions('RECEIVING');

        // SIM yang belum punya gudang (pool) — siap diterima ke gudang tertentu.
        $poolSimcards = GsmSimcard::whereNull('warehouse_code')
            ->where('status', 'IN_STOCK')
            ->orderBy('provider')
            ->get(['id', 'msisdn', 'provider', 'category'])
            ->toArray();

        // Daftar provider untuk dropdown input manual (gabungan yang sudah ada + umum).
        $simProviders = \App\Models\SimcardMaster::query()->whereNotNull('provider')->distinct()->pluck('provider')->toArray();
        sort($simProviders);

        // Map provider to its categories from Master Data
        $simcardMasters = \App\Models\SimcardMaster::all();
        $simProviderCategories = [];
        foreach ($simcardMasters as $sm) {
            if ($sm->provider && $sm->category) {
                $simProviderCategories[$sm->provider][] = $sm->category;
            }
        }
        foreach ($simProviderCategories as $prov => $cats) {
            $simProviderCategories[$prov] = array_values(array_unique($cats));
        }

        // Ambil semua SN yang sudah ada di database untuk mencegah duplikat saat scan (client-side)
        $existingSns = Device::pluck('serial_number')->toArray();

        // Ambil semua MSISDN yang sudah ada di database (global, seluruh gudang)
        $existingMsisdns = GsmSimcard::pluck('msisdn')->toArray();

        return view('receiving', compact(
            'warehouses', 'deviceModels', 'accessories',
            'suggestedDevices', 'suggestedAccessories', 'poolSimcards', 'simProviders', 'simProviderCategories', 'existingSns', 'existingMsisdns'
        ));
    }

    public function postReceiving(Request $request)
    {
        $request->validate([
            'warehouse' => 'required|exists:warehouses,code',
            'sns'       => 'required|array',
        ]);

        $duplicateSns = [];

        DB::transaction(function () use ($request, &$duplicateSns) {
            foreach ($request->sns as $index => $sn) {
                $imei  = $request->imeis[$index] ?? '358' . rand(100000000000, 999999999999);
                $type  = $request->types[$index] ?? 'GPS Tracker';
                $model = $request->models[$index] ?? 'Standard VT-Model';
                $cond  = strtoupper($request->conditions[$index] ?? 'BARU') === 'BEKAS' ? 'BEKAS' : 'BARU';
                // Ambil rack per SN (dari hidden per-row), fallback ke rack global
                $rackBarcode = trim($request->rack_barcodes[$index] ?? $request->rack_barcode ?? '') ?: null;

                // Cek duplikat — kumpulkan info untuk ditampilkan ke user
                $existing = Device::where('serial_number', $sn)->first(['serial_number', 'status', 'warehouse_code', 'current_holder']);
                if ($existing) {
                    $duplicateSns[] = "SN {$sn} (Status: {$existing->status}, Gudang: {$existing->warehouse_code}, Pemegang: " . ($existing->current_holder ?? '-') . ")";
                    continue;
                }

                $device = Device::create([
                    'serial_number'  => $sn,
                    'imei'           => $imei,
                    'type'           => $type,
                    'model'          => $model,
                    // Barang masuk WAJIB lewat QC dulu (Tim RND) sebelum jadi stok siap pakai.
                    'status'         => 'PENDING_QC',
                    'unit_condition' => $cond,
                    'warehouse_code' => $request->warehouse,
                    'current_holder' => 'Warehouse ' . $request->warehouse,
                    'rack_barcode'   => $rackBarcode,
                ]);

                $this->logDeviceTransaction($device, 'RECEIVING', 'Supplier', $request->warehouse, auth()->user()->name, 'Scanner-HID-01', 'Kondisi: ' . $cond . ' | Rak: ' . ($rackBarcode ?? '-') . ' | Menunggu QC');
            }
        });

        $this->dispatchStockUpdate();

        if (!empty($duplicateSns)) {
            $dupeList = implode("\n", $duplicateSns);
            $skipped  = count($duplicateSns);
            $total    = count($request->sns);
            $added    = $total - $skipped;

            $msg = "⚠️ PERINGATAN DUPLIKAT: {$skipped} SN sudah terdaftar di sistem dan TIDAK disimpan ulang:\n{$dupeList}";
            if ($added > 0) {
                return redirect()->route('receiving', ['tab' => 'device'])
                    ->with('warning', $msg)
                    ->with('success', "{$added} perangkat berhasil diterima & masuk antrian QC.");
            }
            return redirect()->route('receiving', ['tab' => 'device'])->withErrors(['duplicate' => $msg]);
        }

        return redirect()->route('receiving', ['tab' => 'device'])->with('success', 'Perangkat diterima & masuk antrian QC. Tim RND perlu melakukan QC sebelum perangkat menjadi stok siap pakai.');
    }

    public function postReceivingAccessory(Request $request)
    {
        $request->validate([
            'warehouse'  => 'required|exists:warehouses,code',
            'acc_types'  => 'required|array',
            'acc_qtys'   => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            $this->processAccessoryQtyForm($request, 'RECEIVING', $request->warehouse, 'Supplier', $request->warehouse);
        });

        $this->dispatchStockUpdate();
        return redirect()->route('receiving', ['tab' => 'accessory'])->with('success', 'Berhasil menerima aksesoris ke stok gudang.');
    }

    /**
     * Penerimaan kartu SIM/GSM ke gudang. Mendukung 3 mode (boleh digabung):
     *  - Pilih dari pool (sim_ids[])
     *  - Input manual / scan (sim_msisdns[], sim_providers[], sim_categories[])
     *  - Bulk upload CSV (file: msisdn,provider,category)
     */
    public function postReceivingSimcard(Request $request)
    {
        $request->validate([
            'warehouse'      => 'required|exists:warehouses,code',
            'sim_ids'        => 'nullable|array',
            'sim_msisdns'    => 'nullable|array',
            'csv_file'       => 'nullable|file|mimes:csv,txt',
        ]);

        $warehouse = $request->warehouse;
        $received  = 0;

        DB::transaction(function () use ($request, $warehouse, &$received) {
            // 1) Pilih dari pool (centang)
            foreach ((array) $request->sim_ids as $simId) {
                $sim = GsmSimcard::find($simId);
                if (!$sim) continue;
                $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $warehouse]);
                $this->logSimcardTransaction($sim, 'RECEIVING', 'Pool', $warehouse, $warehouse);
                $received++;
            }

            // 2) Input manual / scan
            foreach ((array) $request->sim_msisdns as $idx => $msisdn) {
                $msisdn = trim((string) $msisdn);
                if ($msisdn === '') continue;
                $sim = GsmSimcard::updateOrCreate(
                    ['msisdn' => $msisdn],
                    [
                        'provider' => $request->sim_providers[$idx] ?? 'Unknown',
                        'category' => $request->sim_categories[$idx] ?? 'General',
                        'status'   => 'IN_STOCK',
                        'warehouse_code' => $warehouse,
                    ]
                );
                $this->logSimcardTransaction($sim, 'RECEIVING', 'Supplier', $warehouse, $warehouse);
                $received++;
            }

            // 3) Bulk CSV (msisdn, provider, category)
            if ($request->hasFile('csv_file')) {
                $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
                if ($handle !== false) {
                    fgetcsv($handle, 1000, ','); // skip header
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $msisdn = trim((string) ($row[0] ?? ''));
                        if ($msisdn === '') continue;
                        $sim = GsmSimcard::updateOrCreate(
                            ['msisdn' => $msisdn],
                            [
                                'provider' => $row[1] ?? 'Unknown',
                                'category' => $row[2] ?? 'General',
                                'status'   => 'IN_STOCK',
                                'warehouse_code' => $warehouse,
                            ]
                        );
                        $this->logSimcardTransaction($sim, 'RECEIVING', 'Bulk CSV', $warehouse, $warehouse);
                        $received++;
                    }
                    fclose($handle);
                }
            }
        });

        if ($received === 0) {
            return redirect()->back()->withErrors(['msg' => 'Tidak ada kartu SIM yang diterima. Pilih dari pool, isi manual, atau unggah CSV.'])->withInput();
        }

        $this->dispatchStockUpdate();
        return redirect()->route('receiving', ['tab' => 'simcard'])->with('success', "Berhasil menerima {$received} kartu SIM ke gudang {$warehouse}.");
    }

    // ==========================================
    // WAREHOUSE TRANSFER (Device & Accessory)
    // ==========================================

    public function transfer()
    {
        $warehouses      = Warehouse::pluck('name', 'code')->toArray();

        // Sertakan devices (sebagai daftar serial number) agar manifes & verifikasi
        // scan di tab "Terima Barang Masuk" berfungsi. Tanpa ini, currentSjData.devices
        // undefined sehingga verifikasi device terlewati di UI.
        $delivery_orders = DeliveryOrder::where('status', 'IN_TRANSIT')
            ->with(['devices', 'accessories', 'simcards'])
            ->get()
            ->map(function ($do) {
                $arr = $do->toArray();
                $arr['devices'] = $do->devices->pluck('serial_number')->values()->toArray();
                return $arr;
            })
            ->keyBy('id')
            ->toArray();

        $devices         = Device::where('status', 'IN_STOCK')->get()->toArray();

        // Lookup SN → status/gudang untuk validasi scan di UI (termasuk diagnosa non-IN_STOCK).
        $deviceLookup = Device::query()
            ->get(['serial_number', 'status', 'warehouse_code', 'type', 'unit_condition', 'model'])
            ->mapWithKeys(fn ($d) => [
                $d->serial_number => [
                    'status'         => $d->status,
                    'warehouse_code' => $d->warehouse_code,
                    'type'           => $d->type,
                    'model'          => $d->model,
                    'unit_condition' => $d->unit_condition,
                ],
            ])
            ->toArray();

        $accessories     = Accessory::all()->keyBy('code')->toArray();

        // SIM IN_STOCK yang punya gudang — bisa dimutasi antar gudang.
        $simcards = GsmSimcard::where('status', 'IN_STOCK')
            ->whereNotNull('warehouse_code')
            ->orderBy('provider')
            ->get(['id', 'msisdn', 'provider', 'category', 'warehouse_code'])
            ->toArray();

        // AI Suggestions
        $suggestedRoutes      = $this->getTransferRouteSuggestions();
        $suggestedAccessories = $this->getAccessorySuggestions('TRANSFER_OUT');

        // Per-warehouse accessory stock for the UI
        $warehouseAccessories = WarehouseAccessory::all()
            ->groupBy('warehouse_code')
            ->map(fn($items) => $items->keyBy('accessory_code')->map(fn($item) => $item->qty))
            ->toArray();

        return view('transfer', compact(
            'warehouses', 'delivery_orders', 'devices', 'deviceLookup', 'accessories',
            'suggestedRoutes', 'suggestedAccessories', 'warehouseAccessories', 'simcards'
        ));
    }

    public function postCreateTransfer(Request $request)
    {
        $request->validate([
            'from_warehouse' => 'required|exists:warehouses,code',
            'to_warehouse'   => 'required|exists:warehouses,code',
            'sns'            => 'nullable|array',
        ]);

        // Gudang asal & tujuan tidak boleh sama.
        if ($request->from_warehouse === $request->to_warehouse) {
            return redirect()->back()->withErrors(['msg' => 'Gudang asal dan tujuan tidak boleh sama.'])->withInput();
        }

        // Harus ada minimal 1 item (device, aksesoris, atau kartu GSM) untuk dikirim.
        $hasSns = $request->has('sns') && count(array_filter((array) $request->sns)) > 0;
        $hasSim = $request->has('sim_ids') && count(array_filter((array) $request->sim_ids)) > 0;
        $hasAcc = false;
        if ($request->has('acc_types') && $request->has('acc_qtys')) {
            foreach ($request->acc_qtys as $qty) {
                if (intval($qty) > 0) { $hasAcc = true; break; }
            }
        }
        if (!$hasSns && !$hasAcc && !$hasSim) {
            return redirect()->back()->withErrors(['msg' => 'Tidak ada barang untuk ditransfer. Scan device, pilih aksesoris, atau pilih kartu GSM dahulu.'])->withInput();
        }

        // Validasi server-side: qty aksesoris tidak boleh melebihi stok gudang asal.
        if ($error = $this->validateAccessoryStock($request, $request->from_warehouse)) {
            return redirect()->back()->withErrors(['msg' => $error])->withInput();
        }

        $sjId = 'SJ-' . date('dmy') . '-' . rand(10, 99);

        DB::transaction(function () use ($request, $sjId) {
            $deliveryOrder = DeliveryOrder::create([
                'id'                  => $sjId,
                'from_warehouse_code' => $request->from_warehouse,
                'to_warehouse_code'   => $request->to_warehouse,
                'status'              => 'IN_TRANSIT',
            ]);

            // --- Process Devices ---
            $deviceIds = [];
            if ($request->has('sns')) {
                foreach ($request->sns as $sn) {
                    $device = Device::where('serial_number', $sn)->where('status', 'IN_STOCK')->first();
                    if (!$device) continue;

                    // Integritas: hanya device dari gudang asal yang boleh ditransfer.
                    if ($device->warehouse_code !== $request->from_warehouse) continue;

                    $device->update(['status' => 'IN_TRANSIT']);
                    $deviceIds[] = $device->id;

                    $this->logDeviceTransaction($device, 'TRANSFER_OUT', $request->from_warehouse, $request->to_warehouse);
                }
                $deliveryOrder->devices()->sync($deviceIds);
            }

            // --- Process Accessories ---
            if ($request->has('acc_types')) {
                foreach ($request->acc_types as $idx => $accCode) {
                    $qty = intval($request->acc_qtys[$idx] ?? 0);
                    if ($qty <= 0) continue;

                    $acc = Accessory::find($accCode);
                    if (!$acc) continue;

                    // Attach to DO manifest
                    $deliveryOrder->accessories()->attach($accCode, ['qty' => $qty]);

                    // Kurangi stok gudang asal; qty global tersinkron otomatis.
                    $this->adjustWarehouseAccessoryStock($request->from_warehouse, $accCode, $qty, 'decrement');

                    $this->logAccessoryTransaction($accCode, $qty, 'TRANSFER_OUT', $request->from_warehouse, $request->to_warehouse);
                }
            }

            // --- Process SIM cards ---
            if ($request->has('sim_ids')) {
                $simIds = [];
                foreach ((array) $request->sim_ids as $simId) {
                    $sim = GsmSimcard::where('id', $simId)
                        ->where('status', 'IN_STOCK')
                        ->where('warehouse_code', $request->from_warehouse)
                        ->first();
                    if (!$sim) continue;

                    $sim->update(['status' => 'IN_TRANSIT']);
                    $simIds[] = $sim->id;

                    $this->logSimcardTransaction($sim, 'TRANSFER_OUT', $request->from_warehouse, $request->to_warehouse, $request->from_warehouse);
                }
                if (!empty($simIds)) {
                    $deliveryOrder->simcards()->sync($simIds);
                }
            }
        });

        $this->dispatchStockUpdate();
        return redirect()->route('transfer')->with('success', "Transfer Shipment created with Delivery Order: $sjId");
    }

    public function postApproveTransfer(Request $request)
    {
        $request->validate([
            'sj_id' => 'required|exists:delivery_orders,id',
        ]);

        DB::transaction(function () use ($request) {
            $do = DeliveryOrder::findOrFail($request->sj_id);
            $do->update(['status' => 'RECEIVED']);

            // --- Approve Devices ---
            // QC hanya dilakukan di gudang penerimaan PERTAMA. Transfer hanya untuk unit
            // yang sudah IN_STOCK (sudah lolos QC), jadi saat tiba langsung IN_STOCK lagi
            // tanpa QC ulang.
            foreach ($do->devices as $device) {
                $device->update([
                    'status'         => 'IN_STOCK',
                    'warehouse_code' => $do->to_warehouse_code,
                    'current_holder' => 'Warehouse ' . $do->to_warehouse_code,
                ]);

                $this->logDeviceTransaction($device, 'TRANSFER_IN', $do->from_warehouse_code, $do->to_warehouse_code);
            }

            // --- Auto-Approve Accessories (sesuai manifes DO) ---
            foreach ($do->accessories as $acc) {
                $qty = $acc->pivot->qty;

                // Tambah stok gudang tujuan; qty global tersinkron otomatis.
                $this->adjustWarehouseAccessoryStock($do->to_warehouse_code, $acc->code, $qty, 'increment');

                $this->logAccessoryTransaction($acc->code, $qty, 'TRANSFER_IN', $do->from_warehouse_code, $do->to_warehouse_code);
            }

            // --- Approve SIM cards (terima ke gudang tujuan) ---
            foreach ($do->simcards as $sim) {
                $sim->update([
                    'status'         => 'IN_STOCK',
                    'warehouse_code' => $do->to_warehouse_code,
                ]);

                $this->logSimcardTransaction($sim, 'TRANSFER_IN', $do->from_warehouse_code, $do->to_warehouse_code, $do->to_warehouse_code);
            }
        });

        $this->dispatchStockUpdate();
        return redirect()->route('transfer')->with('success', "Delivery Order " . $request->sj_id . " approved and received into stock.");
    }

    // ==========================================
    // ISSUE DEVICE TO TECHNICIAN / CUSTOMER
    // ==========================================

    public function issue()
    {
        $user = auth()->user();
        $isTechnician = $user->hasRole('technician');

        $technicians = Technician::pluck('name', 'code')->toArray();
        $technicianAreas = Technician::pluck('area', 'code')->toArray();
        $customers   = Customer::pluck('name', 'id')->toArray();
        $accessories = Accessory::all()->keyBy('code')->toArray();
        $warehouses  = Warehouse::pluck('name', 'code')->toArray();

        if ($isTechnician) {
            // Teknisi: hanya bisa serahkan device yang ada di penguasaannya sendiri.
            $holderLike = 'Technician: ' . $user->name;
            $devices = Device::whereIn('status', ['ISSUED', 'INSTALLED'])
                ->where('current_holder', $holderLike)
                ->get()->toArray();

            // SIM yang dipegang teknisi (status ISSUED, bukan terpasang ke device)
            $boundSimIds = Device::whereNotNull('gsm_simcard_id')->pluck('gsm_simcard_id')->all();
            $simcards = GsmSimcard::whereIn('status', ['ISSUED'])
                ->whereNotIn('id', $boundSimIds ?: [0])
                ->get()
                ->map(function($sim) {
                    $sim->warehouse_code = 'TECH_SELF';
                    return $sim;
                })
                ->toArray();

            // Saldo aksesoris teknisi dari holder_accessories
            $techRecord  = Technician::where('name', $user->name)->first();
            $holderAccs  = \App\Models\HolderAccessory::where('holder_type', 'TECHNICIAN')
                ->where(function($q) use ($techRecord, $user) {
                    if ($techRecord) $q->where('holder_code', $techRecord->code);
                    else $q->where('holder_name', $user->name);
                })
                ->where('qty', '>', 0)
                ->get();

            // Dalam format warehouseAccessories (pakai 'TECH_SELF' sebagai pseudo-warehouse)
            $warehouseAccessories = [
                'TECH_SELF' => $holderAccs->keyBy('accessory_code')->map(fn($a) => $a->qty)->toArray()
            ];
        } else {
            $devices  = Device::where('status', 'IN_STOCK')->get()->toArray();
            // SIM IN_STOCK yang sudah ada di gudang (pool tanpa gudang tidak bisa dipasang).
            $simcards = GsmSimcard::where('status', 'IN_STOCK')->whereNotNull('warehouse_code')->get()->toArray();

            // Saldo aksesoris per gudang untuk filter & batas qty di UI.
            $warehouseAccessories = WarehouseAccessory::all()
                ->groupBy('warehouse_code')
                ->map(fn($items) => $items->keyBy('accessory_code')->map(fn($item) => $item->qty))
                ->toArray();
        }

        // AI Suggestions
        $suggestedAccessories = $this->getAccessorySuggestions('OUT');

        return view('issue', compact(
            'technicians', 'technicianAreas', 'customers', 'devices', 'accessories',
            'simcards', 'suggestedAccessories', 'warehouses', 'warehouseAccessories',
            'isTechnician'
        ));
    }

    // ==========================================
    // API ENDPOINTS (AJAX)
    // ==========================================

    /**
     * AJAX: Rekap stok device IN_STOCK per model, digunakan di Dashboard.
     */
    public function apiDashboardDeviceStock(Request $request)
    {
        $whCode = session('active_warehouse_code');

        $query = Device::where('status', 'IN_STOCK')
            ->when($whCode, fn($q2) => $q2->where('warehouse_code', $whCode))
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        return response()->json($query);
    }

    /**
     * AJAX: Detail list device IN_STOCK per type, digunakan di Dashboard saat kartu diklik.
     */
    public function apiDashboardDeviceStockDetails(Request $request)
    {
        $type = $request->input('type', '');
        $whCode = session('active_warehouse_code');

        $query = Device::where('status', 'IN_STOCK')
            ->when($whCode, fn($q2) => $q2->where('warehouse_code', $whCode))
            ->when($type, fn($q2) => $q2->where('type', $type))
            ->select('id', 'serial_number', 'model', 'type', 'unit_condition', 'rack_barcode', 'warehouse_code')
            ->orderBy('serial_number')
            ->get();

        return response()->json($query);
    }

    /**
     * AJAX: Cari device dari DB berdasarkan SN/IMEI.
     * Hanya device IN_STOCK yang ditampilkan (untuk menu serah terima).
     */
    public function apiSearchDevices(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Filter by warehouse jika disertakan
        $warehouseCode = $request->input('warehouse');
        // Filter by source_type: jika 'technician', cari device yang dipegang teknisi tersebut
        $sourceType = $request->input('source_type', 'warehouse'); // 'warehouse' | 'technician'
        $sourceTechCode = $request->input('source_tech');

        if ($sourceType === 'all') {
            // Cek apakah murni angka
            if (!preg_match('/^[0-9]+$/', $q)) {
                // Huruf/Kombinasi -> Return total count
                $devCount = Device::where(function($query) use ($q) {
                    $query->where('serial_number', 'like', "%{$q}%")
                        ->orWhere('imei', 'like', "%{$q}%")
                        ->orWhere('status', 'like', "%{$q}%")
                        ->orWhere('type', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('warehouse_code', 'like', "%{$q}%")
                        ->orWhere('current_holder', 'like', "%{$q}%");
                })->when($warehouseCode && $warehouseCode !== '__global__' && !str_starts_with($warehouseCode, '__region_'), fn($query) => $query->where('warehouse_code', $warehouseCode))
                ->count();
                    
                $gsmCount = \App\Models\GsmSimcard::where(function($query) use ($q) {
                    $query->where('msisdn', 'like', "%{$q}%")
                        ->orWhere('provider', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%")
                        ->orWhere('status', 'like', "%{$q}%")
                        ->orWhere('warehouse_code', 'like', "%{$q}%");
                })->when($warehouseCode && $warehouseCode !== '__global__' && !str_starts_with($warehouseCode, '__region_'), fn($query) => $query->where('warehouse_code', $warehouseCode))
                ->count();
                    
                $accCount = \App\Models\WarehouseAccessory::where(function($query) use ($q) {
                    $query->whereHas('accessory', fn($q2) => $q2->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                        ->orWhere('accessory_code', 'like', "%{$q}%");
                })->when($warehouseCode && $warehouseCode !== '__global__' && !str_starts_with($warehouseCode, '__region_'), fn($query) => $query->where('warehouse_code', $warehouseCode))
                ->count();

                $holderAccCount = \App\Models\HolderAccessory::where(function($query) use ($q) {
                    $query->whereHas('accessory', fn($q2) => $q2->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                        ->orWhere('accessory_code', 'like', "%{$q}%")
                        ->orWhere('holder_code', 'like', "%{$q}%")
                        ->orWhere('holder_name', 'like', "%{$q}%");
                })->count();

                $total = $devCount + $gsmCount + $accCount + $holderAccCount;
                return response()->json([
                    'suggestion_type' => 'count',
                    'total' => $total
                ]);
            }

            // Jika murni angka, gunakan logic pencarian device saja
            $query = Device::query()
                ->where(function ($qb) use ($q) {
                    $qb->where('serial_number', 'like', "%{$q}%")
                       ->orWhere('imei', 'like', "%{$q}%");
                })
                ->select('id', 'serial_number', 'imei', 'type', 'model', 'status', 'warehouse_code', 'current_holder', 'unit_condition');
                
            if ($warehouseCode && $warehouseCode !== '__global__' && !str_starts_with($warehouseCode, '__region_')) {
                $query->where('warehouse_code', $warehouseCode);
            }
            
            return response()->json([
                'suggestion_type' => 'list',
                'data' => $query->limit(12)->get()
            ]);
        }

        // Logic normal untuk form selain search global
        $query = Device::query()
            ->where(function ($qb) use ($q) {
                $qb->where('serial_number', 'like', "%{$q}%")
                   ->orWhere('imei', 'like', "%{$q}%");
            })
            ->select('id', 'serial_number', 'imei', 'type', 'model', 'status', 'warehouse_code', 'current_holder', 'unit_condition');

        if ($sourceType === 'technician' && $sourceTechCode) {
            $techName = \App\Models\Technician::where('code', $sourceTechCode)->value('name');
            $query->whereIn('status', ['ISSUED', 'INSTALLED'])
                  ->where('current_holder', 'like', "%{$techName}%");
        } else {
            $query->where('status', 'IN_STOCK');
            if ($warehouseCode) {
                $query->where('warehouse_code', $warehouseCode);
            }
        }

        $results = $query->limit(12)->get();
        return response()->json($results);
    }

    public function apiBulkSearchDevices(Request $request)
    {
        $sns = $request->input('sns', []);
        if (empty($sns) || !is_array($sns)) {
            return response()->json([]);
        }

        $warehouseCode = $request->input('warehouse');
        $sourceType = $request->input('source_type', 'warehouse');
        $sourceTechCode = $request->input('source_tech');

        $query = Device::query()
            ->whereIn('serial_number', $sns)
            ->select('id', 'serial_number', 'imei', 'type', 'model', 'status', 'warehouse_code', 'current_holder', 'unit_condition');

        if ($sourceType === 'technician' && $sourceTechCode) {
            $techName = \App\Models\Technician::where('code', $sourceTechCode)->value('name');
            $query->whereIn('status', ['ISSUED', 'INSTALLED'])
                  ->where('current_holder', 'like', "%{$techName}%");
        } else {
            $query->where('status', 'IN_STOCK');
            if ($warehouseCode) {
                $query->where('warehouse_code', $warehouseCode);
            }
        }

        $results = $query->get();
        return response()->json($results);
    }

    // ==========================================
    // RACK TRANSFER (Transfer Antar Rak)
    // ==========================================

    /**
     * AJAX: Ambil daftar rak untuk suatu gudang.
     */
    public function apiGetRacks(Request $request)
    {
        $warehouse = $request->query('warehouse');
        if (!$warehouse) {
            return response()->json([]);
        }
        $racks = \App\Models\WarehouseLocation::where('warehouse_code', $warehouse)
            ->select('barcode', 'rack_code', 'row_code', 'description')
            ->orderBy('rack_code')
            ->get();
        return response()->json($racks);
    }

    /**
     * AJAX: Ambil daftar device yang ada di suatu rak.
     */
    public function apiGetRackDevices(Request $request)
    {
        $rack = $request->query('rack');
        if (!$rack) {
            return response()->json([]);
        }
        $devices = Device::where('rack_barcode', $rack)
            ->where('status', 'IN_STOCK') // Hanya barang in stock di rak tsb
            ->select('id', 'serial_number', 'model', 'unit_condition')
            ->orderBy('serial_number')
            ->get();
        return response()->json($devices);
    }

    /**
     * POST: Pindah antar rak
     */
    public function postTransferRack(Request $request)
    {
        $request->validate([
            'device_ids'       => 'required|array|min:1',
            'device_ids.*'     => 'exists:devices,id',
            'source_rack'      => 'required|string',
            'destination_rack' => 'required|string',
        ]);

        $sourceRack = $request->destination_rack; // (we actually move TO destination, but let's log them)
        // Wait, source_rack is just for logging/validation.
        
        DB::transaction(function () use ($request) {
            $devices = Device::whereIn('id', $request->device_ids)->get();
            $operator = auth()->user()->name;

            foreach ($devices as $device) {
                $oldRack = $device->rack_barcode ?: 'Gudang Utama';
                
                $device->update([
                    'rack_barcode' => $request->destination_rack
                ]);

                $this->logDeviceTransaction(
                    $device, 
                    'RACK_TRANSFER', 
                    $oldRack, 
                    $request->destination_rack, 
                    $operator, 
                    'Web Form', 
                    'Pindah antar rak (dari ' . $oldRack . ' ke ' . $request->destination_rack . ')'
                );
            }
        });

        $this->dispatchStockUpdate();

        return redirect()->back()->with('success', count($request->device_ids) . ' perangkat berhasil dipindahkan ke rak ' . $request->destination_rack . '.');
    }

    /**
     * AJAX: Ambil daftar device yang pending acceptance untuk user yang sedang login.
     */
    public function apiGetPendingAcceptance()
    {
        $userId = auth()->id();
        $devices = Device::where('status', 'PENDING_ACCEPTANCE')
            ->where('pending_handover_to_user_id', $userId)
            ->select('id', 'serial_number', 'imei', 'type', 'model', 'current_holder', 'warehouse_code')
            ->get();
        return response()->json($devices);
    }

    /**
     * Teknisi konfirmasi terima barang: ubah dari PENDING_ACCEPTANCE → ISSUED.
     */
    public function postAcceptHandover(Request $request)
    {
        $userId = auth()->id();
        $user   = auth()->user();

        $devices = Device::where('status', 'PENDING_ACCEPTANCE')
            ->where('pending_handover_to_user_id', $userId)
            ->get();

        if ($devices->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tidak ada barang yang perlu dikonfirmasi.'], 404);
            }
            return redirect()->route('issue')->with('info', 'Tidak ada barang yang perlu dikonfirmasi.');
        }

        DB::transaction(function () use ($devices, $user) {
            foreach ($devices as $device) {
                $oldHolder = $device->current_holder;
                $device->update([
                    'status'                       => 'ISSUED',
                    'pending_handover_to_user_id'  => null,
                ]);
                $this->logDeviceTransaction($device, 'ISSUED', $oldHolder, $device->current_holder, $user->name, 'Digital Acceptance', 'Dikonfirmasi oleh teknisi via Digital Acceptance');
            }
        });

        $this->dispatchStockUpdate();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Berhasil dikonfirmasi. ' . $devices->count() . ' perangkat sekarang berstatus ISSUED.', 'count' => $devices->count()]);
        }
        return redirect()->route('issue')->with('success', 'Berhasil dikonfirmasi. ' . $devices->count() . ' perangkat sekarang berstatus ISSUED.');
    }

    // ==========================================
    // ISSUE DEVICE TO TECHNICIAN / CUSTOMER
    // ==========================================

    public function postIssue(Request $request)
    {
        $authUser   = auth()->user();
        $isTechUser = $authUser->hasRole('technician');

        $rules = [
            'target_type'   => 'required|in:technician,customer',
            'technician'    => 'nullable|required_if:target_type,technician|exists:technicians,code',
            'customer'      => 'nullable|required_if:target_type,customer|exists:customers,id',
            'sns'           => 'nullable|array',
            'source_type'   => 'nullable|in:warehouse,technician',
            'source_tech'   => 'nullable|exists:technicians,code',
        ];

        // Teknisi tidak perlu mengirim warehouse (tidak dipakai untuk sumber stok).
        if (!$isTechUser) {
            $rules['warehouse'] = 'required|exists:warehouses,code';
        }

        $request->validate($rules);

        // Untuk teknisi: set source_type ke 'technician' secara otomatis.
        if ($isTechUser && !$request->has('source_type')) {
            $request->merge(['source_type' => 'technician']);
        }

        // Harus ada minimal 1 device, 1 aksesoris, ATAU 1 kartu GSM
        $hasSns = $request->has('sns') && is_array($request->sns) && count($request->sns) > 0;
        $hasAcc = false;
        if ($request->has('acc_types') && $request->has('acc_qtys')) {
            foreach ($request->acc_qtys as $qty) {
                if (intval($qty) > 0) { $hasAcc = true; break; }
            }
        }
        $hasSim = $request->has('issue_sim_ids') && count(array_filter((array) $request->issue_sim_ids)) > 0;

        if (!$hasSns && !$hasAcc && !$hasSim) {
            return redirect()->back()->withErrors(['msg' => 'Harus ada minimal 1 perangkat, aksesoris, atau kartu GSM yang diserahkan.']);
        }

        // Validasi server-side: qty aksesoris tidak boleh melebihi stok gudang asal.
        if ($error = $this->validateAccessoryStock($request, $request->warehouse)) {
            return redirect()->back()->withErrors(['msg' => $error])->withInput();
        }

        $receiptNo = 'TT-' . now()->format('ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));

        // Status device saat serah terima:
        // - Ke TEKNISI → ISSUED (langsung, tanpa konfirmasi)
        // - Ke CUSTOMER → ISSUED atau INSTALLED
        $deviceStatus = 'ISSUED';
        $pendingToUserId = null;
        if ($request->target_type === 'technician') {
            $deviceStatus = 'ISSUED';
        } else {
            $deviceStatus = ($request->customer_device_status === 'INSTALLED') ? 'INSTALLED' : 'ISSUED';
        }
        $simStatus = 'ISSUED';

        $hasEseal = false;
        DB::transaction(function () use ($request, $hasSns, $receiptNo, $deviceStatus, $simStatus, $pendingToUserId, &$hasEseal, $isTechUser) {
            $holderName     = '';
            $technicianCode = null;
            $customerId     = null;
            $warehouseCode  = $request->warehouse; // Gudang asal (sumber stok aksesoris).

            // Identitas holder untuk saldo aksesoris (teknisi/customer).
            $holderType      = null;
            $holderCode      = null;
            $holderCleanName = null;

            if ($request->target_type === 'technician') {
                $tech = Technician::findOrFail($request->technician);
                $holderName     = 'Technician: ' . $tech->name;
                $technicianCode = $tech->code;
                $holderType      = HolderAccessory::TYPE_TECHNICIAN;
                $holderCode      = $tech->code;
                $holderCleanName = $tech->name;
            } else {
                $cust = Customer::findOrFail($request->customer);
                $holderName = 'Customer: ' . $cust->name;
                $customerId = $cust->id;
                $holderType      = HolderAccessory::TYPE_CUSTOMER;
                $holderCode      = (string) $cust->id;
                $holderCleanName = $cust->name;
            }

            if ($hasSns) {
                foreach ($request->sns as $sn) {
                    $device = Device::where('serial_number', $sn)->first();
                    if (!$device) continue;

                    // Integritas: hanya proses device yang benar-benar dimiliki sumber asal.
                    if ($isTechUser) {
                        // Teknisi: device harus di penguasaan teknisi yang login
                        $holderLikeSrc = 'Technician: ' . auth()->user()->name;
                        if (!in_array($device->status, ['ISSUED', 'INSTALLED']) || $device->current_holder !== $holderLikeSrc) {
                            continue;
                        }
                    } elseif ($request->filled('warehouse') && $device->warehouse_code !== $request->warehouse) {
                        continue;
                    }

                    // Vehicle plate
                    $plate = $request->input('vehicle_plates.' . $sn);
                    if ($plate) {
                        $device->vehicle_plate = $plate;
                    }

                    // SIM Card Pairing
                    $simMsisdn = $request->input('sim_pairings.' . $sn);
                    if ($simMsisdn) {
                        $simQuery = GsmSimcard::where('msisdn', $simMsisdn);
                        if ($isTechUser) {
                            $simQuery->where('status', 'ISSUED');
                        } else {
                            $simQuery->where('status', 'IN_STOCK')
                                     ->where('warehouse_code', $request->warehouse);
                        }
                        
                        $sim = $simQuery->first();
                        if ($sim) {
                            $fromLoc = $isTechUser ? ('Technician: ' . auth()->user()->name) : ($sim->warehouse_code ?? 'Warehouse');
                            $fromWh = $isTechUser ? null : $sim->warehouse_code;
                            
                            // SIM keluar dari stok gudang saat terpasang ke perangkat.
                            $sim->update(['status' => 'INSTALLED', 'warehouse_code' => null]);
                            $device->gsm_simcard_id = $sim->id;
                            $this->logSimcardTransaction($sim, 'INSTALLED', $fromLoc, $holderName, $fromWh, $receiptNo);
                        }
                    }

                    $updateData = [
                        'status'                      => $deviceStatus,
                        'current_holder'              => $holderName,
                        'pending_handover_to_user_id' => ($deviceStatus === 'PENDING_ACCEPTANCE') ? $pendingToUserId : null,
                    ];

                    // Simpan data garansi/sewa jika device E-SEAL dan diserahkan ke customer (INSTALLED).
                    $isEseal = stripos(str_replace(['-', '_', ' '], '', $device->type ?? ''), 'eseal') !== false;
                    if ($isEseal) {
                        $hasEseal = true;
                    }
                    if ($isEseal && $customerId && $deviceStatus === 'INSTALLED' && $request->filled('ownership_status')) {
                        $updateData['ownership_status'] = $request->ownership_status;

                        // Hitung warranty_end_date berdasarkan durasi + satuan.
                        $duration = max(1, (int) $request->input('warranty_duration', 1));
                        $unit     = $request->input('warranty_unit', 'months');
                        $endDate  = now();
                        switch ($unit) {
                            case 'days':   $endDate = $endDate->addDays($duration);   break;
                            case 'weeks':  $endDate = $endDate->addWeeks($duration);  break;
                            case 'months': $endDate = $endDate->addMonths($duration); break;
                            case 'years':  $endDate = $endDate->addYears($duration);  break;
                        }
                        $updateData['warranty_end_date'] = $endDate->toDateString();
                    }

                    $device->update($updateData);

                    if ($customerId) {
                        CustomerDevice::create([
                            'customer_id'  => $customerId,
                            'device_id'    => $device->id,
                            'installed_at' => $deviceStatus === 'INSTALLED' ? now() : null,
                        ]);
                    }

                    $this->logDeviceTransaction($device, $deviceStatus, $device->warehouse_code, $holderName, auth()->user()->name, 'Scanner-HID-01', $receiptNo);
                }
            }

            // Serah terima kartu GSM mandiri (tanpa device)
            foreach ((array) $request->issue_sim_ids as $simId) {
                if (!$simId) continue;
                $simQuery = GsmSimcard::where('id', $simId);
                
                if ($isTechUser) {
                    $simQuery->where('status', 'ISSUED');
                } else {
                    $simQuery->where('status', 'IN_STOCK')
                             ->where('warehouse_code', $request->warehouse);
                }
                
                $sim = $simQuery->first();
                if (!$sim) continue;

                $fromLoc = $isTechUser ? ('Technician: ' . auth()->user()->name) : ($sim->warehouse_code ?? 'Warehouse');
                $fromWh = $isTechUser ? null : $sim->warehouse_code;
                
                // SIM diserahkan ke teknisi/customer.
                $sim->update(['status' => $simStatus, 'warehouse_code' => null]);
                $this->logSimcardTransaction($sim, $simStatus, $fromLoc, $holderName, $fromWh, $receiptNo);
            }

            // Process accessories:
            // - Teknisi: kurangi dari saldo holder aksesoris teknisi itu sendiri
            // - Admin/PIC: kurangi dari stok gudang asal (WarehouseAccessory)
            if ($isTechUser) {
                $srcTechRecord = Technician::where('name', auth()->user()->name)->first();
                $srcTechCode   = $srcTechRecord?->code;
                $srcTechName   = auth()->user()->name;

                if ($request->has('acc_types')) {
                    foreach ($request->acc_types as $idx => $accCode) {
                        $qty = intval($request->acc_qtys[$idx] ?? 0);
                        if ($qty <= 0) continue;

                        // Kurangi saldo holder teknisi asal
                        if ($srcTechCode || $srcTechName) {
                            $this->adjustHolderAccessoryStock(
                                \App\Models\HolderAccessory::TYPE_TECHNICIAN,
                                $srcTechCode ?? $srcTechName,
                                $srcTechName,
                                $accCode,
                                $qty,
                                'decrement'
                            );
                        }

                        // Tambah ke holder tujuan jika teknisi lain
                        if ($holderType && $holderCode && ($holderType !== 'WAREHOUSE')) {
                            $this->adjustHolderAccessoryStock($holderType, $holderCode, $holderCleanName, $accCode, $qty, 'increment');
                        }

                        $this->logAccessoryTransaction($accCode, $qty, 'OUT', 'Teknisi: ' . $srcTechName, $holderName, $srcTechCode, $receiptNo);
                    }
                }
            } else {
                // Admin/PIC: kurangi dari stok gudang asal
                $this->processAccessoryQtyForm($request, 'OUT', $warehouseCode, 'Warehouse', $holderName, $technicianCode, $receiptNo, $holderType, $holderCode, $holderCleanName);
            }

            // Simpan data tanda terima untuk riwayat (checklist)
            DB::table('handover_receipts')->insert([
                'receipt_no'     => $receiptNo,
                'target_type'    => strtoupper($request->target_type),
                'target_name'    => $holderCleanName,
                'issuer_name'    => auth()->user()->name,
                'warehouse_code' => !$isTechUser ? ($warehouseCode ?? null) : null,
                'is_accepted'    => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        $this->dispatchStockUpdate();

        if ($hasEseal) {
            return redirect()->route('warranty')->with('success', 'Perangkat berhasil dilakukan serah terima.');
        }

        // Auto-generate tanda terima: arahkan langsung ke dokumen yang bisa dicetak / disimpan PDF.
        // (Dikembalikan ke tab yang sama agar tidak diblokir oleh Popup Blocker browser)
        return redirect()->route('receipt.show', ['receiptNo' => $receiptNo, 'autoprint' => 1]);
    }

    /**
     * API: Ambil riwayat serah terima berdasarkan filter tanggal & tipe tujuan.
     */
    public function apiGetHandoverHistory(Request $request)
    {
        $startDate  = $request->query('start_date', now()->format('Y-m-d'));
        $endDate    = $request->query('end_date',   now()->format('Y-m-d'));
        $targetType = $request->query('target_type', '');
        $user       = auth()->user();

        $query = DB::table('handover_receipts')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        // Teknisi & PIC: hanya tampilkan riwayat yang melibatkan mereka sendiri
        if ($user->hasRole('technician')) {
            $query->where(function($q) use ($user) {
                $q->where('target_name', $user->name)
                  ->orWhere('issuer_name', $user->name);
            });
        } elseif ($user->hasRole('pic') && $user->warehouse_code) {
            $query->where('warehouse_code', $user->warehouse_code);
        }

        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        $rows = $query->orderBy('created_at', 'desc')->get();

        return response()->json($rows);
    }

    /**
     * API: Tandai serah terima sebagai sudah diterima (checklist).
     */
    public function postMarkHandoverAccepted(Request $request)
    {
        $receiptNo = $request->input('receipt_no');
        if (!$receiptNo) {
            return response()->json(['success' => false, 'message' => 'Receipt No kosong.'], 422);
        }

        $updated = DB::table('handover_receipts')
            ->where('receipt_no', $receiptNo)
            ->update(['is_accepted' => true, 'accepted_at' => now(), 'updated_at' => now()]);

        return response()->json(['success' => (bool) $updated]);
    }

    // ==========================================
    // RETURN & INSPECTION METHODS
    // ==========================================

    public function returnDevice()
    {
        $devices     = Device::whereIn('status', ['ISSUED', 'IN_TRANSIT'])->get()->toArray();
        $accessories = Accessory::all()->keyBy('code')->toArray();

        // AI Suggestions
        $suggestedAccessories = $this->getAccessorySuggestions('RETURN');

        $technicians = Technician::orderBy('name')->get(['code', 'name', 'area']);
        $customers   = Customer::orderBy('name')->get(['id', 'name']);

        // SIM yang diserahkan mandiri (ISSUED/INSTALLED) & tidak terikat ke device — bisa direturn.
        $boundSimIds = Device::whereNotNull('gsm_simcard_id')->pluck('gsm_simcard_id')->all();
        $returnableSims = GsmSimcard::whereIn('status', ['ISSUED', 'INSTALLED'])
            ->when(!empty($boundSimIds), fn($q) => $q->whereNotIn('id', $boundSimIds))
            ->orderBy('provider')
            ->get(['id', 'msisdn', 'provider', 'category', 'status'])
            ->toArray();

        return view('return', compact('devices', 'accessories', 'suggestedAccessories', 'technicians', 'customers', 'returnableSims'));
    }

    public function apiGetReturnHistory(Request $request)
    {
        $startDate  = $request->query('start_date', now()->subMonths(3)->format('Y-m-d'));
        $endDate    = $request->query('end_date',   now()->format('Y-m-d'));

        // Ambil riwayat dari return_receipts
        $rows = DB::table('return_receipts')
            ->select('receipt_no', 'returner_name as operator', 'returned_by', 'warehouse_code', 'reason as notes', 'internal_note', 'created_at')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($rows);
    }

    public function postReturn(Request $request)
    {
        $request->validate([
            'sns'              => 'nullable|array',
            'warehouse'        => 'required|exists:warehouses,code',
            'return_reason'    => 'required|string',
        ]);

        // Harus ada minimal 1 device, 1 aksesoris, ATAU 1 kartu GSM
        $hasSns = $request->has('sns') && is_array($request->sns) && count($request->sns) > 0;
        $hasAcc = false;
        if ($request->has('acc_types') && $request->has('acc_qtys')) {
            foreach ($request->acc_qtys as $qty) {
                if (intval($qty) > 0) { $hasAcc = true; break; }
            }
        }
        $hasSim = $request->has('return_sim_ids') && count(array_filter((array) $request->return_sim_ids)) > 0;

        if (!$hasSns && !$hasAcc && !$hasSim) {
            return redirect()->back()->withErrors(['msg' => 'Harus ada minimal 1 perangkat, aksesoris, atau kartu GSM yang direturn.']);
        }

        // Tentukan asal pengembalian dari user yang login
        $user = auth()->user();
        $accFrom = $user->name;
        $accTechCode = $user->teknisi_code ?? null;
        $holderType = null;
        $holderCode = null;
        $holderCleanName = null;

        if ($user->role === 'technician' && $accTechCode) {
            $holderType = \App\Models\HolderAccessory::TYPE_TECHNICIAN;
            $holderCode = $accTechCode;
            $holderCleanName = $user->name;
        }

        $returnReason = $request->input('return_reason');
        $receiptNo = 'RET-' . date('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(4));

        DB::transaction(function () use ($request, $hasSns, $accFrom, $accTechCode, $holderType, $holderCode, $holderCleanName, $returnReason, $user, $receiptNo) {
            
            $operatorName = $user->name;
            if ($user->role === 'technician') {
                $whName = \App\Models\Warehouse::where('code', $request->warehouse)->value('name') ?? $request->warehouse;
                $operatorName = 'Admin / PIC Gudang ' . $whName;
            }

            DB::table('return_receipts')->insert([
                'receipt_no' => $receiptNo,
                'returner_name' => $operatorName,
                'warehouse_code' => $request->warehouse,
                'reason' => $returnReason,
                'returned_by' => $request->returned_by,
                'internal_note' => $request->internal_note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($hasSns) {
                $isCustomerReturn = str_starts_with($request->returned_by ?? '', 'Customer:');
                
                foreach ($request->sns as $sn) {
                    $device = Device::where('serial_number', $sn)->first();
                    if (!$device) continue;

                    $oldHolder = $device->current_holder;

                    // Unbind Customer Device if exists
                    $custDevice = CustomerDevice::where('device_id', $device->id)->whereNull('uninstalled_at')->first();
                    if ($custDevice) {
                        $custDevice->update(['uninstalled_at' => now()]);
                    }

                    // Lepas pairing kartu SIM: kembalikan SIM ke stok gudang penerima.
                    $simId = $device->gsm_simcard_id;
                    if ($simId) {
                        $sim = GsmSimcard::find($simId);
                        if ($sim) {
                            $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $request->warehouse]);
                            $this->logSimcardTransaction($sim, 'RETURNED', $oldHolder, 'Warehouse ' . $request->warehouse, $request->warehouse, $receiptNo);
                        }
                    }
                    
                    $deviceCondition = $device->unit_condition;
                    if ($isCustomerReturn) {
                        $deviceCondition = 'BEKAS';
                    } elseif ($returnReason === 'Cabut - Rusak') {
                        $deviceCondition = 'RUSAK';
                    }

                    $device->update([
                        'status'         => 'RETURNED',
                        'current_holder' => 'Warehouse ' . $request->warehouse,
                        'warehouse_code' => $request->warehouse,
                        'gsm_simcard_id' => null,
                        'unit_condition' => $deviceCondition,
                    ]);

                    $this->logDeviceTransaction($device, 'RETURNED', $oldHolder, $request->warehouse, $user->name, 'Web Form', $receiptNo);
                }
            }

            // Return kartu GSM mandiri (yang diserahkan tanpa device) kembali ke stok gudang.
            foreach ((array) $request->return_sim_ids as $simId) {
                if (!$simId) continue;
                $sim = GsmSimcard::where('id', $simId)->whereIn('status', ['ISSUED', 'INSTALLED'])->first();
                if (!$sim) continue;

                $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $request->warehouse]);
                $this->logSimcardTransaction($sim, 'RETURNED', $accFrom, 'Warehouse ' . $request->warehouse, $request->warehouse, $receiptNo);
            }

            // Aksesoris kembali ke gudang; saldo holder (teknisi/customer) berkurang
            // bila asal pengembalian dipilih.
            $this->processAccessoryQtyForm($request, 'RETURN', $request->warehouse, $accFrom, $request->warehouse, $accTechCode, $receiptNo, $holderType, $holderCode, $holderCleanName);
        });

        $this->dispatchStockUpdate();
        return redirect()->route('inspection')->with('success', "Perangkat/aksesoris berhasil direturn. Silakan lakukan QC/Inspection.");
    }

    public function inspection()
    {
        // Digabung ke menu Quality Control terpadu (tab Return).
        return redirect()->route('quality.control', ['tab' => 'return']);
    }

    public function postInspection(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:devices,id',
            'condition' => 'required|string',
            'qc_result' => 'required|in:PASSED,FAILED',
            'notes'     => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $device = Device::findOrFail($request->device_id);

            DeviceInspection::create([
                'device_id' => $device->id,
                'condition' => $request->condition,
                'notes'     => $request->notes,
                'qc_result' => $request->qc_result,
                'operator'  => 'QC Officer',
            ]);

            if ($request->qc_result === 'PASSED') {
                // Unit yang sudah pernah dipakai & lolos QC kembali sebagai stok BEKAS.
                $device->update(['status' => 'IN_STOCK', 'unit_condition' => 'BEKAS']);
                $this->logDeviceTransaction($device, 'QC_PASSED', 'QC Room', $device->warehouse_code, 'QC Officer', 'System');
            } else {
                $device->update(['status' => 'FLAGGED']);
                $this->logDeviceTransaction($device, 'QC_FAILED', 'QC Room', 'Flagged Storage', 'QC Officer', 'System');
            }
        });

        $this->dispatchStockUpdate();
        return redirect()->route('quality.control', ['tab' => 'return'])->with('success', 'Inspeksi perangkat berhasil disimpan.');
    }

    // ==========================================
    // QC PENERIMAAN (INCOMING QC) — oleh Tim RND
    // ==========================================

    /**
     * Halaman Quality Control terpadu (gabungan QC Penerimaan + QC Return/Inspeksi).
     * Tab: "incoming" (PENDING_QC), "return" (RETURNED/UNDER_QC), "report" (laporan QC).
     */
    public function qualityControl()
    {
        $warehouseCode = session('active_warehouse_code');
        $warehouseName = session('active_warehouse_name', $warehouseCode);

        // Tab 1 — QC Penerimaan (barang masuk), ter-scope gudang aktif.
        $incoming = Device::where('status', 'PENDING_QC')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->orderBy('model')
            ->orderBy('created_at')
            ->get();

        $models = $incoming->map(fn ($d) => $d->model ?: $d->type)
            ->filter()->unique()->sort()->values();

        // Tab 2 — QC Return / Inspeksi (perangkat balik dari lapangan).
        $returns = Device::whereIn('status', ['RETURNED', 'UNDER_QC'])
            ->with('inspections')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Tab 3 — Laporan QC.
        $qcReport = $this->qcReport(30);

        return view('quality_control', compact(
            'incoming', 'models', 'returns', 'warehouseCode', 'warehouseName', 'qcReport'
        ));
    }

    /**
     * Ringkasan & laporan QC (incoming + return) berbasis device_transactions.
     * Mengembalikan: antrian, throughput harian, reject rate, lead time, reject per model.
     */
    private function qcReport(int $days = 30): array
    {
        $since         = now()->subDays($days)->startOfDay();
        $warehouseCode = session('active_warehouse_code');

        // Antrian saat ini.
        $queueIncoming = (int) Device::where('status', 'PENDING_QC')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();
        $queueReturn = (int) Device::whereIn('status', ['RETURNED', 'UNDER_QC'])->count();

        // Transaksi QC dalam jendela waktu.
        $tx = DeviceTransaction::whereIn('action', ['QC_PASSED', 'QC_FAILED'])
            ->where('created_at', '>=', $since)
            ->get(['action', 'device_id', 'from_location', 'created_at']);

        $passed = $tx->where('action', 'QC_PASSED')->count();
        $failed = $tx->where('action', 'QC_FAILED')->count();
        $total  = $passed + $failed;
        $rejectRate = $total > 0 ? round($failed / $total * 100, 1) : 0.0;

        // Throughput per hari (jumlah unit selesai QC: pass + fail).
        $byDay = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $byDay[now()->subDays($i)->format('Y-m-d')] = 0;
        }
        foreach ($tx as $t) {
            $d = $t->created_at->format('Y-m-d');
            if (isset($byDay[$d])) {
                $byDay[$d]++;
            }
        }

        // Lead time barang masuk: RECEIVING → QC_PASSED (hanya QC Penerimaan).
        $leadHours = [];
        foreach ($tx->where('action', 'QC_PASSED')->where('from_location', 'QC Penerimaan') as $q) {
            $recv = DeviceTransaction::where('device_id', $q->device_id)
                ->where('action', 'RECEIVING')
                ->where('created_at', '<=', $q->created_at)
                ->latest('created_at')
                ->first(['created_at']);
            if ($recv) {
                $leadHours[] = $recv->created_at->diffInHours($q->created_at);
            }
        }
        $avgLeadHours = count($leadHours) > 0 ? round(array_sum($leadHours) / count($leadHours), 1) : null;

      // Reject per model (top 10).
$modelExpr = 'COALESCE(NULLIF(devices.model, ""), devices.type)';
$rejectByModel = DeviceTransaction::where('device_transactions.action', 'QC_FAILED')
    ->where('device_transactions.created_at', '>=', $since)
    ->join('devices', 'devices.id', '=', 'device_transactions.device_id')
    ->selectRaw($modelExpr . ' as model, count(*) as total')
    
    // BARIS DI BAWAH INI YANG DIUBAH:
    ->groupBy('devices.model', 'devices.type') 
    
    ->orderByDesc('total')
    ->limit(10)
    ->get();

        return [
            'days'           => $days,
            'queue_incoming' => $queueIncoming,
            'queue_return'   => $queueReturn,
            'passed'         => $passed,
            'failed'         => $failed,
            'total'          => $total,
            'reject_rate'    => $rejectRate,
            'avg_lead_hours' => $avgLeadHours,
            'throughput'     => $byDay,
            'reject_by_model' => $rejectByModel,
        ];
    }

    /**
     * Proses hasil QC penerimaan untuk satu/banyak unit (bulk).
     *  - OK            → IN_STOCK (siap dimutasi)
     *  - REJECT/RETEST → tetap PENDING_QC (uji ulang)
     *  - REJECT/RETUR_VENDOR → FLAGGED (karantina, retur ke vendor)
     *  - REJECT/DISPOSED → DISPOSED (dimusnahkan)
     */
    public function postQcIncoming(Request $request)
    {
        $request->validate([
            'device_ids'   => 'required|array|min:1',
            'device_ids.*' => 'exists:devices,id',
            'decision'     => 'required|in:OK,REJECT',
            'disposition'  => 'required_if:decision,REJECT|in:RETEST,RETUR_VENDOR,DISPOSED',
            'condition'    => 'nullable|in:BARU,BEKAS',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $warehouseCode = session('active_warehouse_code');
        $officer       = auth()->user()?->name ?? 'QC Officer';
        $processed     = 0;

        DB::transaction(function () use ($request, $warehouseCode, $officer, &$processed) {
            // Integritas: hanya unit PENDING_QC di gudang aktif yang boleh diproses.
            $devices = Device::whereIn('id', $request->device_ids)
                ->where('status', 'PENDING_QC')
                ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
                ->get();

            foreach ($devices as $device) {
                $notes = $request->notes;

                if ($request->decision === 'OK') {
                    $update = [
                        'status'   => 'IN_STOCK',
                        'qc_by'    => $officer,
                        'qc_at'    => now(),
                        'qc_notes' => $notes,
                    ];
                    if ($request->filled('condition')) {
                        $update['unit_condition'] = $request->condition;
                    }
                    $device->update($update);
                    $this->logDeviceTransaction($device, 'QC_PASSED', 'QC Penerimaan', $device->warehouse_code, $officer, 'System', $notes ?: 'QC OK');
                } else {
                    switch ($request->disposition) {
                        case 'RETEST':
                            $device->update(['status' => 'PENDING_QC', 'qc_by' => $officer, 'qc_at' => now(), 'qc_notes' => 'Re-test: ' . $notes]);
                            $this->logDeviceTransaction($device, 'QC_RETEST', 'QC Penerimaan', $device->warehouse_code, $officer, 'System', 'Re-test — ' . ($notes ?: 'perlu uji ulang'));
                            break;
                        case 'RETUR_VENDOR':
                            $device->update(['status' => 'FLAGGED', 'qc_by' => $officer, 'qc_at' => now(), 'qc_notes' => 'Retur Vendor: ' . $notes]);
                            $this->logDeviceTransaction($device, 'QC_FAILED', 'QC Penerimaan', 'Retur Vendor', $officer, 'System', 'Retur Vendor — ' . ($notes ?: 'reject QC'));
                            break;
                        case 'DISPOSED':
                            $device->update(['status' => 'DISPOSED', 'qc_by' => $officer, 'qc_at' => now(), 'qc_notes' => 'Disposed: ' . $notes]);
                            $this->logDeviceTransaction($device, 'QC_FAILED', 'QC Penerimaan', 'Disposed', $officer, 'System', 'Disposed — ' . ($notes ?: 'reject QC'));
                            break;
                    }
                }
                $processed++;
            }
        });

        $this->dispatchStockUpdate();

        $label = $request->decision === 'OK' ? 'lolos QC (IN_STOCK)' : 'ditandai reject';
        return redirect()->route('quality.control', ['tab' => 'incoming'])->with('success', "QC selesai: {$processed} unit {$label}.");
    }

    // ==========================================
    // FLAG & DISPOSE
    // ==========================================

    public function flagDevice($id)
    {
        $device = Device::findOrFail($id);
        $device->update(['status' => 'FLAGGED']);
        $this->dispatchStockUpdate();
        return redirect()->back()->with('success', 'Perangkat berhasil ditandai (FLAGGED).');
    }

    public function disposeDevice($id)
    {
        $device = Device::findOrFail($id);
        $device->update(['status' => 'DISPOSED', 'current_holder' => 'DISPOSED']);
        $this->logDeviceTransaction($device, 'DISPOSED', $device->warehouse_code, 'Disposal', 'Warehouse Manager', 'System');

        $this->dispatchStockUpdate();
        return redirect()->back()->with('success', 'Perangkat berhasil dihapus dari inventaris aktif (DISPOSED).');
    }

    // ==========================================
    // STOCK OPNAME WAREHOUSE
    // ==========================================

    /**
     * Tampilkan halaman utama Stock Opname Warehouse (daftar sesi).
     */
    public function stockOpname(Request $request)
    {
        $warehouses = Warehouse::pluck('name', 'code')->toArray();
        $selectedWh = $request->query('warehouse', session('active_warehouse_code'));
        if (!array_key_exists($selectedWh, $warehouses)) {
            $selectedWh = array_key_first($warehouses);
        }

        // Ambil sesi aktif dan riwayat sesi untuk gudang terpilih
        $sessions = StockOpnameSession::with('startedBy')
            ->where('warehouse_code', $selectedWh)
            ->latest()
            ->paginate(15);

        // Untuk tab "Koreksi Manual" (legacy)
        $accessories = Accessory::orderBy('name')->get();
        $whStock = WarehouseAccessory::where('warehouse_code', $selectedWh)->pluck('qty', 'accessory_code');
        $recentAdjustments = AccessoryTransaction::where('action', 'ADJUSTMENT')
            ->where('to_location', $selectedWh)
            ->latest()->take(10)->get();

        return view('stock_opname', compact('warehouses', 'selectedWh', 'sessions', 'accessories', 'whStock', 'recentAdjustments'));
    }

    /**
     * Mulai sesi opname baru untuk gudang tertentu.
     */
    public function startOpnameSession(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
            'opname_date' => 'required|date'
        ]);
        
        // Cek apakah ada sesi open untuk gudang ini
        $openSession = StockOpnameSession::where('warehouse_code', $request->warehouse_code)
            ->where('status', 'open')->first();
            
        if ($openSession) {
            return redirect()->route('stock.opname.session.show', $openSession->id)
                ->with('warning', 'Sesi opname sedang berjalan. Silakan lanjutkan sesi ini.');
        }

        $session = StockOpnameSession::create([
            'warehouse_code' => $request->warehouse_code,
            'opname_date' => $request->opname_date,
            'status' => 'open',
            'started_by' => $request->user()->id,
        ]);

        return redirect()->route('stock.opname.session.show', $session->id)
            ->with('success', 'Sesi Stock Opname berhasil dimulai.');
    }

    /**
     * Tampilkan form scan barcode untuk sesi aktif, atau hasil untuk sesi selesai.
     */
    public function showOpnameSession(Request $request, $id)
    {
        $session = StockOpnameSession::with(['startedBy', 'warehouse'])->findOrFail($id);
        $items = $session->items()->latest()->paginate(15);
        
        $deviceModels = \App\Models\DeviceModel::all()->groupBy('type');

        return view('stock_opname_session', compact('session', 'items', 'deviceModels'));
    }

    /**
     * Membatalkan sesi opname yang sedang berjalan.
     */
    public function cancelOpnameSession(Request $request, $id)
    {
        $session = StockOpnameSession::findOrFail($id);
        
        if (!$session->isOpen()) {
            return redirect()->back()->with('error', 'Hanya sesi yang masih berjalan yang dapat dibatalkan.');
        }

        // Hapus semua item terscan terkait sesi ini
        StockOpnameItem::where('session_id', $session->id)->delete();
        
        // Hapus sesi
        $session->delete();

        return redirect()->route('stock.opname')->with('success', 'Sesi Stock Opname berhasil dibatalkan dan dihapus.');
    }

    /**
     * API untuk resolve barcode lokasi atau item.
     */
    public function apiResolveOpnameBarcode(Request $request)
    {
        $barcode = $request->input('barcode');
        $reqType = $request->input('reqType');
        $reqModel = $request->input('reqModel');
        
        if (empty($barcode)) return response()->json(['success' => false]);

        // Coba cek lokasi (RAK-XX-ROW-XX)
        $parsed = WarehouseLocation::parseBarcode($barcode);
        if ($parsed) {
            return response()->json([
                'success' => true,
                'type' => 'location',
                'data' => [
                    'rack_code' => $parsed['rack'],
                    'row_code' => $parsed['row'],
                    'barcode' => $barcode
                ]
            ]);
        }

        // Jika user memaksa Tipe Alat (Dropdown aktif)
        if ($reqType === 'device' && $reqModel) {
            return response()->json([
                'success' => true,
                'type' => 'item',
                'item_type' => 'device',
                'item_code' => $barcode,
                'item_name' => $reqModel, // Simpan model di item_name
            ]);
        }

        // Coba cek Device (Serial Number)
        $device = Device::where('serial_number', $barcode)->first();
        if ($device) {
            return response()->json([
                'success' => true,
                'type' => 'item',
                'item_type' => 'device',
                'item_code' => $device->serial_number,
                'item_name' => ($device->model ?: $device->type) . ' (Device)',
            ]);
        }

        // Coba cek Accessory (Kode)
        $acc = Accessory::where('code', $barcode)->first();
        if ($acc) {
            return response()->json([
                'success' => true,
                'type' => 'item',
                'item_type' => 'accessory',
                'item_code' => $acc->code,
                'item_name' => $acc->name . ' (Aksesoris)',
            ]);
        }

        // Coba cek SIM Card (MSISDN)
        $sim = GsmSimcard::where('msisdn', $barcode)->first();
        if ($sim) {
            return response()->json([
                'success' => true,
                'type' => 'item',
                'item_type' => 'simcard',
                'item_code' => $sim->msisdn,
                'item_name' => $sim->provider . ' - ' . $sim->msisdn . ' (SIM)',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Barcode tidak dikenali.']);
    }

    /**
     * Simpan hasil scan ke sesi aktif.
     */
    public function postOpnameScan(Request $request, $id)
    {
        $session = StockOpnameSession::findOrFail($id);
        if (!$session->isOpen()) {
            return response()->json(['success' => false, 'message' => 'Sesi opname sudah selesai.']);
        }

        $request->validate([
            'location_barcode' => 'required|string',
            'rack_code' => 'required|string',
            'row_code' => 'required|string',
            'item_type' => 'required|in:device,accessory,simcard',
            'item_code' => 'required|string',
            'item_name' => 'nullable|string',
            'qty_physical' => 'required|integer|min:1',
            'unit' => 'nullable|string|max:100',
        ]);

        // Simpan lokasi jika belum ada
        WarehouseLocation::firstOrCreate(
            ['warehouse_code' => $session->warehouse_code, 'barcode' => $request->location_barcode],
            ['rack_code' => $request->rack_code, 'row_code' => $request->row_code]
        );

        // Jika barang tipe aksesoris, bisa digabung / diakumulasi dalam lokasi yang sama
        if ($request->item_type === 'accessory') {
            $existing = StockOpnameItem::where('session_id', $session->id)
                ->where('location_barcode', $request->location_barcode)
                ->where('item_type', 'accessory')
                ->where('item_code', $request->item_code)
                ->first();
                
            if ($existing) {
                $existing->increment('qty_physical', $request->qty_physical);
                if ($request->filled('unit')) {
                    $existing->update(['unit' => $request->unit]);
                }
                return response()->json([
                    'success' => true, 
                    'message' => 'Qty ditambahkan ke item yang sudah ada.',
                    'item' => $existing->fresh()
                ]);
            }
        } elseif (in_array($request->item_type, ['device', 'simcard'])) {
            // Device/SIM qty selalu 1 per record scan
            $existing = StockOpnameItem::where('session_id', $session->id)
                ->where('item_type', $request->item_type)
                ->where('item_code', $request->item_code)
                ->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'Item ini sudah discan di sesi ini.']);
            }
        }

        $item = StockOpnameItem::create([
            'session_id' => $session->id,
            'location_barcode' => $request->location_barcode,
            'rack_code' => $request->rack_code,
            'row_code' => $request->row_code,
            'item_type' => $request->item_type,
            'item_code' => $request->item_code,
            'item_name' => $request->item_name,
            'qty_physical' => $request->item_type === 'accessory' ? $request->qty_physical : 1,
            'unit' => $request->unit,
            'scanned_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil ditambahkan.',
            'item' => $item
        ]);
    }

    /**
     * Hapus item hasil scan.
     */
    public function deleteOpnameScan(Request $request, $id, $itemId)
    {
        $session = StockOpnameSession::findOrFail($id);
        if ($session->crosscheck_result && isset($session->crosscheck_result['applied']) && $session->crosscheck_result['applied']) {
            return response()->json(['success' => false, 'message' => 'Sesi sudah diterapkan ke sistem, data tidak bisa diubah.']);
        }

        $item = StockOpnameItem::where('session_id', $session->id)->findOrFail($itemId);
        $item->delete();

        // Regenerate if completed
        if ($session->status === 'completed') {
            $this->completeOpnameSession(request()->merge(['notes' => $session->notes]), $session->id, true);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Edit item hasil scan (Misal ubah SN karena typo).
     */
    public function updateOpnameScan(Request $request, $id, $itemId)
    {
        $session = StockOpnameSession::findOrFail($id);
        if ($session->crosscheck_result && isset($session->crosscheck_result['applied']) && $session->crosscheck_result['applied']) {
            return response()->json(['success' => false, 'message' => 'Sesi sudah diterapkan ke sistem, data tidak bisa diubah.']);
        }

        $request->validate([
            'item_code' => 'required|string',
        ]);

        $item = StockOpnameItem::where('session_id', $session->id)->findOrFail($itemId);
        
        // Cek duplicate
        $existing = StockOpnameItem::where('session_id', $session->id)
            ->where('item_type', $item->item_type)
            ->where('item_code', $request->item_code)
            ->where('id', '!=', $item->id)
            ->first();
            
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'SN ini sudah discan di data lain pada sesi ini.']);
        }

        $item->update(['item_code' => $request->item_code]);

        // Regenerate if completed
        if ($session->status === 'completed') {
            $this->completeOpnameSession(request()->merge(['notes' => $session->notes]), $session->id, true);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Selesaikan sesi opname dan hitung selisih.
     */
    public function completeOpnameSession(Request $request, $id, $isRegenerate = false)
    {
        $session = StockOpnameSession::with('items')->findOrFail($id);
        
        if (!$session->isOpen() && !$isRegenerate) {
            return redirect()->route('stock.opname.session.show', $session->id)
                ->with('warning', 'Sesi sudah selesai.');
        }

        $warehouseCode = $session->warehouse_code;
        $items = $session->items;

        $results = [
            'device' => [],
            'accessory' => [],
            'simcard' => []
        ];
        
        $stats = ['sesuai' => 0, 'selisih' => 0];

        // --- 1. Crosscheck Aksesoris ---
        $accPhysical = [];
        foreach ($items->where('item_type', 'accessory') as $item) {
            if (!isset($accPhysical[$item->item_code])) {
                $accPhysical[$item->item_code] = 0;
            }
            $accPhysical[$item->item_code] += $item->qty_physical;
        }

        // Ambil stok sistem untuk gudang ini
        $whAccs = WarehouseAccessory::with('accessory')
            ->where('warehouse_code', $warehouseCode)->get();
        
        // Cek selisih dari sisi sistem vs fisik
        foreach ($whAccs as $wa) {
            $code = $wa->accessory_code;
            $sysQty = $wa->qty;
            $physQty = $accPhysical[$code] ?? 0;
            $diff = $physQty - $sysQty;
            
            $results['accessory'][] = [
                'code' => $code,
                'name' => $wa->accessory->name ?? $code,
                'sys_qty' => $sysQty,
                'phys_qty' => $physQty,
                'diff' => $diff,
                'status' => $diff === 0 ? 'SESUAI' : 'SELISIH'
            ];
            
            if ($diff === 0) $stats['sesuai']++; else $stats['selisih']++;
            unset($accPhysical[$code]); // Sudah diproses
        }
        
        // Sisa di fisik tapi tak ada di sistem
        foreach ($accPhysical as $code => $physQty) {
            $acc = Accessory::find($code);
            $results['accessory'][] = [
                'code' => $code,
                'name' => $acc ? $acc->name : $code,
                'sys_qty' => 0,
                'phys_qty' => $physQty,
                'diff' => $physQty,
                'status' => 'SELISIH'
            ];
            $stats['selisih']++;
        }


        // --- 2. Crosscheck Device (Summary per Model + Hidden Detailed SN) ---
        $devicePhysical = $items->where('item_type', 'device')->pluck('item_code')->toArray();
        $whDevices = Device::with('deviceModel')->where('warehouse_code', $warehouseCode)->where('status', 'IN_STOCK')->get();
        
        $sysDevicesMap = [];
        $deviceSystemCount = [];
        foreach ($whDevices as $d) {
            $sysDevicesMap[$d->serial_number] = $d;
            $modelName = $d->deviceModel ? $d->deviceModel->model : ($d->model ?: $d->type);
            if (!isset($deviceSystemCount[$modelName])) $deviceSystemCount[$modelName] = 0;
            $deviceSystemCount[$modelName]++;
        }

        $devicePhysicalCount = [];
        $detailedDeviceDiff = []; // Disimpan untuk Terapkan Koreksi

        foreach ($items->where('item_type', 'device') as $item) {
            $sn = $item->item_code;
            $modelName = $item->item_name ?: 'Unknown Model';
            
            if (!isset($devicePhysicalCount[$modelName])) $devicePhysicalCount[$modelName] = 0;
            $devicePhysicalCount[$modelName] += $item->qty_physical;

            // Track detailed SN for apply logic
            if (isset($sysDevicesMap[$sn])) {
                // Sesuai
                unset($sysDevicesMap[$sn]);
            } else {
                // Di fisik ada, di sistem ga ada (Nyasar) — simpan juga lokasi rak
                $detailedDeviceDiff[] = [
                    'code'     => $sn, 
                    'diff'     => 1, 
                    'name'     => $modelName, 
                    'item_id'  => $item->id,
                    'rack_code' => $item->rack_code,
                    'row_code'  => $item->row_code,
                    'location_barcode' => $item->location_barcode,
                ];
            }
        }
        
        // Sisa sysDevicesMap adalah yang hilang
        foreach ($sysDevicesMap as $sn => $d) {
            $modelName = $d->deviceModel ? $d->deviceModel->model : ($d->model ?: $d->type);
            $detailedDeviceDiff[] = [
                'code'     => $sn, 
                'diff'     => -1, 
                'name'     => $modelName,
                'item_id'  => null,
                'rack_code' => null,
                'row_code'  => null,
                'location_barcode' => null,
            ];
        }

        // Tampilkan Summary per Model ke UI
        $allModels = array_unique(array_merge(array_keys($devicePhysicalCount), array_keys($deviceSystemCount)));
        foreach ($allModels as $modelName) {
            $sysQty = $deviceSystemCount[$modelName] ?? 0;
            $physQty = $devicePhysicalCount[$modelName] ?? 0;
            $diff = $physQty - $sysQty;

            $results['device'][] = [
                'code' => $modelName,
                'name' => 'Device Model',
                'sys_qty' => $sysQty,
                'phys_qty' => $physQty,
                'diff' => $diff,
                'status' => $diff === 0 ? 'SESUAI' : 'SELISIH'
            ];
            
            if ($diff === 0) $stats['sesuai']++; else $stats['selisih']++;
        }
        


        // --- 3. Crosscheck SIM Card ---
        $simPhysical = $items->where('item_type', 'simcard')->pluck('item_code')->toArray();
        $whSims = GsmSimcard::where('warehouse_code', $warehouseCode)->where('status', 'IN_STOCK')->get();
        
        $sysSimsMap = [];
        foreach ($whSims as $s) {
            $sysSimsMap[$s->msisdn] = $s;
        }

        foreach ($simPhysical as $msisdn) {
            if (isset($sysSimsMap[$msisdn])) {
                $s = $sysSimsMap[$msisdn];
                $results['simcard'][] = [
                    'code' => $msisdn,
                    'name' => $s->provider,
                    'sys_qty' => 1,
                    'phys_qty' => 1,
                    'diff' => 0,
                    'status' => 'SESUAI'
                ];
                $stats['sesuai']++;
                unset($sysSimsMap[$msisdn]);
            } else {
                $s = GsmSimcard::where('msisdn', $msisdn)->first();
                $results['simcard'][] = [
                    'code' => $msisdn,
                    'name' => $s ? $s->provider : 'Unknown SIM',
                    'sys_qty' => 0,
                    'phys_qty' => 1,
                    'diff' => 1,
                    'status' => 'SELISIH (Nyasar)'
                ];
                $stats['selisih']++;
            }
        }
        
        foreach ($sysSimsMap as $msisdn => $s) {
            $results['simcard'][] = [
                'code' => $msisdn,
                'name' => $s->provider,
                'sys_qty' => 1,
                'phys_qty' => 0,
                'diff' => -1,
                'status' => 'SELISIH (Hilang)'
            ];
            $stats['selisih']++;
        }

        // Simpan hasil ke session
        $session->update([
            'status' => 'completed',
            'completed_at' => $session->completed_at ?? now(),
            'notes' => $request->notes ?? $session->notes,
            'crosscheck_result' => [
                'details' => $results,
                'stats' => $stats,
                'hidden_device_diff' => $detailedDeviceDiff // Simpan SN aktual yang hilang/berlebih untuk apply
            ]
        ]);

        if ($isRegenerate) {
            return true; // Return silent for internal regenerate call
        }

        return redirect()->route('stock.opname.session.show', $session->id)
            ->with('success', 'Sesi opname selesai. Hasil crosscheck telah di-generate.');
    }

    /**
     * Terapkan hasil opname (Adjustment otomatis).
     */
    public function applyOpnameSession(Request $request, $id)
    {
        $session = StockOpnameSession::findOrFail($id);
        if ($session->status !== 'completed' || empty($session->crosscheck_result)) {
            return redirect()->back()->withErrors(['msg' => 'Hasil opname tidak valid atau belum diselesaikan.']);
        }
        
        $results = $session->crosscheck_result['details'] ?? [];
        $wh = $session->warehouse_code;
        $adminName = $request->user()->name;

        DB::transaction(function () use ($results, $wh, $adminName, $session) {
            // Apply Accessories
            foreach ($results['accessory'] as $r) {
                if ($r['diff'] == 0) continue;
                $record = WarehouseAccessory::firstOrCreate(
                    ['warehouse_code' => $wh, 'accessory_code' => $r['code']],
                    ['qty' => 0]
                );
                $oldQty = $record->qty;
                $record->update(['qty' => $r['phys_qty']]);
                $this->syncAccessoryGlobalQty($r['code']);
                
                AccessoryTransaction::create([
                    'accessory_code' => $r['code'],
                    'qty'            => $r['diff'],
                    'action'         => 'ADJUSTMENT',
                    'from_location'  => "Opname #{$session->id}",
                    'to_location'    => $wh,
                    'notes'          => "Koreksi opname (Sistem: $oldQty, Fisik: {$r['phys_qty']})",
                ]);
            }

            // Apply Devices (Lost or Found) menggunakan detailed SN diff yang tersimpan
            $detailedDeviceDiff = $session->crosscheck_result['hidden_device_diff'] ?? [];
            foreach ($detailedDeviceDiff as $r) {
                if ($r['diff'] == 0) continue;
                $device = Device::where('serial_number', $r['code'])->first();
                if (!$device) continue;
                
                $from = "{$device->status} @ {$device->warehouse_code} ({$device->current_holder})";
                
                if ($r['diff'] == -1) {
                    // Hilang
                    $device->update(['status' => 'LOST', 'current_holder' => 'SYSTEM']);
                    $to = "LOST @ {$device->warehouse_code} (SYSTEM)";
                    $reason = "Hilang";
                } else if ($r['diff'] == 1) {
                    // Ketemu/Nyasar
                    $device->update(['status' => 'IN_STOCK', 'warehouse_code' => $wh, 'current_holder' => "Warehouse $wh"]);
                    $to = "IN_STOCK @ $wh (Warehouse $wh)";
                    $reason = "Ditemukan/Nyasar";
                }
                
                $this->logDeviceTransaction($device, 'ADJUSTMENT', $from, $to, $adminName, 'System', "Koreksi opname #{$session->id}: {$reason}");
            }

            // Apply SIM Cards (Lost or Found)
            foreach ($results['simcard'] as $r) {
                if ($r['diff'] == 0) continue;
                $sim = GsmSimcard::where('msisdn', $r['code'])->first();
                if (!$sim) continue;
                
                if ($r['diff'] == -1) {
                    $sim->update(['status' => 'LOST', 'warehouse_code' => null]);
                    $this->logSimcardTransaction($sim, 'ADJUSTMENT', $wh, 'LOST', null, "Opname #{$session->id}: Hilang");
                } else if ($r['diff'] == 1) {
                    $oldWh = $sim->warehouse_code;
                    $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $wh]);
                    $this->logSimcardTransaction($sim, 'ADJUSTMENT', $oldWh ?? 'Unknown', $wh, $wh, "Opname #{$session->id}: Ditemukan di gudang ini");
                }
            }
            
            // Tandai sudah di-apply
            $res = $session->crosscheck_result;
            $res['applied'] = true;
            $res['applied_at'] = now()->toIso8601String();
            $session->update(['crosscheck_result' => $res]);
        });

        $this->dispatchStockUpdate();
        return redirect()->route('stock.opname.session.show', $session->id)->with('success', 'Semua selisih telah dikoreksi di sistem secara otomatis.');
    }

    /**
     * Apply a stock-take: set per-warehouse qty to the physical count, keep the
     * global accessory qty in sync via the same delta, and log an ADJUSTMENT.
     */
    public function postStockOpname(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
            'counts'         => 'required|array',
            'reason'         => 'required|string|max:500',
        ]);

        $wh = $request->warehouse_code;
        $changed = 0;

        DB::transaction(function () use ($request, $wh, &$changed) {
            foreach ($request->counts as $accCode => $newQtyRaw) {
                if ($newQtyRaw === null || $newQtyRaw === '') continue;

                $acc = Accessory::find($accCode);
                if (!$acc) continue;

                $newQty = max(0, intval($newQtyRaw));

                $record = WarehouseAccessory::firstOrCreate(
                    ['warehouse_code' => $wh, 'accessory_code' => $accCode],
                    ['qty' => 0]
                );
                $oldQty = (int) $record->qty;
                $delta  = $newQty - $oldQty;
                if ($delta === 0) continue;

                // Per-warehouse stock = physical count
                $record->update(['qty' => $newQty]);

                // Rekonsiliasi qty global dari total seluruh gudang.
                $this->syncAccessoryGlobalQty($accCode);

                // Audit trail
                AccessoryTransaction::create([
                    'accessory_code' => $accCode,
                    'qty'            => $delta, // signed delta
                    'action'         => 'ADJUSTMENT',
                    'from_location'  => 'Stock Opname',
                    'to_location'    => $wh,
                    'technician_code' => null,
                    'notes'          => "Opname {$oldQty} -> {$newQty} (delta {$delta}). Alasan: {$request->reason}",
                ]);

                $changed++;
            }
        });

        $this->dispatchStockUpdate();

        $msg = $changed > 0
            ? "Stock opname tersimpan. {$changed} item disesuaikan & tercatat di audit trail."
            : "Tidak ada perubahan qty yang terdeteksi.";

        return redirect()->route('stock.opname', ['warehouse' => $wh])->with('success', $msg);
    }


    /**
     * Manually correct a single device's status / location / holder, with a
     * mandatory reason, recorded as an ADJUSTMENT in the audit trail.
     */
    public function postAdjustDevice(Request $request)
    {
        $validated = $request->validate([
            'device_id'      => 'required|exists:devices,id',
            'status'         => 'required|in:IN_STOCK,PENDING_QC,IN_TRANSIT,ISSUED,INSTALLED,RETURNED,UNDER_QC,FLAGGED,LOST,DISPOSED',
            'warehouse_code' => 'required|exists:warehouses,code',
            'current_holder' => 'required|string|max:255',
            'reason'         => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($validated) {
            $device = Device::findOrFail($validated['device_id']);

            $from = "{$device->status} @ {$device->warehouse_code} ({$device->current_holder})";

            $device->update([
                'status'         => $validated['status'],
                'warehouse_code' => $validated['warehouse_code'],
                'current_holder' => $validated['current_holder'],
            ]);

            $to = "{$validated['status']} @ {$validated['warehouse_code']} ({$validated['current_holder']})";

            $this->logDeviceTransaction($device, 'ADJUSTMENT', $from, $to, 'Admin Gudang', 'Manual', $validated['reason']);
        });

        $this->dispatchStockUpdate();
        return redirect()->back()->with('success', 'Koreksi unit perangkat berhasil disimpan & tercatat di audit trail.');
    }

    // ==========================================
    // SEARCH
    // ==========================================

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        $results = [];
        $audit_trails = [];
        $gsm_results = [];
        $accessory_results = [];
        $warning = null;
        $notInWarehouse = false; 

        $activeWarehouseCode = session('active_warehouse_code');
        $activeWarehouseName = session('active_warehouse_name', $activeWarehouseCode);
        $isGlobal = empty($activeWarehouseCode) || $activeWarehouseCode === '__global__'
                    || str_starts_with((string) $activeWarehouseCode, '__region_');

        $qClean = trim($q);

        if (!empty($qClean)) {
            $qArray = preg_split('/[\s,]+/', $qClean, -1, PREG_SPLIT_NO_EMPTY);

            if (count($qArray) > 1) {
                $resultsAll = Device::where(function ($query) use ($qArray) {
                    foreach ($qArray as $term) {
                        $query->orWhere('serial_number', 'like', "%{$term}%")
                              ->orWhere('imei', 'like', "%{$term}%");
                    }
                })->get();

                if (!$isGlobal && $activeWarehouseCode) {
                    $filtered = $resultsAll->filter(fn($d) => $d->warehouse_code === $activeWarehouseCode);
                    if ($resultsAll->isNotEmpty() && $filtered->isEmpty()) {
                        $notInWarehouse = true;
                    }
                    $results = $filtered->toArray();
                } else {
                    $results = $resultsAll->toArray();
                }

                $gsm_results = GsmSimcard::where(function ($query) use ($qArray) {
                    foreach ($qArray as $term) {
                        $query->orWhere('msisdn', 'like', "%{$term}%")
                              ->orWhere('provider', 'like', "%{$term}%");
                    }
                })->when(!$isGlobal && $activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))
                  ->orderBy('provider')->get()->toArray();

            } else {
                $searchTerm = $qArray[0];
                if (strlen($searchTerm) < 3) {
                    $warning = 'Kata kunci pencarian terlalu pendek. Silakan masukkan minimal 3 karakter untuk pencarian cepat.';
                } else {
                    $resultsAll = Device::where('serial_number', 'like', "%{$searchTerm}%")
                        ->orWhere('imei', 'like', "%{$searchTerm}%")
                        ->orWhere('status', 'like', "%{$searchTerm}%")
                        ->orWhere('type', 'like', "%{$searchTerm}%")
                        ->orWhere('warehouse_code', 'like', "%{$searchTerm}%")
                        ->orWhere('current_holder', 'like', "%{$searchTerm}%")
                        ->get();

                    if (!$isGlobal && $activeWarehouseCode) {
                        $filtered = $resultsAll->filter(fn($d) => $d->warehouse_code === $activeWarehouseCode);
                        if ($resultsAll->isNotEmpty() && $filtered->isEmpty()) {
                            $notInWarehouse = true;
                        }
                        $results = $filtered->toArray();
                    } else {
                        $results = $resultsAll->toArray();
                    }

                    $gsm_results = GsmSimcard::where('msisdn', 'like', "%{$searchTerm}%")
                        ->orWhere('provider', 'like', "%{$searchTerm}%")
                        ->orWhere('category', 'like', "%{$searchTerm}%")
                        ->orWhere('status', 'like', "%{$searchTerm}%")
                        ->orWhere('warehouse_code', 'like', "%{$searchTerm}%")
                        ->when(!$isGlobal && $activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))
                        ->orderBy('provider')
                        ->get()->toArray();

                    $accQuery = \App\Models\WarehouseAccessory::with('accessory')
                        ->whereHas('accessory', fn($q) => $q->where('code', 'like', "%{$searchTerm}%")
                            ->orWhere('name', 'like', "%{$searchTerm}%"))
                        ->orWhere('accessory_code', 'like', "%{$searchTerm}%");

                    if (!$isGlobal && $activeWarehouseCode) {
                        $accQuery->where('warehouse_code', $activeWarehouseCode);
                    }
                    $whAccs = $accQuery->get();

                    $holderAccQuery = \App\Models\HolderAccessory::with('accessory')
                        ->whereHas('accessory', fn($q) => $q->where('code', 'like', "%{$searchTerm}%")
                            ->orWhere('name', 'like', "%{$searchTerm}%"))
                        ->orWhere('accessory_code', 'like', "%{$searchTerm}%")
                        ->orWhere('holder_code', 'like', "%{$searchTerm}%")
                        ->orWhere('holder_name', 'like', "%{$searchTerm}%");

                    $holderAccs = $holderAccQuery->get();

                    foreach ($whAccs as $wa) {
                        $accessory_results[] = [
                            'code' => $wa->accessory_code,
                            'name' => $wa->accessory ? $wa->accessory->name : '-',
                            'qty'  => $wa->qty,
                            'warehouse_code' => $wa->warehouse_code,
                            'location' => 'Di Gudang',
                        ];
                    }
                    foreach ($holderAccs as $ha) {
                        $loc = $ha->holder_type . ': ' . $ha->holder_code . ($ha->holder_name ? ' (' . $ha->holder_name . ')' : '');
                        $accessory_results[] = [
                            'code' => $ha->accessory_code,
                            'name' => $ha->accessory ? $ha->accessory->name : '-',
                            'qty'  => $ha->qty,
                            'warehouse_code' => '-',
                            'location' => $loc,
                        ];
                    }
                }
            }

            if (!empty($results)) {
                $gsm_results = [];
                $accessory_results = [];

                $resultSns = collect($results)->pluck('serial_number')->toArray();
                $audit_trails = \App\Models\DeviceTransaction::whereIn('device_sn', $resultSns)
                    ->orWhere(function($query) use ($qArray) {
                        if (count($qArray) === 1) {
                            $query->where('device_sn', 'like', "%{$qArray[0]}%");
                        }
                    })
                    ->latest()->get()->map(fn($tx) => [
                        'id'         => $tx->id,
                        'device_sn'  => $tx->device_sn,
                        'action'     => $tx->action,
                        'from'       => $tx->from_location,
                        'to'         => $tx->to_location,
                        'operator'   => $tx->operator,
                        'scanned_by' => $tx->scanned_by,
                        'via'        => $tx->via_web ? 'Web App' : 'API',
                        'notes'      => $tx->notes,
                        'timestamp'  => $tx->created_at->format('Y-m-d H:i:s'),
                    ])->toArray();
            } elseif (!empty($gsm_results)) {
                $results = [];
                $accessory_results = [];

                $resultMsisdns = collect($gsm_results)->pluck('msisdn')->toArray();
                $audit_trails = \App\Models\SimcardTransaction::whereIn('msisdn', $resultMsisdns)
                    ->latest()->get()->map(fn($tx) => [
                        'id'         => $tx->id,
                        'device_sn'  => $tx->msisdn,
                        'action'     => $tx->action,
                        'from'       => $tx->from_location ?? '-',
                        'to'         => $tx->to_location ?? '-',
                        'operator'   => $tx->operator ?? '-',
                        'scanned_by' => '-',
                        'via'        => '-',
                        'notes'      => $tx->notes,
                        'timestamp'  => $tx->created_at->format('Y-m-d H:i:s'),
                    ])->toArray();
            } elseif (!empty($accessory_results)) {
                $results = [];
                $gsm_results = [];

                $resultAccCodes = collect($accessory_results)->pluck('code')->toArray();
                $audit_trails = \App\Models\AccessoryTransaction::whereIn('accessory_code', $resultAccCodes)
                    ->latest()->get()->map(fn($tx) => [
                        'id'         => $tx->id,
                        'device_sn'  => $tx->accessory_code . ' (Qty: '.$tx->qty.')',
                        'action'     => $tx->action,
                        'from'       => $tx->from_location ?? '-',
                        'to'         => $tx->to_location ?? '-',
                        'operator'   => $tx->technician_code ?? '-',
                        'scanned_by' => '-',
                        'via'        => '-',
                        'notes'      => $tx->notes,
                        'timestamp'  => $tx->created_at->format('Y-m-d H:i:s'),
                    ])->toArray();
            }
        } else {
            $gsm_results = GsmSimcard::when(!$isGlobal && $activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))
                ->orderBy('provider')->get()->toArray();

            $accQuery = \App\Models\WarehouseAccessory::with('accessory');
            if (!$isGlobal && $activeWarehouseCode) {
                $accQuery->where('warehouse_code', $activeWarehouseCode);
            }
            $accessory_results = $accQuery->orderBy('warehouse_code')->get()->map(fn($wa) => [
                'code'           => $wa->accessory_code,
                'name'           => $wa->accessory->name ?? $wa->accessory_code,
                'qty'            => (int) $wa->qty,
                'warehouse_code' => $wa->warehouse_code,
                'location'       => 'Gudang',
            ])->toArray();
        }

        $warehouses = Warehouse::pluck('name', 'code')->toArray();

        return response()
            ->view('search', compact('results', 'audit_trails', 'gsm_results', 'accessory_results', 'q', 'warning', 'warehouses', 'notInWarehouse', 'activeWarehouseName', 'isGlobal'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    // ==========================================
    // MASTER DATA CRUD
    // ==========================================

    public function masterData()
    {
        $activeWarehouseCode = session('active_warehouse_code');
        $activeWarehouseName = session('active_warehouse_name', $activeWarehouseCode);

        $warehouses   = Warehouse::all()->toArray();
        $technicians  = Technician::all()->toArray();
        $deviceModels = DeviceModel::all()->toArray();
        $customers    = Customer::all()->toArray();

        $racks = \App\Models\WarehouseLocation::when($activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))->get();
        $devicesInRack = \App\Models\Device::whereNotNull('rack_barcode')
            ->when($activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))
            ->get(['serial_number', 'model', 'rack_barcode', 'status', 'warehouse_code', 'unit_condition']);

        // Aksesoris: jika ada gudang aktif di session, tampilkan qty per gudang tersebut.
        // Super Admin tanpa session gudang (mode global) tetap melihat qty global.
        if ($activeWarehouseCode) {
            // Ambil semua aksesoris, lalu gabungkan dengan stok per gudang aktif.
            $warehouseAccMap = WarehouseAccessory::where('warehouse_code', $activeWarehouseCode)
                ->pluck('qty', 'accessory_code')
                ->toArray();

            $accessories = Accessory::all()->map(function ($acc) use ($warehouseAccMap) {
                $arr = $acc->toArray();
                // Override qty global dengan qty gudang aktif.
                $arr['qty'] = (int) ($warehouseAccMap[$acc->code] ?? 0);
                return $arr;
            })->toArray();
        } else {
            // Mode global: qty = total semua gudang (sudah tersimpan di accessories.qty).
            $accessories = Accessory::all()->toArray();
        }

        // GSM SIM Cards Master Data: tidak scope ke gudang, ini adalah tabel master.
        $simcards = \App\Models\SimcardMaster::orderBy('provider')->get()->toArray();

        // Ambil data threshold dan kelompokkan per warehouse/teknisi
        $thresholds = \App\Models\StockAlertThreshold::where('item_type', '!=', 'TECH_LIMIT')
            ->get()->groupBy('warehouse_code');
            
        $technicianLimits = \App\Models\StockAlertThreshold::where('item_type', 'TECH_LIMIT')
            ->get()->groupBy('warehouse_code');

        return view('master_data', compact(
            'warehouses', 'technicians', 'accessories', 'simcards',
            'deviceModels', 'customers', 'thresholds', 'technicianLimits',
            'activeWarehouseCode', 'activeWarehouseName', 'racks', 'devicesInRack'
        ));
    }

    public function storeWarehouse(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'name' => 'required|string',
            'type' => 'required|string',
            'region' => 'nullable|string|in:EAST,WEST',
        ]);
        Warehouse::updateOrCreate(
            ['code' => $request->code],
            [
                'name' => $request->name,
                'type' => $request->type,
                'region' => $request->region ?: null,
            ]
        );
        return redirect()->route('master_data', ['tab' => $request->input('tab', 'warehouse')])->with('success', 'Gudang berhasil disimpan.');
    }

    public function deleteWarehouse($code)
    {
        Warehouse::where('code', $code)->delete();
        return redirect()->route('master_data', ['tab' => 'warehouse'])->with('success', 'Gudang berhasil dihapus.');
    }

    public function storeRack(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|string',
            'rack_code' => 'nullable|string',
            'row_code' => 'nullable|string',
            'barcode' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!str_contains($value, 'WS')) {
                    $fail('Barcode wajib mengandung kode "WS" (contoh: WS-RAK-01-ROW-01 atau WS-CONT-01).');
                }
            }],
            'description' => 'nullable|string',
        ]);
        \App\Models\WarehouseLocation::updateOrCreate(
            ['barcode' => $request->barcode],
            [
                'warehouse_code' => $request->warehouse_code,
                'rack_code' => $request->rack_code ?? '-',
                'row_code' => $request->row_code ?? '-',
                'description' => $request->description,
            ]
        );
        return redirect()->route('master_data', ['tab' => 'rack'])->with('success', 'Rak Penyimpanan berhasil disimpan.');
    }

    public function deleteRack($id)
    {
        \App\Models\WarehouseLocation::findOrFail($id)->delete();
        return redirect()->route('master_data', ['tab' => 'rack'])->with('success', 'Rak Penyimpanan berhasil dihapus.');
    }

    public function exportRacks(Request $request)
    {
        $activeWarehouseCode = session('active_warehouse_code');
        $racks = \App\Models\WarehouseLocation::when($activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))->get();
        $devices = \App\Models\Device::whereNotNull('rack_barcode')
            ->when($activeWarehouseCode, fn($q) => $q->where('warehouse_code', $activeWarehouseCode))
            ->get(['serial_number', 'model', 'rack_barcode', 'status', 'warehouse_code', 'unit_condition']);
            
        // Convert to CSV
        $filename = "Export_Data_Rak_Penyimpanan_" . date('Ymd_His') . ".csv";
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];
        
        $callback = function() use($racks, $devices) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Tipe Data', 'Warehouse Code', 'Rack Code', 'Row Code', 'Barcode', 'Description', 'Serial Number', 'Model', 'Status', 'Condition']);
            
            foreach ($racks as $rack) {
                fputcsv($file, ['RACK', $rack->warehouse_code, $rack->rack_code, $rack->row_code, $rack->barcode, $rack->description, '', '', '', '']);
            }
            foreach ($devices as $dev) {
                fputcsv($file, ['DEVICE', $dev->warehouse_code, '', '', $dev->rack_barcode, '', $dev->serial_number, $dev->model, $dev->status, $dev->unit_condition]);
            }
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Update warehouse — termasuk cascade sync ke semua tabel yang mereferensikan
     * warehouse_code jika kode gudang berubah.
     */
    public function updateWarehouse(Request $request, $oldCode)
    {
        $request->validate([
            'code'   => 'required|string|max:50',
            'name'   => 'required|string|max:100',
            'type'   => 'required|string',
            'region' => 'nullable|string|in:EAST,WEST',
        ]);

        $newCode = trim($request->code);
        $newName = trim($request->name);

        $warehouse = Warehouse::where('code', $oldCode)->firstOrFail();

        \DB::transaction(function () use ($warehouse, $oldCode, $newCode, $newName, $request) {
            // 1. Jika kode berubah — cascade update ke semua tabel terkait
            if ($oldCode !== $newCode) {
                // Pastikan kode baru belum dipakai
                if (Warehouse::where('code', $newCode)->exists()) {
                    abort(422, "Kode gudang '{$newCode}' sudah digunakan oleh gudang lain.");
                }

                // Cascade update: devices
                \App\Models\Device::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);

                // Cascade update: device_transactions (from & to)
                \App\Models\DeviceTransaction::where('from_warehouse_code', $oldCode)
                    ->update(['from_warehouse_code' => $newCode]);
                \App\Models\DeviceTransaction::where('to_warehouse_code', $oldCode)
                    ->update(['to_warehouse_code' => $newCode]);

                // Cascade update: warehouse_accessories
                \App\Models\WarehouseAccessory::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);

                // Cascade update: gsm_simcards
                \App\Models\GsmSimcard::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);

                // Cascade update: stock_alert_thresholds
                \App\Models\StockAlertThreshold::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);

                // Cascade update: users
                \App\Models\User::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);

                // Cascade update: technicians
                \App\Models\Technician::where('warehouse_code', $oldCode)
                    ->update(['warehouse_code' => $newCode]);
            }

            // 2. Update warehouse record (kode baru + nama + tipe + region)
            $warehouse->update([
                'code'   => $newCode,
                'name'   => $newName,
                'type'   => $request->type,
                'region' => $request->region ?: null,
            ]);
        });

        return redirect()
            ->route('master_data', ['tab' => 'warehouse'])
            ->with('success', "Gudang berhasil diperbarui." . ($oldCode !== $newCode ? " Kode diubah dari '{$oldCode}' → '{$newCode}' dan semua data terkait telah disinkronkan." : ''));
    }

    public function storeWarehouseThreshold(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|string',
            'item_type' => 'required|string',
            'item_identifier' => 'required|string',
            'min_stock_level' => 'required|integer|min:0'
        ]);

        \App\Models\StockAlertThreshold::updateOrCreate(
            [
                'warehouse_code' => $request->warehouse_code,
                'item_type' => $request->item_type,
                'item_identifier' => $request->item_identifier
            ],
            ['min_stock_level' => $request->min_stock_level]
        );

        return redirect()->route('master_data', ['tab' => 'warehouse'])->with('success', 'Batas minimum stok gudang berhasil disimpan.');
    }

    public function deleteWarehouseThreshold($id)
    {
        \App\Models\StockAlertThreshold::findOrFail($id)->delete();
        return redirect()->route('master_data', ['tab' => 'warehouse'])->with('success', 'Batas minimum stok gudang berhasil dihapus.');
    }

    public function storeTechnician(Request $request)
    {
        $request->validate(['code' => 'required|string', 'name' => 'required|string', 'area' => 'nullable|string']);
        Technician::updateOrCreate(['code' => $request->code], ['name' => $request->name, 'area' => $request->area]);
        return redirect()->route('master_data', ['tab' => $request->input('tab', 'technician')])->with('success', 'Teknisi berhasil disimpan.');
    }

    public function deleteTechnician($code)
    {
        Technician::where('code', $code)->delete();
        return redirect()->route('master_data', ['tab' => 'technician'])->with('success', 'Teknisi berhasil dihapus.');
    }

    public function storeTechnicianLimit(Request $request)
    {
        $request->validate([
            'technician_code' => 'required|string',
            'category' => 'required|string',
            'min_required' => 'required|integer|min:0'
        ]);

        \App\Models\StockAlertThreshold::updateOrCreate(
            [
                'warehouse_code' => $request->technician_code,
                'item_type' => 'TECH_LIMIT',
                'item_identifier' => $request->category
            ],
            ['min_stock_level' => $request->min_required]
        );

        return redirect()->route('master_data', ['tab' => 'technician'])->with('success', 'Batas minimal perangkat teknisi berhasil disimpan.');
    }

    public function deleteTechnicianLimit($id)
    {
        \App\Models\StockAlertThreshold::findOrFail($id)->delete();
        return redirect()->route('master_data', ['tab' => 'technician'])->with('success', 'Batas minimal perangkat teknisi berhasil dihapus.');
    }

    public function storeCustomer(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'phone'       => 'nullable|string',
            'address'     => 'nullable|string',
            'pic_name'    => 'nullable|string',
        ]);

        Customer::updateOrCreate(
            ['id' => $request->id],
            ['name' => $request->name, 'phone' => $request->phone, 'address' => $request->address, 'pic_name' => $request->pic_name]
        );

        return redirect()->route('master_data', ['tab' => $request->input('tab', 'customer')])->with('success', 'Customer berhasil disimpan.');
    }

    public function deleteCustomer($id)
    {
        Customer::where('id', $id)->delete();
        return redirect()->route('master_data', ['tab' => 'customer'])->with('success', 'Customer berhasil dihapus.');
    }

    public function storeAccessory(Request $request)
    {
        $request->validate(['code' => 'required|string', 'name' => 'required|string', 'unit' => 'nullable|string']);

        $exists = Accessory::where('code', $request->code)->exists();

        if ($exists) {
            // Edit katalog: hanya perbarui nama. Stok dikelola lewat menu
            // operasional (Receiving / Stock Opname), bukan dari editor master data,
            // agar qty global tetap = total stok seluruh gudang.
            Accessory::where('code', $request->code)->update([
                'name' => $request->name,
                'unit' => $request->input('unit', 'pcs')
            ]);
        } else {
            // Item baru: stok awal di-seed ke gudang aktif lalu qty global
            // direkonsiliasi dari total gudang.
            Accessory::create([
                'code' => $request->code, 
                'name' => $request->name, 
                'qty' => 0,
                'unit' => $request->input('unit', 'pcs')
            ]);
        }

        return redirect()->route('master_data', ['tab' => $request->input('tab', 'accessory')])->with('success', 'Aksesoris berhasil disimpan.');
    }

    /**
     * Import satu baris aksesoris dari CSV. Item baru di-seed ke gudang pusat
     * (qty global ikut tersinkron), item lama cukup diperbarui namanya.
     */
    private function importAccessoryRow(string $code, string $name, int $qty): void
    {
        if (Accessory::where('code', $code)->exists()) {
            Accessory::where('code', $code)->update(['name' => $name]);
            return;
        }
        // Seed ke gudang aktif di session jika ada, fallback WH-PUSAT.
        $seedWarehouse = session('active_warehouse_code', 'WH-PUSAT') ?: 'WH-PUSAT';
        Accessory::create(['code' => $code, 'name' => $name, 'qty' => 0]);
        if ($qty > 0) {
            $this->adjustWarehouseAccessoryStock($seedWarehouse, $code, $qty, 'increment');
        }
    }

    public function deleteAccessory($code)
    {
        Accessory::where('code', $code)->delete();
        return redirect()->route('master_data', ['tab' => 'accessory'])->with('success', 'Aksesoris berhasil dihapus.');
    }

    public function storeSimcard(Request $request)
    {
        $request->validate([
            'kode'     => 'required|string',
            'provider' => 'required|string',
            'category' => 'required|string',
        ]);

        if ($request->id) {
            \App\Models\SimcardMaster::where('id', $request->id)->update([
                'kode'     => $request->kode,
                'provider' => $request->provider,
                'category' => $request->category,
            ]);
        } else {
            \App\Models\SimcardMaster::create([
                'kode'     => $request->kode,
                'provider' => $request->provider,
                'category' => $request->category,
            ]);
        }

        return redirect()->route('master_data', ['tab' => $request->input('tab', 'simcard')])->with('success', 'Master GSM SIM berhasil disimpan.');
    }

    public function deleteSimcard($id)
    {
        \App\Models\SimcardMaster::where('id', $id)->delete();
        return redirect()->route('master_data', ['tab' => 'simcard'])->with('success', 'Master GSM SIM berhasil dihapus.');
    }

    public function storeDeviceModel(Request $request)
    {
        $request->validate(['brand' => 'required|string', 'type' => 'required|string', 'model' => 'required|string']);
        DeviceModel::updateOrCreate(['model' => $request->model], ['brand' => $request->brand, 'type' => $request->type]);
        return redirect()->route('master_data', ['tab' => $request->input('tab', 'device_model')])->with('success', 'Model Perangkat berhasil disimpan.');
    }

    public function deleteDeviceModel($id)
    {
        DeviceModel::where('id', $id)->delete();
        return redirect()->route('master_data', ['tab' => 'device_model'])->with('success', 'Model Perangkat berhasil dihapus.');
    }

    // ==========================================
    // CSV IMPORT & EXPORT
    // ==========================================

    public function importMasterData(Request $request)
    {
        $request->validate(['file' => 'required|file', 'type' => 'required|string']);

        $file   = $request->file('file');
        $path   = $file->getRealPath();
        $handle = fopen($path, 'r');
        fgetcsv($handle, 1000, ','); // Skip header

        DB::transaction(function () use ($handle, $request) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (empty($data[0])) continue;

                match ($request->type) {
                    'warehouse'  => Warehouse::updateOrCreate(['code' => $data[0]], ['name' => $data[1] ?? '', 'type' => strtoupper($data[2] ?? 'CABANG'), 'region' => !empty($data[3]) ? strtoupper(trim($data[3])) : null]),
                    'technician' => Technician::updateOrCreate(['code' => $data[0]], ['name' => $data[1] ?? '', 'area' => $data[2] ?? null]),
                    'accessory'  => $this->importAccessoryRow($data[0], $data[1] ?? '', intval($data[2] ?? 0)),
                    'simcard'    => GsmSimcard::updateOrCreate(
                        ['msisdn' => $data[0]],
                        [
                            'provider'       => $data[1] ?? 'Unknown',
                            'category'       => $data[2] ?? 'General',
                            'status'         => $data[3] ?? 'IN_STOCK',
                            // Assign ke gudang aktif saat import; jika sudah ada row (update), kolom ini tetap diisi
                            'warehouse_code' => session('active_warehouse_code') ?: null,
                        ]
                    ),
                    'customer'   => Customer::updateOrCreate(['name' => $data[0]], ['phone' => $data[1] ?? null, 'address' => $data[2] ?? null, 'pic_name' => $data[3] ?? null]),
                    default      => null,
                };
            }
        });

        fclose($handle);
        return redirect()->route('master_data', ['tab' => $request->input('type', 'warehouse')])->with('success', 'Bulk import data master berhasil diproses.');
    }

    // ==========================================
    // REPORTS
    // ==========================================

    public function reports(Request $request, ReportService $reports)
    {
        $filterInput = $request->only(['from', 'to', 'period', 'warehouse']);
        if ($request->user()->isWarehouseBound()) {
            $filterInput['warehouse'] = $request->user()->warehouse_code;
        }
        $filters = $reports->resolveFilters($filterInput);

        $whFilter = $filters['warehouse']; // null or warehouse code string

        // Status stats scoped to warehouse (when applicable)
        $statusQuery = fn($status) => Device::where('status', $status)
            ->when($whFilter, fn($q) => $q->where('warehouse_code', $whFilter));

        $statusStats = [
            'IN_STOCK'   => $statusQuery('IN_STOCK')->count(),
            'IN_TRANSIT' => $statusQuery('IN_TRANSIT')->count(),
            'ISSUED'     => $statusQuery('ISSUED')->count(),
            'INSTALLED'  => $statusQuery('INSTALLED')->count(),
            'REPAIR'     => $statusQuery('REPAIR')->count(),
            'SCRAP'      => $statusQuery('SCRAP')->count(),
        ];

        $stockcard = $reports->stockCard($filters);
        $aging     = $reports->aging($filters['warehouse']);
        
        $inTransactions = \App\Models\DeviceTransaction::with('device')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::IN_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('to_location', $whFilter);
            })->get();

        $accInTransactions = \App\Models\AccessoryTransaction::with('accessory')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::ACC_IN_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('to_location', $whFilter);
            })->get();

        $gsmInTransactions = \App\Models\SimcardTransaction::with('simcard')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::SIM_IN_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('to_location', $whFilter);
            })->get();

        $outTransactions = \App\Models\DeviceTransaction::with('device')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::OUT_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('from_location', $whFilter);
            })->get();

        $accOutTransactions = \App\Models\AccessoryTransaction::with('accessory')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::ACC_OUT_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('from_location', $whFilter);
            })->get();

        $gsmOutTransactions = \App\Models\SimcardTransaction::with('simcard')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', \App\Services\ReportService::SIM_OUT_ACTIONS)
            ->when($whFilter && $whFilter !== 'all', function($q) use ($whFilter) {
                $q->where('from_location', $whFilter);
            })->get();

        // Stok Teknisi: filter devices by warehouse_code when user is warehouse-bound
        $rawTechDevices = \App\Models\Device::whereIn('status', ['ISSUED', 'INSTALLED'])
            ->where('current_holder', 'like', 'Technician:%')
            ->when($whFilter, fn($q) => $q->where('warehouse_code', $whFilter))
            ->get(['current_holder', 'model', 'warehouse_code']);

        $techStockMatrix = [];
        $activeTechNames = [];
        foreach ($rawTechDevices as $d) {
            $model  = $d->model ?: 'Model Lain';
            $holder = trim(preg_replace('/^Technician:\s*/i', '', $d->current_holder));
            $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + 1;
            $activeTechNames[$holder] = true;
        }

        // Accessories tech stock
        $accIssued = \Illuminate\Support\Facades\DB::table('accessory_transactions')
            ->select('accessory_code', 'to_location', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_issued'))
            ->where('action', 'ISSUED')
            ->where('to_location', 'like', 'Technician:%')
            ->groupBy('accessory_code', 'to_location')
            ->get();
            
        $accReturned = \Illuminate\Support\Facades\DB::table('accessory_transactions')
            ->select('accessory_code', 'from_location', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_returned'))
            ->where('action', 'RETURN')
            ->where('from_location', 'like', 'Technician:%')
            ->groupBy('accessory_code', 'from_location')
            ->get()->keyBy(function($item) { return $item->accessory_code . '_' . $item->from_location; });

        $accNames = \App\Models\Accessory::pluck('name', 'code');
        
        foreach ($accIssued as $ai) {
            $holder = trim(preg_replace('/^Technician:\s*/i', '', $ai->to_location));
            $model = 'ACC: ' . ($accNames[$ai->accessory_code] ?? $ai->accessory_code);
            
            $returned = $accReturned->get($ai->accessory_code . '_' . $ai->to_location)->total_returned ?? 0;
            $current = $ai->total_issued - $returned;
            
            if ($current > 0) {
                $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                $activeTechNames[$holder] = true;
            }
        }

        // GSM tech stock
        $gsmIssued = \Illuminate\Support\Facades\DB::table('simcard_transactions')
            ->select('to_location', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_issued'))
            ->where('action', 'ISSUED')
            ->where('to_location', 'like', 'Technician:%')
            ->groupBy('to_location')
            ->get();
            
        $gsmReturned = \Illuminate\Support\Facades\DB::table('simcard_transactions')
            ->select('from_location', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_returned'))
            ->whereIn('action', ['RETURNED', 'INSTALLED'])
            ->where('from_location', 'like', 'Technician:%')
            ->groupBy('from_location')
            ->get()->keyBy('from_location');
            
        foreach ($gsmIssued as $gi) {
            $holder = trim(preg_replace('/^Technician:\s*/i', '', $gi->to_location));
            $model = 'GSM / SIMCARD';
            
            $returned = $gsmReturned->get($gi->to_location)->total_returned ?? 0;
            $current = $gi->total_issued - $returned;
            
            if ($current > 0) {
                $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                $activeTechNames[$holder] = true;
            }
        }

        // Only list technicians that actually hold devices in this scope
        // (if no active techs, fall back to warehouse-area filter)
        if (!empty($activeTechNames)) {
            $techniciansList = \App\Models\Technician::whereIn('name', array_keys($activeTechNames))
                ->orderBy('name')->get();
        } else {
            $techniciansList = collect(); // empty — no devices at technicians in this scope
        }


        $data = [
            'filters'        => $filters,
            'warehouses'     => $reports->warehouseOptions(),
            'statusStats'    => $statusStats,
            'executive'      => $reports->executiveSummary($filters),
            'movement'       => $reports->inOutMovement($filters),
            'movementDaily'  => $reports->dailyMovement($filters),
            'technicianStock'=> $reports->technicianStock(),
            'customerStock'  => $reports->customerStock(),
            'aging'          => $aging,
            'quality'        => $reports->quality($filters),
            'adjustment'     => $reports->adjustmentAudit($filters),
            'stockcard'      => $stockcard,
            
            // Unified Table Variables
            'deviceRows'       => $stockcard['device']['rows'] ?? [],
            'inTransactions'     => $inTransactions,
            'accInTransactions'  => $accInTransactions,
            'gsmInTransactions'  => $gsmInTransactions,
            'outTransactions'    => $outTransactions,
            'accOutTransactions' => $accOutTransactions,
            'gsmOutTransactions' => $gsmOutTransactions,
            'bekasByModel'     => \App\Models\Device::where(function($q) {
                                      $q->where('status', 'RETURNED')
                                        ->orWhere(function($sq) {
                                            $sq->where('status', 'IN_STOCK')->where('unit_condition', 'BEKAS');
                                        });
                                  })
                                  ->when($filterInput['warehouse'] ?? session('active_warehouse_code'), function($q) use ($filterInput) {
                                      $wh = $filterInput['warehouse'] ?? session('active_warehouse_code');
                                      if ($wh && $wh !== 'all') {
                                          if (is_array($wh)) {
                                              $q->whereIn('warehouse_code', $wh);
                                          } else {
                                              $q->where('warehouse_code', $wh);
                                          }
                                      }
                                  })
                                  ->get()
                                  ->groupBy('model')
                                  ->map->count(),
            'techniciansList'  => $techniciansList,
            'techStockMatrix'  => $techStockMatrix,

            // Untuk view: tampilkan tanggal terformat
            'fromDate'       => $filters['from']->format('Y-m-d'),
            'toDate'         => $filters['to']->format('Y-m-d'),
            'rawWarehouse'   => $filterInput['warehouse'] ?? session('active_warehouse_code') ?? 'all',
        ];

        return view('reports', $data);
    }

    /**
     * Tampilan ramah-cetak (untuk Simpan sebagai PDF via browser).
     */
    public function printReport(Request $request, ReportService $reports)
    {
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));

        return view('reports_print', [
            'filters'         => $filters,
            'fromDate'        => $filters['from']->format('Y-m-d'),
            'toDate'          => $filters['to']->format('Y-m-d'),
            'warehouseLabel'  => $filters['warehouse'] ? (Warehouse::where('code', $filters['warehouse'])->value('name') ?? $filters['warehouse']) : 'Semua Gudang',
            'executive'       => $reports->executiveSummary($filters),
            'movement'        => $reports->inOutMovement($filters),
            'technicianStock' => $reports->technicianStock(),
            'aging'           => $reports->aging($filters['warehouse']),
            'quality'         => $reports->quality($filters),
            'adjustment'      => $reports->adjustmentAudit($filters),
            'stockcard'       => $reports->stockCard($filters),
        ]);
    }

    public function exportReport($type, Request $request, ReportService $reports)
    {
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));
        $format  = $request->query('format', 'csv') === 'excel' ? 'excel' : 'csv';

        // Susun dataset (header + rows) sesuai tipe report.
        [$headerRow, $rows] = $this->buildReportDataset($type, $filters, $reports);

        $filename = "dlms_report_{$type}_" . date('Ymd_His');

        if ($format === 'excel') {
            return $this->streamExcel($filename, $headerRow, $rows);
        }

        return $this->streamCsv($filename, $headerRow, $rows);
    }

    public function exportTemplateExcel(Request $request, ReportService $reports)
    {
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));
        $whCode = $filters['warehouse'] ?? 'Semua Gudang';
        $whName = $whCode === 'Semua Gudang' ? 'Semua Gudang' : (Warehouse::where('code', $whCode)->value('name') ?? $whCode);
        
        // 1. Get Stock Card data for devices
        $sc = $reports->stockCard($filters);
        $deviceRows = $sc['device']['rows'] ?? [];
        
        // 2. Get incoming transactions
        $inTransactions = DeviceTransaction::with('device')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', ReportService::IN_ACTIONS);
            
        if ($filters['warehouse']) {
            $inTransactions->where(function($q) use ($filters) {
                $wh = $filters['warehouse'];
                if (is_array($wh)) {
                    $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh);
                } else {
                    $q->where('to_location', $wh)->orWhere('from_location', $wh);
                }
            });
        }
        $inTransactions = $inTransactions->orderBy('created_at')->get();

        // 3. Get outgoing transactions
        $outTransactions = DeviceTransaction::with('device')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->whereIn('action', ReportService::OUT_ACTIONS);
            
        if ($filters['warehouse']) {
            $outTransactions->where(function($q) use ($filters) {
                $wh = $filters['warehouse'];
                if (is_array($wh)) {
                    $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh);
                } else {
                    $q->where('to_location', $wh)->orWhere('from_location', $wh);
                }
            });
        }
        $outTransactions = $outTransactions->orderBy('created_at')->get();

        // Calculate frozen stock (dead stock)
        $deadStockList = $reports->aging($filters['warehouse'])['dead_stock'] ?? [];
        $deadStockByModel = collect($deadStockList)->groupBy('model')->map->count();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheetTitle = strtoupper(substr($whName, 0, 30));
        $sheetTitle = preg_replace('/[^A-Za-z0-9]/', ' ', $sheetTitle);
        $sheet->setTitle(trim($sheetTitle));

        // Styling arrays
        $headerStyleLeft = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC00000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $headerStyleMid = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF00B050']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $headerStyleRight = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE26B0A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        // Headers
        // Section 1: Laporan Stok Barang
        $sheet->setCellValue('A1', 'LAPORAN STOK BARANG');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyleLeft);
        
        $headersLeft = ['Nama Barang', 'Satuan', 'Stok Awal', 'Barang Masuk', 'Barang Keluar', 'Sisa', 'Barang Beku', 'STOCK AKHIR'];
        foreach ($headersLeft as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '2', $h);
        }
        $sheet->getStyle('A2:H2')->applyFromArray($headerStyleLeft);

        // Section 2: Barang Masuk (starts at J)
        $sheet->setCellValue('J1', 'BARANG MASUK');
        $sheet->mergeCells('J1:O1');
        $sheet->getStyle('J1:O1')->applyFromArray($headerStyleMid);

        $headersMid = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
        foreach ($headersMid as $i => $h) {
            $col = chr(74 + $i);
            $sheet->setCellValue($col . '2', $h);
        }
        $sheet->getStyle('J2:O2')->applyFromArray($headerStyleMid);

        // Section 3: Barang Keluar (starts at Q)
        $sheet->setCellValue('Q1', 'BARANG KELUAR');
        $sheet->mergeCells('Q1:V1');
        $sheet->getStyle('Q1:V1')->applyFromArray($headerStyleRight);

        $headersRight = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
        foreach ($headersRight as $i => $h) {
            $col = chr(81 + $i); // Q is 81
            $sheet->setCellValue($col . '2', $h);
        }
        $sheet->getStyle('Q2:V2')->applyFromArray($headerStyleRight);

        // Populate Data - Section 1
        $row = 3;
        foreach ($deviceRows as $dr) {
            $name = $dr['name'];
            $awal = $dr['opening'];
            $masuk = $dr['in'];
            $keluar = $dr['out'];
            $sisa = $dr['closing'];
            $beku = $deadStockByModel[$name] ?? 0;
            $akhir = $sisa;

            $sheet->setCellValue('A'.$row, $name);
            $sheet->setCellValue('B'.$row, 'Pcs');
            $sheet->setCellValue('C'.$row, $awal);
            $sheet->setCellValue('D'.$row, $masuk);
            $sheet->setCellValue('E'.$row, $keluar);
            $sheet->setCellValue('F'.$row, $sisa);
            $sheet->setCellValue('G'.$row, $beku);
            $sheet->setCellValue('H'.$row, $akhir);
            $row++;
        }
        if ($row > 3) $sheet->getStyle('A3:H'.($row - 1))->applyFromArray($borderStyle);

        // Populate Data - Section 2 (Masuk)
        $row = 3;
        foreach ($inTransactions as $t) {
            $sheet->setCellValue('J'.$row, $t->created_at->format('d/m/y'));
            $sheet->setCellValue('K'.$row, $t->device_sn);
            $sheet->setCellValue('L'.$row, $t->device->model ?? $t->device->type ?? '-');
            $sheet->setCellValue('M'.$row, 1);
            $sheet->setCellValue('N'.$row, 'Pcs');
            $sheet->setCellValue('O'.$row, $t->notes ?: $t->action);
            $row++;
        }
        if ($row > 3) $sheet->getStyle('J3:O'.($row - 1))->applyFromArray($borderStyle);

        // Populate Data - Section 3 (Keluar)
        $row = 3;
        foreach ($outTransactions as $t) {
            $sheet->setCellValue('Q'.$row, $t->created_at->format('d/m/y'));
            $sheet->setCellValue('R'.$row, $t->device_sn);
            $sheet->setCellValue('S'.$row, $t->device->model ?? $t->device->type ?? '-');
            $sheet->setCellValue('T'.$row, 1);
            $sheet->setCellValue('U'.$row, 'Pcs');
            $sheet->setCellValue('V'.$row, $t->notes ?: $t->action);
            $row++;
        }
        if ($row > 3) $sheet->getStyle('Q3:V'.($row - 1))->applyFromArray($borderStyle);

        // Auto-size columns
        foreach (range('A', 'V') as $col) {
            if (!in_array($col, ['I', 'P'])) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            } else {
                $sheet->getColumnDimension($col)->setWidth(3);
            }
        }

        $fileName = 'Template_Laporan_Stok_' . date('Ymd_His') . '.xlsx';
        
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
        public function exportCustomExcel(Request $request, ReportService $reports)
    {
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));
        $whCode = $filters['warehouse'] ?? 'Semua Gudang';
        $whName = $whCode === 'Semua Gudang' ? 'Semua Gudang' : (Warehouse::where('code', $whCode)->value('name') ?? $whCode);
        
        $wantStok = $request->query('stok', '0') === '1';
        $wantMasuk = $request->query('masuk', '0') === '1';
        $wantKeluar = $request->query('keluar', '0') === '1';
        $wantTeknisi = $request->query('teknisi', '0') === '1';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFED7D31']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        $sheetIndex = 0;

        // 1. Laporan Stok Barang
        if ($wantStok) {
            $sc = $reports->stockCard($filters);
            $deviceRows = $sc['device']['rows'] ?? [];
            
            $deadStockList = $reports->aging($filters['warehouse'])['dead_stock'] ?? [];
            $deadStockByModel = collect($deadStockList)->groupBy('model')->map->count();

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Laporan Stok Barang');
            
            $headersLeft = ['Nama Barang', 'Satuan', 'Stok Awal', 'Barang Masuk', 'Barang Keluar', 'Sisa', 'Barang Beku', 'STOCK AKHIR'];
            foreach ($headersLeft as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            $row = 2;
            // Devices
            foreach ($deviceRows as $dr) {
                $name = $dr['name'];
                $awal = $dr['opening'];
                $masuk = $dr['in'];
                $keluar = $dr['out'];
                $sisa = $dr['closing'];
                $beku = $deadStockByModel[$name] ?? 0;
                $akhir = $sisa;

                $sheet->setCellValue('A'.$row, $name);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $awal);
                $sheet->setCellValue('D'.$row, $masuk);
                $sheet->setCellValue('E'.$row, $keluar);
                $sheet->setCellValue('F'.$row, $sisa);
                $sheet->setCellValue('G'.$row, $beku);
                $sheet->setCellValue('H'.$row, $akhir);
                $row++;
            }
            // ACC
            foreach ($sc['accessory']['rows'] ?? [] as $r) {
                $sheet->setCellValue('A'.$row, 'ACC: ' . $r['name']);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $r['opening']);
                $sheet->setCellValue('D'.$row, $r['in']);
                $sheet->setCellValue('E'.$row, $r['out']);
                $sheet->setCellValue('F'.$row, $r['closing']);
                $sheet->setCellValue('G'.$row, '-');
                $sheet->setCellValue('H'.$row, max(0, $r['closing']));
                $row++;
            }
            // GSM
            foreach ($sc['gsm']['rows'] ?? [] as $r) {
                $sheet->setCellValue('A'.$row, 'GSM: ' . $r['name']);
                $sheet->setCellValue('B'.$row, 'Pcs');
                $sheet->setCellValue('C'.$row, $r['opening']);
                $sheet->setCellValue('D'.$row, $r['in']);
                $sheet->setCellValue('E'.$row, $r['out']);
                $sheet->setCellValue('F'.$row, $r['closing']);
                $sheet->setCellValue('G'.$row, '-');
                $sheet->setCellValue('H'.$row, max(0, $r['closing']));
                $row++;
            }

            if ($row > 2) {
                $sheet->getStyle('A2:H'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':H'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 2. Barang Masuk
        if ($wantMasuk) {
            $inTransactions = DeviceTransaction::with('device')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->whereIn('action', ReportService::IN_ACTIONS);
            if ($filters['warehouse']) {
                $inTransactions->where(function($q) use ($filters) {
                    $wh = $filters['warehouse'];
                    if (is_array($wh)) { $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh); } 
                    else { $q->where('to_location', $wh)->orWhere('from_location', $wh); }
                });
            }
            $inTransactions = $inTransactions->orderBy('created_at')->get();
            
            $accIn = \App\Models\AccessoryTransaction::with('accessory')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'IN');
            if ($filters['warehouse']) { $accIn->where('to_location', $filters['warehouse']); }
            $accIn = $accIn->get();

            $gsmIn = \App\Models\SimcardTransaction::whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'IN');
            if ($filters['warehouse']) { $gsmIn->where('to_location', $filters['warehouse']); }
            $gsmIn = $gsmIn->get();

            $summaryMasuk = [];
            foreach($inTransactions as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->device->model ?? $t->device->type ?? '-';
                $name = $t->device->model ?? $t->device->type ?? 'Device Lain';
                $ket = $t->to_location ?: ($t->device->current_holder ?? '-');
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += 1;
            }
            foreach($accIn as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->accessory_code;
                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += $t->qty;
            }
            foreach($gsmIn as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = 'GSM';
                $name = 'GSM / SIMCARD';
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryMasuk[$key]['qty'] += 1;
            }
            usort($summaryMasuk, function($a, $b) { return strtotime(str_replace('/','-',''.$b['date'])) <=> strtotime(str_replace('/','-',''.$a['date'])); });

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Barang Masuk');
            
            $headers = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($summaryMasuk as $data) {
                $sheet->setCellValue('A'.$row, $data['date']);
                $sheet->setCellValue('B'.$row, $data['code']);
                $sheet->setCellValue('C'.$row, $data['name']);
                $sheet->setCellValue('D'.$row, $data['qty']);
                $sheet->setCellValue('E'.$row, 'Pcs');
                $sheet->setCellValue('F'.$row, $data['ket']);
                $row++;
            }
            if ($row > 2) {
                $sheet->getStyle('A2:F'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':F'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'F') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 3. Barang Keluar
        if ($wantKeluar) {
            $outTransactions = DeviceTransaction::with('device')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->whereIn('action', ReportService::OUT_ACTIONS);
            if ($filters['warehouse']) {
                $outTransactions->where(function($q) use ($filters) {
                    $wh = $filters['warehouse'];
                    if (is_array($wh)) { $q->whereIn('to_location', $wh)->orWhereIn('from_location', $wh); } 
                    else { $q->where('to_location', $wh)->orWhere('from_location', $wh); }
                });
            }
            $outTransactions = $outTransactions->orderBy('created_at')->get();
            
            $accOut = \App\Models\AccessoryTransaction::with('accessory')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'OUT');
            if ($filters['warehouse']) { $accOut->where('to_location', $filters['warehouse']); }
            $accOut = $accOut->get();

            $gsmOut = \App\Models\SimcardTransaction::whereBetween('created_at', [$filters['from'], $filters['to']])
                ->where('action', 'OUT');
            if ($filters['warehouse']) { $gsmOut->where('to_location', $filters['warehouse']); }
            $gsmOut = $gsmOut->get();

            $summaryKeluar = [];
            foreach($outTransactions as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->device->model ?? $t->device->type ?? '-';
                $name = $t->device->model ?? $t->device->type ?? 'Device Lain';
                $ket = $t->to_location ?: ($t->device->current_holder ?? '-');
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += 1;
            }
            foreach($accOut as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = $t->accessory_code;
                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += $t->qty;
            }
            foreach($gsmOut as $t) { 
                $date = $t->created_at->format('d/m/Y');
                $code = 'GSM';
                $name = 'GSM / SIMCARD';
                $ket = $t->to_location ?? '-';
                $key = "$date|$code|$name|$ket";
                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                $summaryKeluar[$key]['qty'] += 1;
            }
            usort($summaryKeluar, function($a, $b) { return strtotime(str_replace('/','-',''.$b['date'])) <=> strtotime(str_replace('/','-',''.$a['date'])); });

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Barang Keluar');
            
            $headers = ['Tgl', 'Kode Barang', 'Nama Barang', 'Jumlah', 'Satuan', 'Keterangan'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue($col . '1', $h);
            }
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($summaryKeluar as $data) {
                $sheet->setCellValue('A'.$row, $data['date']);
                $sheet->setCellValue('B'.$row, $data['code']);
                $sheet->setCellValue('C'.$row, $data['name']);
                $sheet->setCellValue('D'.$row, $data['qty']);
                $sheet->setCellValue('E'.$row, 'Pcs');
                $sheet->setCellValue('F'.$row, $data['ket']);
                $row++;
            }
            if ($row > 2) {
                $sheet->getStyle('A2:F'.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':F'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range('A', 'F') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 4. Stok Teknisi
        if ($wantTeknisi) {
            $whFilter = $filters['warehouse'];
            $techniciansList = \App\Models\User::where('role', 'teknisi')->get();

            $techStockMatrix = [];
            
            $rawTechDevices = Device::where('status', 'ISSUED')
                ->where('current_holder', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('warehouse_code', $whFilter))
                ->get(['current_holder', 'model']);

            foreach ($rawTechDevices as $d) {
                $model  = $d->model ?: 'Model Lain';
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $d->current_holder));
                $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + 1;
            }

            $accIssued = \Illuminate\Support\Facades\DB::table('accessory_transactions')
                ->select('accessory_code', 'technician_code', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_issued'))
                ->where('action', 'OUT')
                ->where('to_location', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('from_location', $whFilter))
                ->groupBy('accessory_code', 'technician_code')
                ->get();

            foreach ($accIssued as $ai) {
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $ai->technician_code));
                $model = 'ACC: ' . $ai->accessory_code;
                
                $returned = \Illuminate\Support\Facades\DB::table('accessory_transactions')
                    ->where('action', 'IN')
                    ->where('accessory_code', $ai->accessory_code)
                    ->where('technician_code', 'Technician: ' . $holder)
                    ->when($whFilter, fn($q) => $q->where('to_location', $whFilter))
                    ->sum('qty');
                    
                $current = $ai->total_issued - $returned;
                if ($current > 0) {
                    $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                }
            }

            $gsmIssued = \Illuminate\Support\Facades\DB::table('simcard_transactions')
                ->select('msisdn', 'operator', 'to_location', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_issued'))
                ->where('action', 'OUT')
                ->where('to_location', 'like', 'Technician:%')
                ->when($whFilter, fn($q) => $q->where('from_location', $whFilter))
                ->groupBy('msisdn', 'operator', 'to_location')
                ->get();

            foreach ($gsmIssued as $gi) {
                $holder = trim(preg_replace('/^Technician:\s*/i', '', $gi->to_location));
                $model = 'GSM / SIMCARD';
                
                $returned = \Illuminate\Support\Facades\DB::table('simcard_transactions')
                    ->where('action', 'IN')
                    ->where('msisdn', $gi->msisdn)
                    ->where('from_location', 'Technician: ' . $holder)
                    ->when($whFilter, fn($q) => $q->where('to_location', $whFilter))
                    ->count();
                    
                $current = $gi->total_issued - $returned;
                if ($current > 0) {
                    $techStockMatrix[$model][$holder] = ($techStockMatrix[$model][$holder] ?? 0) + $current;
                }
            }

            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle('Stok Teknisi');
            
            $sheet->setCellValue('A1', 'Nama Barang');
            $colIndex = 1; // B
            foreach ($techniciansList as $tech) {
                $colStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(++$colIndex);
                $sheet->setCellValue($colStr . '1', $tech->name);
            }
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($techStockMatrix as $modelName => $techs) {
                $sheet->setCellValue('A'.$row, $modelName);
                $c = 1;
                foreach ($techniciansList as $tech) {
                    $cStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(++$c);
                    $qty = $techs[$tech->name] ?? '';
                    $sheet->setCellValue($cStr.$row, $qty);
                }
                $row++;
            }
            
            if ($row > 2) {
                $sheet->getStyle('A2:'.$lastCol.($row - 1))->applyFromArray($borderStyle);
                for($r=2; $r<$row; $r++) {
                    if($r % 2 == 0) $sheet->getStyle('A'.$r.':'.$lastCol.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
                }
            }
            foreach (range(1, $colIndex) as $c) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
            }
        }

        if ($spreadsheet->getSheetCount() == 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('Kosong');
            $sheet->setCellValue('A1', 'Tidak ada data dipilih');
        }

        $spreadsheet->setActiveSheetIndex(0);
        
        $parts = [];
        if ($wantStok) $parts[] = 'Stok';
        if ($wantMasuk) $parts[] = 'Masuk';
        if ($wantKeluar) $parts[] = 'Keluar';
        if ($wantTeknisi) $parts[] = 'Teknisi';
        $fileName = 'Export_' . implode('_', $parts) . '_' . date('Ymd_His') . '.xlsx';
        
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }




    /**
     * Bangun [header, rows] untuk tiap jenis report.
     */
    private function buildReportDataset(string $type, array $filters, ReportService $reports): array
    {
        switch ($type) {
            case 'stock':
                $rows = Device::all()->map(fn($d) => [
                    $d->id, $d->serial_number, $d->imei, $d->type, $d->model, $d->status,
                    $d->current_holder, $d->warehouse_code, $d->vehicle_plate, $d->created_at,
                ])->toArray();
                return [['ID', 'Serial Number', 'IMEI', 'Type', 'Model', 'Status', 'Current Holder', 'Warehouse', 'Vehicle Plate', 'Created At'], $rows];

            case 'transactions':
                $q = DeviceTransaction::whereBetween('created_at', [$filters['from'], $filters['to']]);
                $rows = $q->get()->map(fn($t) => [
                    $t->id, $t->device_sn, $t->action, $t->from_location, $t->to_location,
                    $t->operator, $t->scanned_by, $t->via_web ? 'Yes' : 'No', $t->notes, $t->created_at,
                ])->toArray();
                return [['ID', 'Device SN', 'Action', 'From', 'To', 'Operator', 'Scanned By', 'Via Web', 'Notes', 'Created At'], $rows];

            case 'inout':
                $m = $reports->inOutMovement($filters);
                $rows = [];
                foreach ($m['labels'] as $i => $label) {
                    $rows[] = [$label, $m['in'][$i], $m['out'][$i], $m['net'][$i]];
                }
                return [['Periode', 'Barang Masuk', 'Barang Keluar', 'Net'], $rows];

            case 'technicians':
                $ts = $reports->technicianStock();
                $rows = array_map(fn($t) => [
                    $t['code'], $t['name'], $t['area'] ?? '-', $t['gps'], $t['mdvr'], $t['dashcam'], $t['other'], $t['total'],
                ], $ts['devices']);
                return [['Kode Teknisi', 'Nama', 'Area', 'GPS Tracker', 'MDVR', 'Dashcam', 'Lainnya', 'Total Aset'], $rows];

            case 'customers':
                $cs = $reports->customerStock();
                $rows = array_map(fn($c) => [
                    $c['name'], $c['gps'], $c['mdvr'], $c['dashcam'], $c['other'], $c['total'],
                ], $cs['devices']);
                foreach ($cs['accessories'] as $a) {
                    $rows[] = ['[Aksesoris] ' . $a['customer'], $a['accessory_name'], '', '', '', $a['qty']];
                }
                return [['Customer', 'GPS Tracker', 'MDVR', 'Dashcam', 'Lainnya', 'Total Aset / Qty'], $rows];

            case 'aging':
                $ag = $reports->aging($filters['warehouse']);
                $rows = array_map(fn($d) => [
                    $d['serial_number'], $d['type'], $d['model'], $d['warehouse'], $d['age_days'], $d['last_movement'],
                ], $ag['dead_stock']);
                return [['Serial Number', 'Type', 'Model', 'Gudang', 'Umur (hari)', 'Pergerakan Terakhir'], $rows];

            case 'quality':
                $q = $reports->quality($filters);
                $rows = array_map(fn($i) => [
                    $i['device_id'], $i['condition'], $i['qc_result'], $i['operator'], $i['notes'], $i['created_at'],
                ], $q['recent']);
                return [['Device ID', 'Kondisi', 'Hasil QC', 'Operator', 'Catatan', 'Tanggal'], $rows];

            case 'adjustment':
                $adj = $reports->adjustmentAudit($filters);
                $rows = array_map(fn($a) => [
                    'DEVICE', $a['device_sn'], '', $a['from'], $a['to'], $a['operator'], $a['notes'], $a['created_at'],
                ], $adj['device_adjustments']);
                foreach ($adj['accessory_adjustments'] as $a) {
                    $rows[] = ['ACCESSORY', $a['accessory_code'], $a['qty'], $a['from'], $a['to'], '', $a['notes'], $a['created_at']];
                }
                return [['Jenis', 'Item', 'Qty', 'Dari', 'Ke', 'Operator', 'Catatan', 'Tanggal'], $rows];

            case 'stockcard':
                $sc = $reports->stockCard($filters);
                $catLabels = ['device' => 'Device', 'accessory' => 'Aksesoris', 'gsm' => 'Kartu GSM'];
                $rows = [];
                foreach ($catLabels as $cat => $label) {
                    foreach (($sc[$cat]['rows'] ?? []) as $r) {
                        $rows[] = [
                            $label, $r['name'], $r['opening'], $r['in'], $r['out'],
                            $r['first_in'] ?? '-', $r['last_out'] ?? '-', $r['closing'],
                        ];
                    }
                }
                return [['Kategori', 'Nama Barang', 'Stok Awal', 'Masuk', 'Keluar', 'Tgl Masuk Pertama', 'Tgl Keluar Terakhir', 'Sisa Stok'], $rows];

            case 'all':
                $rows = [];

                // ── 1. Ringkasan Eksekutif ──
                $exec = $reports->executiveSummary($filters);
                $rows[] = ['=== RINGKASAN EKSEKUTIF ===', '', '', '', '', '', '', ''];
                $rows[] = ['Total Masuk', 'Total Keluar', 'Net', 'Total Device', '', '', '', ''];
                $rows[] = [$exec['total_in'], $exec['total_out'], $exec['net'], $exec['total_devices'], '', '', '', ''];
                $rows[] = ['', '', '', '', '', '', '', ''];
                $rows[] = ['Status', 'Jumlah', '', '', '', '', '', ''];
                foreach ($exec['status_snapshot'] as $status => $count) {
                    $rows[] = [$status, $count, '', '', '', '', '', ''];
                }
                $rows[] = ['', '', '', '', '', '', '', ''];

                // ── 2. Mutasi Barang ──
                $m = $reports->inOutMovement($filters);
                $rows[] = ['=== MUTASI BARANG IN/OUT ===', '', '', '', '', '', '', ''];
                $rows[] = ['Periode', 'Masuk', 'Keluar', 'Net', '', '', '', ''];
                foreach ($m['labels'] as $i => $label) {
                    $rows[] = [$label, $m['in'][$i], $m['out'][$i], $m['net'][$i], '', '', '', ''];
                }
                if (!empty($m['by_action'])) {
                    $rows[] = ['', '', '', '', '', '', '', ''];
                    $rows[] = ['Breakdown per Aksi', 'Jumlah', '', '', '', '', '', ''];
                    foreach ($m['by_action'] as $action => $count) {
                        $rows[] = [$action, $count, '', '', '', '', '', ''];
                    }
                }
                $rows[] = ['', '', '', '', '', '', '', ''];

                // ── 3. Stok Teknisi ──
                $ts = $reports->technicianStock();
                $rows[] = ['=== STOK TEKNISI ===', '', '', '', '', '', '', ''];
                $rows[] = ['Kode', 'Nama', 'Area', 'GPS Tracker', 'MDVR', 'Dashcam', 'Lainnya', 'Total'];
                foreach ($ts['devices'] as $t) {
                    $rows[] = [$t['code'], $t['name'], $t['area'] ?? '-', $t['gps'], $t['mdvr'], $t['dashcam'], $t['other'], $t['total']];
                }
                $rows[] = ['', '', '', '', '', '', '', ''];

                // ── 4. Aging / Dead Stock ──
                $ag = $reports->aging($filters['warehouse']);
                $rows[] = ['=== AGING / DEAD STOCK ===', '', '', '', '', '', '', ''];
                $rows[] = ['Bucket', 'Jumlah', '', '', '', '', '', ''];
                foreach ($ag['stock_buckets'] as $bucket => $count) {
                    $rows[] = [$bucket . ' hari', $count, '', '', '', '', '', ''];
                }
                $rows[] = ['', '', '', '', '', '', '', ''];
                $rows[] = ['Serial Number', 'Type', 'Model', 'Gudang', 'Umur (hari)', 'Pergerakan Terakhir', '', ''];
                foreach ($ag['dead_stock'] as $d) {
                    $rows[] = [$d['serial_number'], $d['type'], $d['model'], $d['warehouse'], $d['age_days'], $d['last_movement'], '', ''];
                }
                $rows[] = ['', '', '', '', '', '', '', ''];

                // ── 5. Kualitas ──
                $q = $reports->quality($filters);
                $rows[] = ['=== LAPORAN KUALITAS ===', '', '', '', '', '', '', ''];
                $rows[] = ['Total Inspeksi', 'QC Pass', 'QC Fail', 'Repair', 'Scrap', '', '', ''];
                $rows[] = [$q['total_inspected'] ?? 0, $q['passed'] ?? 0, $q['failed'] ?? 0, $q['repaired'] ?? 0, $q['scrapped'] ?? 0, '', '', ''];
                $rows[] = ['', '', '', '', '', '', '', ''];
                if (!empty($q['recent'])) {
                    $rows[] = ['Device ID', 'Kondisi', 'Hasil QC', 'Operator', 'Catatan', 'Tanggal', '', ''];
                    foreach ($q['recent'] as $i) {
                        $rows[] = [$i['device_id'], $i['condition'], $i['qc_result'], $i['operator'], $i['notes'], $i['created_at'], '', ''];
                    }
                    $rows[] = ['', '', '', '', '', '', '', ''];
                }

                // ── 6. Koreksi / Adjustment ──
                $adj = $reports->adjustmentAudit($filters);
                $rows[] = ['=== KOREKSI / ADJUSTMENT ===', '', '', '', '', '', '', ''];
                $rows[] = ['Jenis', 'Item', 'Qty', 'Dari', 'Ke', 'Operator', 'Catatan', 'Tanggal'];
                foreach ($adj['device_adjustments'] as $a) {
                    $rows[] = ['DEVICE', $a['device_sn'], '', $a['from'], $a['to'], $a['operator'], $a['notes'], $a['created_at']];
                }
                foreach ($adj['accessory_adjustments'] as $a) {
                    $rows[] = ['ACCESSORY', $a['accessory_code'], $a['qty'], $a['from'], $a['to'], '', $a['notes'], $a['created_at']];
                }
                $rows[] = ['', '', '', '', '', '', '', ''];

                // ── 7. Kartu Stok ──
                $sc = $reports->stockCard($filters);
                $catLabels2 = ['device' => 'Device', 'accessory' => 'Aksesoris', 'gsm' => 'Kartu GSM'];
                $rows[] = ['=== KARTU STOK ===', '', '', '', '', '', '', ''];
                $rows[] = ['Kategori', 'Nama Barang', 'Stok Awal', 'Masuk', 'Keluar', 'Tgl Masuk Pertama', 'Tgl Keluar Terakhir', 'Sisa Stok'];
                foreach ($catLabels2 as $cat => $label) {
                    foreach (($sc[$cat]['rows'] ?? []) as $r) {
                        $rows[] = [$label, $r['name'], $r['opening'], $r['in'], $r['out'], $r['first_in'] ?? '-', $r['last_out'] ?? '-', $r['closing']];
                    }
                }

                return [['Laporan DLMS — ' . $filters['from']->format('d/m/Y') . ' s/d ' . $filters['to']->format('d/m/Y'), '', '', '', '', '', '', ''], $rows];

            default:
                return [['Info'], [['Tipe report tidak dikenal: ' . $type]]];
        }
    }

    private function streamCsv(string $filename, array $header, array $rows)
    {
        $headers = [
            'Content-type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}.csv",
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($header, $rows) {
            $file = fopen('php://output', 'w');
            fprintf($file, "\xEF\xBB\xBF"); // UTF-8 BOM agar Excel membaca dengan benar
            fputcsv($file, $header);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, 200, $headers);
    }

    /**
     * Ekspor Excel ringan tanpa dependency: tabel HTML dengan mimetype Excel.
     */
    private function streamExcel(string $filename, array $header, array $rows)
    {
        $headers = [
            'Content-type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}.xls",
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
        ];

        return response()->stream(function () use ($header, $rows) {
            echo "\xEF\xBB\xBF";
            echo '<table border="1"><thead><tr>';
            foreach ($header as $h) {
                echo '<th>' . htmlspecialchars((string) $h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }, 200, $headers);
    }

    // ==========================================
    // ALERT CENTER
    // ==========================================

    public function alertCenter(Request $request)
    {
        $service = new \App\Services\DashboardInsightService();

        $user = $request->user();
        if ($user->isWarehouseBound()) {
            $view = $user->warehouse_code;
        } else {
            $view = $request->query('warehouse', session('active_warehouse_code') ?: 'global');
        }
        $scope = $view === 'global' ? null : $view;

        $insights    = $service->getInsights(is_array($scope) ? ($scope[0] ?? null) : $scope);
        $stockAlerts = $service->getStockAlerts($scope);
        $warehouses  = Warehouse::pluck('name', 'code');

        return view('alerts', compact('insights', 'stockAlerts', 'warehouses', 'view'));
    }

    // ==========================================
    // TANDA TERIMA (HANDOVER RECEIPT)
    // ==========================================

    /**
     * Rekonstruksi & tampilkan dokumen tanda terima berdasarkan nomor TT
     * (disimpan di kolom notes transaksi saat barang di-issue).
     */
    public function showReceipt(string $receiptNo)
    {
        $deviceTx = DeviceTransaction::where('notes', $receiptNo)->whereIn('action', ['ISSUED', 'INSTALLED', 'PENDING_ACCEPTANCE'])->get();
        $accTx    = AccessoryTransaction::where('notes', $receiptNo)->get();
        $simTx    = \App\Models\SimcardTransaction::with('simcard')
                        ->where('notes', $receiptNo)
                        ->whereIn('action', ['ISSUED', 'INSTALLED'])
                        ->get();

        if ($deviceTx->isEmpty() && $accTx->isEmpty() && $simTx->isEmpty()) {
            abort(404, 'Tanda terima tidak ditemukan.');
        }

        $first      = $deviceTx->first() ?? $accTx->first() ?? $simTx->first();
        $holderRaw  = $first->to_location ?? '';
        $issued     = \Carbon\Carbon::parse($first->created_at);
        $operator   = $deviceTx->first()?->operator ?? auth()->user()->name ?? 'Warehouse Operator';

        // Parse penerima dari label "Technician: Nama" / "Customer: Nama"
        $recipientType = 'Penerima';
        $recipientName = $holderRaw;
        $recipientMeta = [];
        if (str_starts_with($holderRaw, 'Technician:')) {
            $recipientType = 'Teknisi';
            $recipientName = trim(substr($holderRaw, strlen('Technician:')));
            $tech = Technician::where('name', $recipientName)->first();
            if ($tech) {
                $recipientMeta['Kode Teknisi'] = $tech->code;
            }
        } elseif (str_starts_with($holderRaw, 'Customer:')) {
            $recipientType = 'Pelanggan';
            $recipientName = trim(substr($holderRaw, strlen('Customer:')));
            $cust = Customer::where('name', $recipientName)->first();
            if ($cust) {
                $recipientMeta['Telepon'] = $cust->phone ?? '-';
                $recipientMeta['Alamat'] = $cust->address ?? '-';
                if (!empty($cust->pic_name)) $recipientMeta['Nama PIC'] = $cust->pic_name;
            }
        }

        // Detail perangkat (Dikelompokkan berdasarkan model dan type)
        $deviceSns = $deviceTx->pluck('device_sn')->all();
        $devices   = Device::whereIn('serial_number', $deviceSns)->get()->keyBy('serial_number');
        $rawDeviceItems = $deviceTx->map(function ($t) use ($devices) {
            $d = $devices->get($t->device_sn);
            return [
                'serial_number' => $t->device_sn,
                'type'          => $d->type ?? '-',
                'model'         => $d->model ?? '-',
            ];
        });

        $deviceGroups = collect($rawDeviceItems)->groupBy(function ($item) {
            return $item['type'] . '|' . $item['model'];
        });

        $deviceItems = [];
        foreach ($deviceGroups as $group) {
            $first = $group->first();
            $sns = [];
            foreach ($group->values() as $index => $item) {
                $num = $index + 1;
                $sns[] = "<div style=\"font-size:11px; white-space:nowrap;\"><span style=\"color:#94a3b8;font-weight:bold;margin-right:3px;\">{$num}.</span> " . $item['serial_number'] . "</div>";
            }
            
            $deviceItems[] = [
                'serial_number' => '<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">' . implode("", $sns) . '</div>',
                'type'          => $first['type'],
                'model'         => $first['model'],
                'qty'           => $group->count()
            ];
        }

        // Detail aksesoris
        $accNames = Accessory::whereIn('code', $accTx->pluck('accessory_code')->all())->pluck('name', 'code');
        $accItems = $accTx->map(fn($t) => [
            'code' => $t->accessory_code,
            'name' => $accNames[$t->accessory_code] ?? $t->accessory_code,
            'qty'  => $t->qty,
        ])->values()->toArray();

        // Detail SIM Card (Dikelompokkan berdasarkan provider dan kategori)
        $rawSimItems = $simTx->map(function ($t) {
            $sim = $t->simcard;
            return [
                'msisdn'   => $t->msisdn ?? ($sim->msisdn ?? '-'),
                'provider' => $sim->provider ?? 'Lainnya',
                'category' => $sim->category ?? 'Lainnya',
            ];
        });

        $simItems = [];
        foreach ($rawSimItems->groupBy('provider') as $provider => $group) {
            foreach ($group->groupBy('category') as $category => $subgroup) {
                $sns = [];
                foreach ($subgroup->values() as $index => $item) {
                    $num = $index + 1;
                    $sns[] = "<div style=\"font-size:11px; white-space:nowrap;\"><span style=\"color:#94a3b8;font-weight:bold;margin-right:3px;\">{$num}.</span> " . $item['msisdn'] . "</div>";
                }

                $simItems[] = [
                    'msisdn'   => '<div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 4px;">' . implode("", $sns) . '</div>',
                    'provider' => $provider,
                    'category' => $category,
                    'qty'      => $subgroup->count(),
                ];
            }
        }

        $warehouseCode = $deviceTx->first()->from_location ?? $first->from_location ?? null;
        $warehouseName = $warehouseCode ? (Warehouse::where('code', $warehouseCode)->value('name') ?? $warehouseCode) : '-';

        return view('receipt', [
            'receiptNo'     => $receiptNo,
            'issued'        => $issued,
            'operator'      => $operator,
            'recipientType' => $recipientType,
            'recipientName' => $recipientName,
            'recipientMeta' => $recipientMeta,
            'deviceItems'   => $deviceItems,
            'accItems'      => $accItems,
            'simItems'      => $simItems,
            'warehouseName' => $warehouseName,
            'autoprint'     => request()->boolean('autoprint'),
        ]);
    }

    public function showReturnReceipt(string $receiptNo)
    {
        $receipt = DB::table('return_receipts')->where('receipt_no', $receiptNo)->first();
        if (!$receipt) {
            abort(404, 'Tanda terima return tidak ditemukan.');
        }

        $deviceTx = DeviceTransaction::where('notes', $receiptNo)->where('action', 'RETURNED')->get();
        $accTx    = AccessoryTransaction::where('notes', $receiptNo)->get();
        $simTx    = \App\Models\SimcardTransaction::with('simcard')
                        ->where('notes', $receiptNo)
                        ->where('action', 'RETURNED')
                        ->get();

        $operator = $receipt->returner_name;
        $issued   = \Carbon\Carbon::parse($receipt->created_at);
        $warehouseCode = $receipt->warehouse_code;
        $warehouseName = Warehouse::where('code', $warehouseCode)->value('name') ?? $warehouseCode;
        $reason = $receipt->reason;
        $returnedBy = $receipt->returned_by;

        // Detail perangkat
        $deviceSns = $deviceTx->pluck('device_sn')->all();
        $devices   = Device::whereIn('serial_number', $deviceSns)->get()->keyBy('serial_number');
        $rawDeviceItems = $deviceTx->map(function ($t) use ($devices) {
            $d = $devices->get($t->device_sn);
            return [
                'serial_number' => $t->device_sn,
                'type'          => $d->type ?? '-',
                'model'         => $d->model ?? '-',
            ];
        });

        $deviceGroups = collect($rawDeviceItems)->groupBy(function ($item) {
            return $item['type'] . '|' . $item['model'];
        });

        $deviceItems = [];
        foreach ($deviceGroups as $group) {
            $first = $group->first();
            $sns = [];
            foreach ($group->values() as $index => $item) {
                $num = $index + 1;
                $sns[] = "<div style=\"font-size:11px; white-space:nowrap;\"><span style=\"color:#94a3b8;font-weight:bold;margin-right:3px;\">{$num}.</span> " . $item['serial_number'] . "</div>";
            }
            
            $deviceItems[] = [
                'serial_number' => "<div style=\"display:grid; grid-template-columns:repeat(3, 1fr); gap:4px;\">" . implode("", $sns) . "</div>",
                'type'          => $first['type'],
                'model'         => $first['model'],
                'qty'           => $group->count()
            ];
        }

        // Detail aksesoris
        $accNames = Accessory::whereIn('code', $accTx->pluck('accessory_code')->all())->pluck('name', 'code');
        $accItems = $accTx->map(fn($t) => [
            'code' => $t->accessory_code,
            'name' => $accNames[$t->accessory_code] ?? $t->accessory_code,
            'qty'  => $t->qty,
        ])->values()->toArray();

        // Detail SIM Card
        $rawSimItems = $simTx->map(function ($t) {
            $sim = $t->simcard;
            return [
                'msisdn'   => $t->msisdn ?? ($sim->msisdn ?? '-'),
                'provider' => $sim->provider ?? 'Lainnya',
                'category' => $sim->category ?? 'Lainnya',
            ];
        });

        $simItems = [];
        foreach ($rawSimItems->groupBy('provider') as $provider => $group) {
            foreach ($group->groupBy('category') as $category => $subgroup) {
                $sns = [];
                foreach ($subgroup->values() as $index => $item) {
                    $num = $index + 1;
                    $sns[] = "<div style=\"font-size:11px; white-space:nowrap;\"><span style=\"color:#94a3b8;font-weight:bold;margin-right:3px;\">{$num}.</span> " . $item['msisdn'] . "</div>";
                }

                $simItems[] = [
                    'msisdn'   => "<div style=\"display:grid; grid-template-columns:repeat(3, 1fr); gap:4px;\">" . implode("", $sns) . "</div>",
                    'provider' => $provider,
                    'category' => $category,
                    'qty'      => $subgroup->count(),
                ];
            }
        }

        return view('return_receipt', [
            'receiptNo'     => $receiptNo,
            'issued'        => $issued,
            'operator'      => $operator,
            'reason'        => $reason,
            'returnedBy'    => $returnedBy,
            'deviceItems'   => $deviceItems,
            'accItems'      => $accItems,
            'simItems'      => $simItems,
            'warehouseName' => $warehouseName,
            'autoprint'     => request()->boolean('autoprint'),
        ]);
    }

    // ==========================================
    // SETTINGS
    // ==========================================

    public function settings()
    {
        $currentLogo    = AppSetting::getValue('app_logo');
        $currentFavicon = AppSetting::getValue('app_favicon');
        $themeMode      = AppSetting::getValue('theme_mode', 'dark');
        
        $warehouses = Warehouse::all();
        $thresholds = \App\Models\StockAlertThreshold::all()->groupBy('warehouse_code');
        
        $deviceModels = \App\Models\DeviceModel::all();
        $accessories = \App\Models\Accessory::all();
        $simcards = \App\Models\GsmSimcard::all();

        return view('settings', compact('currentLogo', 'currentFavicon', 'themeMode', 'warehouses', 'thresholds', 'deviceModels', 'accessories', 'simcards'));
    }

    public function updateStockAlerts(Request $request)
    {
        $alerts = $request->input('alerts', []);

        foreach ($alerts as $whCode => $items) {
            foreach ($items as $itemType => $identifiers) {
                foreach ($identifiers as $identifier => $minLevel) {
                    \App\Models\StockAlertThreshold::updateOrCreate(
                        [
                            'warehouse_code' => $whCode,
                            'item_type' => strtoupper($itemType),
                            'item_identifier' => $identifier,
                        ],
                        [
                            'min_stock_level' => (int) $minLevel
                        ]
                    );
                }
            }
        }

        return redirect()->route('settings')->with('success', 'Minimum stock alerts updated successfully.');
    }

    public function updateLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|mimes:png,jpg,jpeg,svg|max:2048']);
        $file     = $request->file('logo');
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads'), $filename);
        AppSetting::setValue('app_logo', 'uploads/' . $filename);
        return redirect()->route('settings')->with('success', 'Logo aplikasi berhasil diperbarui.');
    }

    public function updateFavicon(Request $request)
    {
        $request->validate(['favicon' => 'required|file|mimes:ico,png,jpg,jpeg|max:1024']);
        $file     = $request->file('favicon');
        $filename = 'favicon_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads'), $filename);
        AppSetting::setValue('app_favicon', 'uploads/' . $filename);
        return redirect()->route('settings')->with('success', 'Favicon berhasil diperbarui.');
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme_mode' => 'required|in:dark,light']);
        AppSetting::setValue('theme_mode', $request->theme_mode);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'theme_mode' => $request->theme_mode,
                'message' => 'Tema berhasil diubah ke ' . ucfirst($request->theme_mode) . ' Mode.',
            ]);
        }

        return redirect()->route('settings')->with('success', 'Tema tampilan berhasil diubah ke ' . ucfirst($request->theme_mode) . ' Mode.');
    }

    // ==========================================
    // DATABASE BACKUP (SQL dump, portable PHP-based)
    // ==========================================

    /**
     * Buat dump SQL seluruh database lalu kirim sebagai unduhan (.sql).
     * Implementasi murni PHP (SHOW CREATE TABLE + INSERT) agar tidak
     * bergantung pada lokasi binary mysqldump di server.
     */
    public function downloadBackup()
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        $pdo      = DB::connection()->getPdo();
        $filename = 'dlms_backup_' . date('Ymd_His') . '.sql';

        return response()->streamDownload(function () use ($pdo, $database) {
            $out = fopen('php://output', 'w');

            fwrite($out, "-- ============================================\n");
            fwrite($out, "-- WMS EASTPRO — Backup Database\n");
            fwrite($out, "-- Database : {$database}\n");
            fwrite($out, "-- Dibuat   : " . date('Y-m-d H:i:s') . "\n");
            fwrite($out, "-- ============================================\n\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($out, "SET NAMES utf8mb4;\n\n");

            $tables = DB::select('SHOW TABLES');
            $tableKey = 'Tables_in_' . $database;

            foreach ($tables as $tableRow) {
                $arr   = (array) $tableRow;
                $table = $arr[$tableKey] ?? array_values($arr)[0];

                // Struktur tabel.
                $createRow = DB::select("SHOW CREATE TABLE `{$table}`");
                $createSql = $createRow[0]->{'Create Table'} ?? null;
                if ($createSql) {
                    fwrite($out, "-- Struktur tabel `{$table}`\n");
                    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($out, $createSql . ";\n\n");
                }

                // Isi data.
                $rows = DB::table($table)->get();
                if ($rows->isNotEmpty()) {
                    fwrite($out, "-- Data tabel `{$table}`\n");
                    foreach ($rows as $row) {
                        $data = (array) $row;
                        $cols = implode(', ', array_map(fn ($c) => "`{$c}`", array_keys($data)));
                        $vals = implode(', ', array_map(
                            fn ($v) => is_null($v) ? 'NULL' : $pdo->quote((string) $v),
                            array_values($data)
                        ));
                        fwrite($out, "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n");
                    }
                    fwrite($out, "\n");
                }
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    // ==========================================
    // SAMPLE CSV DOWNLOADS
    // ==========================================

    public function downloadSampleCsv($type)
    {
        $samples = [
            'warehouse'  => ['filename' => 'sample_warehouse.csv',  'header' => ['code', 'name', 'type', 'region'],                      'rows' => [['WH-PUSAT', 'Warehouse Pusat Jakarta', 'PUSAT', ''], ['WH-REG-EAST', 'Warehouse Surabaya', 'REGIONAL', 'EAST'], ['WH-AREA-MLG', 'Warehouse Malang', 'CABANG', 'EAST']]],
            'technician' => ['filename' => 'sample_technician.csv', 'header' => ['code', 'name', 'area'],                      'rows' => [['TECH-01', 'Budi Santoso', 'Malang'], ['TECH-02', 'Andi Prasetyo', 'Kediri'], ['TECH-03', 'Siti Rahayu', 'Jember']]],
            'accessory'  => ['filename' => 'sample_accessory.csv',  'header' => ['code', 'name', 'qty'],                       'rows' => [['ACC-CABLE', 'Power Harness Cable', '100'], ['ACC-RELAY', 'Relay 12V 40A', '50'], ['ACC-FUSE', 'Blade Fuse 15A', '200']]],
            'simcard'    => ['filename' => 'sample_simcard.csv',    'header' => ['msisdn', 'provider', 'category', 'status'],   'rows' => [['6281100001111', 'Telkomsel', 'Telkomsel Halo', 'IN_STOCK'], ['6285200002222', 'Indosat', 'B2B Corporate', 'IN_STOCK'], ['6287800003333', 'XL Axiata', 'XL Biz Priority', 'IN_STOCK']]],
            'customer'   => ['filename' => 'sample_customer.csv',   'header' => ['name', 'phone', 'address', 'pic_name'],   'rows' => [['PT Maju Bersama', '08123456789', 'Jl. Sudirman No 1', 'Budi Santoso'], ['Budi Santoso', '08567890123', 'Jl. Merdeka No 45', '']]],
        ];

        if (!isset($samples[$type])) {
            abort(404, 'Tipe sample CSV tidak ditemukan.');
        }

        $sample  = $samples[$type];
        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $sample['filename'],
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($sample) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $sample['header']);
            foreach ($sample['rows'] as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // GARANSI PERANGKAT (WARRANTY) PAGE
    // ==========================================

    public function warranty()
    {
        // Ambil semua device yang memiliki data garansi/sewa (ownership_status terisi).
        $devices = Device::whereNotNull('ownership_status')
            ->orderByRaw("CASE WHEN warranty_end_date < CURDATE() THEN 0 WHEN warranty_end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 2 END")
            ->orderBy('warranty_end_date')
            ->get();

        // Enrich dengan data customer dari CustomerDevice.
        $customerDeviceMap = \App\Models\CustomerDevice::with('customer')
            ->whereIn('device_id', $devices->pluck('id'))
            ->get()
            ->keyBy('device_id');

        $warehouses = Warehouse::orderBy('name')->get();

        return view('warranty', compact('devices', 'customerDeviceMap', 'warehouses'));
    }

    public function renewWarranty(Request $request)
    {
        $request->validate([
            'device_id'         => 'required|exists:devices,id',
            'warranty_duration' => 'required|integer|min:1',
            'warranty_unit'     => 'required|in:days,weeks,months,years',
        ]);

        $device = Device::findOrFail($request->device_id);

        // Hitung tanggal baru: perpanjang dari hari ini.
        $duration = (int) $request->warranty_duration;
        $unit     = $request->warranty_unit;
        $endDate  = now();
        switch ($unit) {
            case 'days':   $endDate = $endDate->addDays($duration);   break;
            case 'weeks':  $endDate = $endDate->addWeeks($duration);  break;
            case 'months': $endDate = $endDate->addMonths($duration); break;
            case 'years':  $endDate = $endDate->addYears($duration);  break;
        }

        $device->update(['warranty_end_date' => $endDate->toDateString()]);

        return redirect()->route('warranty')->with('success', 'Masa garansi perangkat ' . $device->serial_number . ' berhasil diperpanjang hingga ' . $endDate->format('d M Y') . '.');
    }

    public function stopWarranty(Request $request)
    {
        $request->validate([
            'device_id'      => 'required|exists:devices,id',
            'warehouse_code' => 'required|exists:warehouses,code',
        ]);

        $device = Device::findOrFail($request->device_id);
        $sn = $device->serial_number;
        $oldHolder = $device->current_holder;
        $targetWh = $request->warehouse_code;

        DB::transaction(function () use ($device, $oldHolder, $targetWh) {
            // Unbind Customer Device
            $custDevice = CustomerDevice::where('device_id', $device->id)->whereNull('uninstalled_at')->first();
            if ($custDevice) {
                $custDevice->update(['uninstalled_at' => now()]);
            }

            // Lepas pairing kartu SIM: kembalikan SIM ke stok gudang penerima
            $simId = $device->gsm_simcard_id;
            if ($simId) {
                $sim = GsmSimcard::find($simId);
                if ($sim) {
                    $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $targetWh]);
                    $this->logSimcardTransaction($sim, 'RETURNED', $oldHolder, 'Warehouse ' . $targetWh, $targetWh);
                }
            }

            // Update status alat menjadi RETURNED agar masuk antrean QC/Inspeksi kembali
            $device->update([
                'status'            => 'RETURNED',
                'current_holder'    => 'Warehouse ' . $targetWh,
                'warehouse_code'    => $targetWh,
                'gsm_simcard_id'    => null,
                'warranty_end_date' => null,
            ]);

            $this->logDeviceTransaction($device, 'RETURNED', $oldHolder, $targetWh);
        });

        $this->dispatchStockUpdate();

        return redirect()->route('warranty')->with('success', "Masa aktif perangkat {$sn} telah dihentikan (Uninstall). Perangkat berhasil dikembalikan ke Gudang {$targetWh} untuk dilakukan QC kembali.");
    }

    /**
     * Download data raw scan (belum dicrosscheck) ke Excel.
     */
    public function exportOpnameRaw(Request $request, $id)
    {
        $session = StockOpnameSession::with(['items', 'warehouse', 'startedBy'])->findOrFail($id);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setCellValue('A1', 'DATA SCAN STOCK OPNAME (RAW)');
        $sheet->setCellValue('A2', 'Gudang: ' . ($session->warehouse->name ?? $session->warehouse_code));
        $sheet->setCellValue('A3', 'Tanggal Opname: ' . ($session->opname_date ?? '-'));
        $sheet->setCellValue('A4', 'Operator: ' . ($session->startedBy->name ?? '-'));
        
        $headers = ['No', 'Tanggal Scan', 'Lokasi (Rak/Row)', 'Tipe', 'Kode / SN', 'Nama Barang', 'Qty Fisik'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '6', $h);
            $sheet->getStyle($col . '6')->getFont()->setBold(true);
            $col++;
        }
        
        $row = 7;
        foreach ($session->items as $idx => $item) {
            $sheet->setCellValue('A' . $row, $idx + 1);
            $sheet->setCellValue('B' . $row, $item->created_at->format('Y-m-d H:i:s'));
            $sheet->setCellValue('C' . $row, $item->location_barcode);
            $sheet->setCellValue('D' . $row, strtoupper($item->item_type));
            $sheet->setCellValue('E' . $row, (string) $item->item_code);
            $sheet->setCellValue('F' . $row, $item->item_name);
            $sheet->setCellValue('G' . $row, $item->qty_physical);
            $row++;
        }
        
        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $date = $session->opname_date ? \Carbon\Carbon::parse($session->opname_date)->format('Y-m-d') : now()->format('Y-m-d');
        $fileName = "DataOpname_{$date}.xls";

        $writer = new Xls($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');

        $oldReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        $writer->save($tempFile);
        error_reporting($oldReporting);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Download hasil crosscheck ke Excel.
     */
    public function exportOpnameResult(Request $request, $id)
    {
        $session = StockOpnameSession::with(['warehouse', 'startedBy'])->findOrFail($id);
        
        if (empty($session->crosscheck_result)) {
            return redirect()->back()->with('error', 'Belum ada hasil crosscheck.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setCellValue('A1', 'HASIL CROSSCHECK STOCK OPNAME');
        $sheet->setCellValue('A2', 'Gudang: ' . ($session->warehouse->name ?? $session->warehouse_code));
        $sheet->setCellValue('A3', 'Tanggal Opname: ' . ($session->opname_date ?? '-'));
        $sheet->setCellValue('A4', 'Status: ' . ($session->crosscheck_result['applied'] ?? false ? 'SUDAH DITERAPKAN KE SISTEM' : 'BELUM DITERAPKAN'));
        
        $headers = ['No', 'Tipe', 'Kode / SN', 'Nama Barang', 'Qty Sistem', 'Qty Fisik', 'Selisih', 'Status'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '6', $h);
            $sheet->getStyle($col . '6')->getFont()->setBold(true);
            $col++;
        }
        
        $row = 7;
        $no = 1;
        $details = $session->crosscheck_result['details'] ?? [];
        
        foreach (['device', 'accessory', 'simcard'] as $type) {
            if (isset($details[$type])) {
                foreach ($details[$type] as $item) {
                    $sheet->setCellValue('A' . $row, $no++);
                    $sheet->setCellValue('B' . $row, strtoupper($type));
                    $sheet->setCellValue('C' . $row, (string) $item['code']);
                    $sheet->setCellValue('D' . $row, $item['name']);
                    $sheet->setCellValue('E' . $row, $item['sys_qty']);
                    $sheet->setCellValue('F' . $row, $item['phys_qty']);
                    $sheet->setCellValue('G' . $row, $item['diff']);
                    $sheet->setCellValue('H' . $row, $item['status']);
                    
                    if (str_starts_with($item['status'], 'SELISIH')) {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
                    } else {
                        $sheet->getStyle('H' . $row)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_DARKGREEN);
                    }
                    
                    $row++;
                }
            }
        }
        
        foreach (range('A', 'H') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $date = $session->opname_date ? \Carbon\Carbon::parse($session->opname_date)->format('Y-m-d') : now()->format('Y-m-d');
        $fileName = "HasilCrosscheck_{$date}.xls";

        $writer = new Xls($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');

        $oldReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        $writer->save($tempFile);
        error_reporting($oldReporting);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    // ==========================================
    // OPNAME TEKNISI
    // ==========================================

    /**
     * Simpan hasil generate barcode (lokasi rak) ke tabel warehouse_locations.
     * Dipanggil dari Barcode Generator UI setelah generate barcode.
     */
    public function saveBarcodeLocations(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
            'barcodes'       => 'required|array|min:1',
            'barcodes.*'     => 'required|string|max:100',
        ]);

        $warehouseCode = $request->warehouse_code;
        $saved = 0;
        $skipped = 0;

        foreach ($request->barcodes as $barcode) {
            $barcode = trim($barcode);
            if (!$barcode) continue;

            // Cek apakah sudah ada
            $exists = \App\Models\WarehouseLocation::where('barcode', $barcode)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            // Parse format RAK-XX-ROW-XX
            $parsed = \App\Models\WarehouseLocation::parseBarcode($barcode);

            \App\Models\WarehouseLocation::create([
                'warehouse_code' => $warehouseCode,
                'rack_code'      => $parsed['rack'] ?? $barcode,
                'row_code'       => $parsed['row'] ?? '-',
                'barcode'        => $barcode,
                'description'    => null,
            ]);
            $saved++;
        }

        return response()->json([
            'success' => true,
            'saved'   => $saved,
            'skipped' => $skipped,
            'message' => "Berhasil menyimpan {$saved} lokasi barcode." . ($skipped > 0 ? " {$skipped} sudah ada di database." : ''),
        ]);
    }

    /**
     * Tampilkan halaman Opname Teknisi (AJAX endpoint untuk load data teknisi).
     * Diakses dari tab di halaman stock opname.
     */
    public function opnameTeknisiData(Request $request)
    {
        $warehouseCode = $request->query('warehouse', session('active_warehouse_code'));
        
        $technicians = Technician::with('warehouse')
            ->when($warehouseCode, fn($q) => $q->where('warehouse_code', $warehouseCode))
            ->orderBy('name')
            ->get();

        $allTechNames = $technicians->pluck('name')->toArray();
        $allTechCodes = $technicians->pluck('code')->toArray();

        // 1. Devices & Simcards
        $allDevices = Device::with('simcard')
            ->whereIn('status', ['ISSUED', 'INSTALLED'])
            ->whereIn('current_holder', $allTechNames)
            ->get();

        // 2. Accessories
        $allAccessories = \App\Models\HolderAccessory::with('accessory')
            ->where('holder_type', \App\Models\HolderAccessory::TYPE_TECHNICIAN)
            ->whereIn('holder_code', $allTechCodes)
            ->get();

        $matrix = []; // [ itemName => [ techCode => qty ] ]
        $itemNames = [];

        foreach ($allDevices as $dev) {
            $itemName = $dev->model ?: $dev->type;
            if (!$itemName) continue;
            
            $techCode = $technicians->where('name', $dev->current_holder)->first()->code ?? null;
            if ($techCode) {
                $matrix[$itemName][$techCode] = ($matrix[$itemName][$techCode] ?? 0) + 1;
                $itemNames[$itemName] = true;
            }

            if ($dev->simcard) {
                $simName = ($dev->simcard->provider ?? '') . ' ' . ($dev->simcard->category ?? '');
                $simName = trim($simName);
                if ($simName && $techCode) {
                    $matrix[$simName][$techCode] = ($matrix[$simName][$techCode] ?? 0) + 1;
                    $itemNames[$simName] = true;
                }
            }
        }

        foreach ($allAccessories as $acc) {
            $itemName = $acc->accessory->name ?? $acc->accessory_code;
            if (!$itemName) continue;
            
            $techCode = $acc->holder_code;
            if ($techCode) {
                $matrix[$itemName][$techCode] = ($matrix[$itemName][$techCode] ?? 0) + $acc->qty;
                $itemNames[$itemName] = true;
            }
        }

        $rows = [];
        $items = array_keys($itemNames);
        sort($items);

        foreach ($items as $item) {
            $row = [
                'item' => $item,
                'techs' => []
            ];
            foreach ($technicians as $tech) {
                $row['techs'][$tech->code] = $matrix[$item][$tech->code] ?? 0;
            }
            $rows[] = $row;
        }

        return response()->json([
            'success' => true,
            'technicians' => $technicians->map(fn($t) => ['code' => $t->code, 'name' => $t->name]),
            'rows' => $rows
        ]);
    }

    /**
     * Proses crosscheck Opname Teknisi: bandingkan jumlah fisik yang diinput admin
     * dengan data sistem per teknisi.
     */
    public function crosscheckOpnameTeknisi(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
            'counts'         => 'required|array',   // [itemName => [tech_code => qty_physical]]
        ]);

        $warehouseCode = $request->warehouse_code;
        $counts = $request->counts;

        $technicians = Technician::with('warehouse')
            ->where('warehouse_code', $warehouseCode)
            ->orderBy('name')
            ->get();

        $allTechNames = $technicians->pluck('name')->toArray();
        $allTechCodes = $technicians->pluck('code')->toArray();

        // 1. Devices & Simcards
        $allDevices = Device::with('simcard')
            ->whereIn('status', ['ISSUED', 'INSTALLED'])
            ->whereIn('current_holder', $allTechNames)
            ->get();

        // 2. Accessories
        $allAccessories = \App\Models\HolderAccessory::with('accessory')
            ->where('holder_type', \App\Models\HolderAccessory::TYPE_TECHNICIAN)
            ->whereIn('holder_code', $allTechCodes)
            ->get();

        $matrix = []; // [ itemName => [ techCode => sysQty ] ]
        $itemNames = [];

        foreach ($allDevices as $dev) {
            $itemName = $dev->model ?: $dev->type;
            if (!$itemName) continue;
            
            $techCode = $technicians->where('name', $dev->current_holder)->first()->code ?? null;
            if ($techCode) {
                $matrix[$itemName][$techCode] = ($matrix[$itemName][$techCode] ?? 0) + 1;
                $itemNames[$itemName] = true;
            }

            if ($dev->simcard) {
                $simName = ($dev->simcard->provider ?? '') . ' ' . ($dev->simcard->category ?? '');
                $simName = trim($simName);
                if ($simName && $techCode) {
                    $matrix[$simName][$techCode] = ($matrix[$simName][$techCode] ?? 0) + 1;
                    $itemNames[$simName] = true;
                }
            }
        }

        foreach ($allAccessories as $acc) {
            $itemName = $acc->accessory->name ?? $acc->accessory_code;
            if (!$itemName) continue;
            
            $techCode = $acc->holder_code;
            if ($techCode) {
                $matrix[$itemName][$techCode] = ($matrix[$itemName][$techCode] ?? 0) + $acc->qty;
                $itemNames[$itemName] = true;
            }
        }

        $items = array_keys($itemNames);
        sort($items);

        $results = [];
        $summary = [
            'total_items' => count($items),
            'sesuai' => 0,
            'selisih' => 0,
        ];

        foreach ($items as $item) {
            $row = [
                'item' => $item,
                'techs' => [],
                'status' => 'SESUAI'
            ];
            $hasDiff = false;

            foreach ($technicians as $tech) {
                $sysQty = $matrix[$item][$tech->code] ?? 0;
                $physQty = isset($counts[$item][$tech->code]) ? (int)$counts[$item][$tech->code] : 0;
                $diff = $physQty - $sysQty;

                if ($diff !== 0) {
                    $hasDiff = true;
                }

                $row['techs'][$tech->code] = [
                    'sys_qty' => $sysQty,
                    'phys_qty' => $physQty,
                    'diff' => $diff
                ];
            }

            $row['status'] = $hasDiff ? 'SELISIH' : 'SESUAI';
            
            if ($hasDiff) {
                $summary['selisih']++;
            } else {
                $summary['sesuai']++;
            }

            $results[] = $row;
        }

        return response()->json([
            'success' => true,
            'technicians' => $technicians->map(fn($t) => ['code' => $t->code, 'name' => $t->name]),
            'results' => $results,
            'summary' => $summary,
        ]);
    }

    /**
     * Export hasil Opname Teknisi ke Excel.
     */
    public function exportOpnameTeknisi(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
            'results_json'   => 'required|string',
        ]);

        $warehouseCode = $request->warehouse_code;
        $resultsData = json_decode($request->results_json, true);
        if (!$resultsData || !isset($resultsData['technicians']) || !isset($resultsData['results'])) {
            return redirect()->back()->with('error', 'Data hasil opname tidak valid.');
        }

        $technicians = $resultsData['technicians'];
        $rows = $resultsData['results'];

        $warehouse = Warehouse::where('code', $warehouseCode)->first();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Opname Teknisi');

        // Header info
        $waktuOpname = now()->format('d M Y H:i:s');
        $sheet->setCellValue('A1', 'LAPORAN OPNAME TEKNISI (MATRIX)');
        $sheet->setCellValue('A2', 'Gudang: ' . ($warehouse->name ?? $warehouseCode));
        $sheet->setCellValue('A3', 'Waktu Opname: ' . $waktuOpname);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Table header
        $headers = ['Keterangan'];
        foreach ($technicians as $tech) {
            $headers[] = strtoupper($tech['name']);
        }
        $headers[] = 'TOTAL';

        $colIndex = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($colIndex, 5, $h);
            $sheet->getStyleByColumnAndRow($colIndex, 5)->getFont()->setBold(true);
            $colIndex++;
        }

        $rowNum = 6;
        foreach ($rows as $r) {
            $colIndex = 1;
            // Keterangan
            $sheet->setCellValueByColumnAndRow($colIndex++, $rowNum, $r['item']);
            
            $total = 0;
            // Teknisi Qty
            foreach ($technicians as $tech) {
                $qty = $r['techs'][$tech['code']]['phys_qty'] ?? 0;
                if ($qty > 0) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $rowNum, $qty);
                }
                $total += $qty;
                $colIndex++;
            }
            
            // TOTAL
            if ($total > 0) {
                $sheet->setCellValueByColumnAndRow($colIndex, $rowNum, $total);
                $sheet->getStyleByColumnAndRow($colIndex, $rowNum)->getFont()->setBold(true);
            }
            
            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $fileName = 'OpnameTeknisi_' . now()->format('Y-m-d_His') . '.xls';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'optek_');

        $oldReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        $writer->save($tempFile);
        error_reporting($oldReporting);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }
}
