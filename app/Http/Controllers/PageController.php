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
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

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
        if (!$warehouseCode || !$request->has('acc_types')) {
            return null;
        }

        foreach ($request->acc_types as $idx => $accCode) {
            $qty = intval($request->acc_qtys[$idx] ?? 0);
            if ($qty <= 0) continue;

            $stock = (int) (WarehouseAccessory::where('warehouse_code', $warehouseCode)
                ->where('accessory_code', $accCode)
                ->value('qty') ?? 0);

            if ($qty > $stock) {
                $name = Accessory::where('code', $accCode)->value('name') ?? $accCode;
                return "Qty aksesoris \"{$name}\" ({$qty}) melebihi stok gudang asal ({$stock}).";
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
            if (!$acc) continue;

            // Stok per-gudang adalah sumber kebenaran; qty global otomatis
            // tersinkron di dalam adjustWarehouseAccessoryStock().
            if ($warehouseCode) {
                $direction = in_array($action, ['RECEIVING', 'RETURN']) ? 'increment' : 'decrement';
                $this->adjustWarehouseAccessoryStock($warehouseCode, $accCode, $qty, $direction);
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
        return view('select_warehouse');
    }

    public function setWarehouse(Request $request)
    {
        $request->validate([
            'warehouse_code' => 'required|exists:warehouses,code',
        ]);

        $wh = Warehouse::where('code', $request->warehouse_code)->first();

        session([
            'active_warehouse_code' => $wh->code,
            'active_warehouse_name' => $wh->name,
            'active_warehouse_type' => $wh->type,
        ]);

        return redirect()->route('dashboard')->with('success', "Gudang aktif disetel ke: {$wh->name} ({$wh->code})");
    }

    // ==========================================
    // DASHBOARD
    // ==========================================

    public function dashboard(Request $request)
    {
        $service = new \App\Services\DashboardInsightService();

        // Resolve the active view: 'global' or a specific warehouse code.
        // The Command Center is a global overview, so it defaults to Global
        // (showing all warehouses). Users can still scope to a single warehouse
        // via the top-right dropdown (?view=CODE).
        $view  = $request->query('view', 'global');
        $scope = $view === 'global' ? null : $view;

        $metrics      = $service->getGlobalMetrics($scope);
        $insights     = $service->getInsights($scope);
        $burnRate     = $service->getBurnRateSeries($scope);
        $distribution = $service->getDistribution($scope);

        $recent_tx = DeviceTransaction::when($scope, function ($q) use ($scope) {
                $q->where(function ($sub) use ($scope) {
                    $sub->where('from_location', $scope)
                        ->orWhere('to_location', $scope);
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

        $warehouses = Warehouse::pluck('name', 'code')->toArray();

        // ----- Stok di lapangan per AREA teknisi -----
        // Tidak semua area punya gudang/cabang; barang bisa langsung dipegang
        // teknisi. Kita kelompokkan saldo lapangan berdasarkan area teknisi.
        $areaStock = $this->getTechnicianAreaStock();

        // Alert Center terintegrasi: peringatan stok minimum + transfer masuk
        // yang masih menunggu diterima di gudang (untuk Priority Stream & feed).
        $stockAlerts = $service->getStockAlerts($scope);
        $pendingIncoming = DeliveryOrder::where('status', 'IN_TRANSIT')
            ->when($scope, fn ($q) => $q->where('to_warehouse_code', $scope))
            ->count();

        return view('dashboard', compact('metrics', 'insights', 'recent_tx', 'burnRate', 'distribution', 'warehouses', 'view', 'stockAlerts', 'pendingIncoming', 'areaStock'));
    }

    /**
     * Agregasi saldo barang yang dipegang teknisi, dikelompokkan per AREA.
     * Device & GSM diambil dari perangkat berstatus ISSUED (dipegang teknisi),
     * aksesoris dari saldo holder bertipe TECHNICIAN.
     *
     * @return array<string, array{devices:int, sim:int, accessories:int}>
     */
    private function getTechnicianAreaStock(): array
    {
        $areaByName = Technician::pluck('area', 'name');
        $areaByCode = Technician::pluck('area', 'code');

        $agg = [];
        $bucket = function (string $area) use (&$agg): void {
            if (!isset($agg[$area])) {
                $agg[$area] = ['devices' => 0, 'sim' => 0, 'accessories' => 0];
            }
        };

        // Device & GSM yang dipegang teknisi (status ISSUED).
        Device::where('status', 'ISSUED')
            ->get(['current_holder', 'gsm_simcard_id'])
            ->each(function ($d) use (&$agg, $areaByName, $bucket) {
                $holder = (string) $d->current_holder;
                $name = str_starts_with($holder, 'Technician: ')
                    ? trim(substr($holder, strlen('Technician: ')))
                    : null;
                $area = ($name && !empty($areaByName[$name])) ? $areaByName[$name] : 'Tanpa Area';
                $bucket($area);
                $agg[$area]['devices']++;
                if ($d->gsm_simcard_id) {
                    $agg[$area]['sim']++;
                }
            });

        // Aksesoris yang dipegang teknisi.
        HolderAccessory::where('holder_type', HolderAccessory::TYPE_TECHNICIAN)
            ->where('qty', '>', 0)
            ->get(['holder_code', 'qty'])
            ->each(function ($h) use (&$agg, $areaByCode, $bucket) {
                $area = !empty($areaByCode[$h->holder_code]) ? $areaByCode[$h->holder_code] : 'Tanpa Area';
                $bucket($area);
                $agg[$area]['accessories'] += (int) $h->qty;
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
            case 'stock_baru':
            case 'stock_bekas':
                $q = Device::where('status', 'IN_STOCK');
                $deviceScope($q);
                if ($metric === 'stock_baru') $q->where('unit_condition', 'BARU');
                if ($metric === 'stock_bekas') $q->where('unit_condition', 'BEKAS');
                $title = $metric === 'stock_baru' ? 'Perangkat IN STOCK — Kondisi BARU'
                       : ($metric === 'stock_bekas' ? 'Perangkat IN STOCK — Kondisi BEKAS' : 'Perangkat IN STOCK');
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
        $simProviders = GsmSimcard::query()->whereNotNull('provider')->distinct()->pluck('provider')->toArray();
        $simProviders = array_values(array_unique(array_merge($simProviders, ['Telkomsel', 'Indosat', 'XL', 'Tri', 'Smartfren'])));
        sort($simProviders);

        return view('receiving', compact(
            'warehouses', 'deviceModels', 'accessories',
            'suggestedDevices', 'suggestedAccessories', 'poolSimcards', 'simProviders'
        ));
    }

    public function postReceiving(Request $request)
    {
        $request->validate([
            'warehouse' => 'required|exists:warehouses,code',
            'sns'       => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->sns as $index => $sn) {
                $imei  = $request->imeis[$index] ?? '358' . rand(100000000000, 999999999999);
                $type  = $request->types[$index] ?? 'GPS Tracker';
                $model = $request->models[$index] ?? 'Standard VT-Model';
                $cond  = strtoupper($request->conditions[$index] ?? 'BARU') === 'BEKAS' ? 'BEKAS' : 'BARU';

                if (Device::where('serial_number', $sn)->exists()) {
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
                ]);

                $this->logDeviceTransaction($device, 'RECEIVING', 'Supplier', $request->warehouse, 'Warehouse Operator', 'Scanner-HID-01', 'Kondisi: ' . $cond . ' | Menunggu QC');
            }
        });

        $this->dispatchStockUpdate();
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
            'warehouses', 'delivery_orders', 'devices', 'accessories',
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
        $technicians = Technician::pluck('name', 'code')->toArray();
        $technicianAreas = Technician::pluck('area', 'code')->toArray();
        $customers   = Customer::pluck('name', 'id')->toArray();
        $devices     = Device::where('status', 'IN_STOCK')->get()->toArray();
        $accessories = Accessory::all()->keyBy('code')->toArray();
        // SIM IN_STOCK yang sudah ada di gudang (pool tanpa gudang tidak bisa dipasang).
        $simcards    = GsmSimcard::where('status', 'IN_STOCK')->whereNotNull('warehouse_code')->get()->toArray();
        $warehouses  = Warehouse::pluck('name', 'code')->toArray();

        // Saldo aksesoris per gudang untuk filter & batas qty di UI.
        $warehouseAccessories = WarehouseAccessory::all()
            ->groupBy('warehouse_code')
            ->map(fn($items) => $items->keyBy('accessory_code')->map(fn($item) => $item->qty))
            ->toArray();

        // AI Suggestions
        $suggestedAccessories = $this->getAccessorySuggestions('OUT');

        return view('issue', compact('technicians', 'technicianAreas', 'customers', 'devices', 'accessories', 'simcards', 'suggestedAccessories', 'warehouses', 'warehouseAccessories'));
    }

    public function postIssue(Request $request)
    {
        $request->validate([
            'target_type' => 'required|in:technician,customer',
            'technician'  => 'required_if:target_type,technician|exists:technicians,code',
            'customer'    => 'required_if:target_type,customer|exists:customers,id',
            'sns'         => 'nullable|array',
            'warehouse'   => 'required|exists:warehouses,code',
        ]);

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

        // Status saat serah terima = ISSUED (stok berpindah ke pemegang/teknisi/customer,
        // tetapi BELUM tentu terpasang). Status INSTALLED hanya diberikan saat perangkat
        // benar-benar dipasang. Dengan ini admin gudang tetap bisa memantau "stok di customer".
        $deviceStatus = 'ISSUED';
        $simStatus    = 'ISSUED';

        DB::transaction(function () use ($request, $hasSns, $receiptNo, $deviceStatus, $simStatus) {
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

                    // Integritas: hanya proses device yang benar-benar ada di gudang asal terpilih.
                    if ($device->warehouse_code !== $request->warehouse) {
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
                        $sim = GsmSimcard::where('msisdn', $simMsisdn)
                            ->where('status', 'IN_STOCK')
                            ->where('warehouse_code', $request->warehouse)
                            ->first();
                        if ($sim) {
                            $fromWh = $sim->warehouse_code;
                            // SIM keluar dari stok gudang saat terpasang ke perangkat.
                            $sim->update(['status' => 'INSTALLED', 'warehouse_code' => null]);
                            $device->gsm_simcard_id = $sim->id;
                            $this->logSimcardTransaction($sim, 'INSTALLED', $fromWh ?? 'Warehouse', $holderName, $fromWh);
                        }
                    }

                    $device->update([
                        'status'         => $deviceStatus,
                        'current_holder' => $holderName,
                    ]);

                    if ($customerId) {
                        CustomerDevice::create([
                            'customer_id'  => $customerId,
                            'device_id'    => $device->id,
                            'installed_at' => now(),
                        ]);
                    }

                    $this->logDeviceTransaction($device, 'ISSUED', $device->warehouse_code, $holderName, 'Warehouse Operator', 'Scanner-HID-01', $receiptNo);
                }
            }

            // Serah terima kartu GSM mandiri (tanpa device) — hanya SIM IN_STOCK di gudang asal.
            foreach ((array) $request->issue_sim_ids as $simId) {
                if (!$simId) continue;
                $sim = GsmSimcard::where('id', $simId)
                    ->where('status', 'IN_STOCK')
                    ->where('warehouse_code', $request->warehouse)
                    ->first();
                if (!$sim) continue;

                $fromWh = $sim->warehouse_code;
                // SIM keluar dari stok gudang saat diserahkan ke teknisi/customer.
                $sim->update(['status' => $simStatus, 'warehouse_code' => null]);
                $this->logSimcardTransaction($sim, $simStatus, $fromWh ?? 'Warehouse', $holderName, $fromWh, $receiptNo);
            }

            // Process accessories with per-warehouse stock + saldo holder
            $this->processAccessoryQtyForm($request, 'OUT', $warehouseCode, 'Warehouse', $holderName, $technicianCode, $receiptNo, $holderType, $holderCode, $holderCleanName);
        });

        $this->dispatchStockUpdate();

        // Auto-generate tanda terima: arahkan langsung ke dokumen yang bisa dicetak / disimpan PDF.
        return redirect()->route('receipt.show', ['receiptNo' => $receiptNo, 'autoprint' => 1]);
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

    public function postReturn(Request $request)
    {
        $request->validate([
            'sns'              => 'nullable|array',
            'warehouse'        => 'required|exists:warehouses,code',
            'return_from_type' => 'nullable|in:technician,customer',
            'return_technician'=> 'nullable|exists:technicians,code',
            'return_customer'  => 'nullable|exists:customers,id',
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

        // Tentukan asal pengembalian (untuk pelacakan saldo aksesoris di teknisi/customer).
        // Opsional: jika tidak dipilih, default 'Field' tanpa atribusi pemegang.
        $accFrom    = 'Field';
        $accTechCode = null;
        $holderType      = null;
        $holderCode      = null;
        $holderCleanName = null;
        if ($request->return_from_type === 'technician' && $request->filled('return_technician')) {
            $tech = Technician::find($request->return_technician);
            if ($tech) {
                $accFrom     = 'Technician: ' . $tech->name;
                $accTechCode = $tech->code;
                $holderType      = HolderAccessory::TYPE_TECHNICIAN;
                $holderCode      = $tech->code;
                $holderCleanName = $tech->name;
            }
        } elseif ($request->return_from_type === 'customer' && $request->filled('return_customer')) {
            $cust = Customer::find($request->return_customer);
            if ($cust) {
                $accFrom = 'Customer: ' . $cust->name;
                $holderType      = HolderAccessory::TYPE_CUSTOMER;
                $holderCode      = (string) $cust->id;
                $holderCleanName = $cust->name;
            }
        }

        DB::transaction(function () use ($request, $hasSns, $accFrom, $accTechCode, $holderType, $holderCode, $holderCleanName) {
            if ($hasSns) {
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
                            $this->logSimcardTransaction($sim, 'RETURNED', $oldHolder, 'Warehouse ' . $request->warehouse, $request->warehouse);
                        }
                    }

                    $device->update([
                        'status'         => 'RETURNED',
                        'current_holder' => 'Warehouse ' . $request->warehouse,
                        'warehouse_code' => $request->warehouse,
                        'gsm_simcard_id' => null,
                    ]);

                    $this->logDeviceTransaction($device, 'RETURNED', $oldHolder, $request->warehouse);
                }
            }

            // Return kartu GSM mandiri (yang diserahkan tanpa device) kembali ke stok gudang.
            foreach ((array) $request->return_sim_ids as $simId) {
                if (!$simId) continue;
                $sim = GsmSimcard::where('id', $simId)->whereIn('status', ['ISSUED', 'INSTALLED'])->first();
                if (!$sim) continue;

                $sim->update(['status' => 'IN_STOCK', 'warehouse_code' => $request->warehouse]);
                $this->logSimcardTransaction($sim, 'RETURNED', $accFrom, 'Warehouse ' . $request->warehouse, $request->warehouse);
            }

            // Aksesoris kembali ke gudang; saldo holder (teknisi/customer) berkurang
            // bila asal pengembalian dipilih.
            $this->processAccessoryQtyForm($request, 'RETURN', $request->warehouse, $accFrom, $request->warehouse, $accTechCode, null, $holderType, $holderCode, $holderCleanName);
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
    // STOCK OPNAME (Manual Accessory Stock Correction) - Priority 1
    // ==========================================

    /**
     * Show the per-warehouse accessory stock-take (opname) form.
     */
    public function stockOpname(Request $request)
    {
        $warehouses = Warehouse::pluck('name', 'code')->toArray();

        // Default to the active session warehouse; fall back to the first one.
        $selected = $request->query('warehouse', session('active_warehouse_code'));
        if (!array_key_exists($selected, $warehouses)) {
            $selected = array_key_first($warehouses);
        }

        $accessories = Accessory::orderBy('name')->get();
        $whStock = WarehouseAccessory::where('warehouse_code', $selected)->pluck('qty', 'accessory_code');

        $recentAdjustments = AccessoryTransaction::where('action', 'ADJUSTMENT')
            ->where('to_location', $selected)
            ->latest()->take(10)->get();

        return view('stock_opname', compact('warehouses', 'selected', 'accessories', 'whStock', 'recentAdjustments'));
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

    // ==========================================
    // DEVICE ADJUSTMENT (Manual Unit Correction) - Priority 2
    // ==========================================

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
        $warning = null;

        if (!empty($q)) {
            if (strlen($q) < 3) {
                $warning = 'Kata kunci pencarian terlalu pendek. Silakan masukkan minimal 3 karakter untuk pencarian cepat.';
            } else {
                $results = Device::where('serial_number', 'like', "{$q}%")
                    ->orWhere('imei', 'like', "{$q}%")
                    ->orWhere('status', $q)
                    ->orWhere('type', $q)
                    ->orWhere('warehouse_code', $q)
                    ->orWhere('current_holder', 'like', "%{$q}%")
                    ->get()
                    ->toArray();

                $audit_trails = DeviceTransaction::where('device_sn', 'like', "{$q}%")
                    ->latest()
                    ->get()
                    ->map(fn($tx) => [
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
                    ])
                    ->toArray();
            }
        }

        $warehouses = Warehouse::pluck('name', 'code')->toArray();

        return view('search', compact('results', 'audit_trails', 'q', 'warning', 'warehouses'));
    }

    // ==========================================
    // MASTER DATA CRUD
    // ==========================================

    public function masterData()
    {
        $warehouses   = Warehouse::all()->toArray();
        $technicians  = Technician::all()->toArray();
        $accessories  = Accessory::all()->toArray();
        $simcards     = GsmSimcard::all()->toArray();
        $deviceModels = DeviceModel::all()->toArray();
        $customers    = Customer::all()->toArray();

        return view('master_data', compact('warehouses', 'technicians', 'accessories', 'simcards', 'deviceModels', 'customers'));
    }

    public function storeWarehouse(Request $request)
    {
        $request->validate(['code' => 'required|string', 'name' => 'required|string', 'type' => 'required|string']);
        Warehouse::updateOrCreate(['code' => $request->code], ['name' => $request->name, 'type' => $request->type]);
        return redirect()->route('master_data', ['tab' => $request->input('tab', 'warehouse')])->with('success', 'Gudang berhasil disimpan.');
    }

    public function deleteWarehouse($code)
    {
        Warehouse::where('code', $code)->delete();
        return redirect()->route('master_data', ['tab' => 'warehouse'])->with('success', 'Gudang berhasil dihapus.');
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

    public function storeCustomer(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'phone'       => 'nullable|string',
            'address'     => 'nullable|string',
            'contract_no' => 'nullable|string',
        ]);

        Customer::updateOrCreate(
            ['id' => $request->id],
            ['name' => $request->name, 'phone' => $request->phone, 'address' => $request->address, 'contract_no' => $request->contract_no]
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
        $request->validate(['code' => 'required|string', 'name' => 'required|string', 'qty' => 'required|integer|min:0']);

        $exists = Accessory::where('code', $request->code)->exists();

        if ($exists) {
            // Edit katalog: hanya perbarui nama. Stok dikelola lewat menu
            // operasional (Receiving / Stock Opname), bukan dari editor master data,
            // agar qty global tetap = total stok seluruh gudang.
            Accessory::where('code', $request->code)->update(['name' => $request->name]);
        } else {
            // Item baru: stok awal di-seed ke gudang pusat lalu qty global
            // direkonsiliasi dari total gudang.
            Accessory::create(['code' => $request->code, 'name' => $request->name, 'qty' => 0]);
            $initial = intval($request->qty);
            if ($initial > 0) {
                $this->adjustWarehouseAccessoryStock('WH-PUSAT', $request->code, $initial, 'increment');
            }
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
        Accessory::create(['code' => $code, 'name' => $name, 'qty' => 0]);
        if ($qty > 0) {
            $this->adjustWarehouseAccessoryStock('WH-PUSAT', $code, $qty, 'increment');
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
            'msisdn'   => 'required|string',
            'provider' => 'required|string',
            'category' => 'required|string',
            'status'   => 'required|string',
        ]);

        GsmSimcard::updateOrCreate(
            ['id' => $request->id],
            ['msisdn' => $request->msisdn, 'provider' => $request->provider, 'category' => $request->category, 'status' => $request->status]
        );

        return redirect()->route('master_data', ['tab' => $request->input('tab', 'simcard')])->with('success', 'Kartu SIM GSM berhasil disimpan.');
    }

    public function deleteSimcard($id)
    {
        GsmSimcard::where('id', $id)->delete();
        return redirect()->route('master_data', ['tab' => 'simcard'])->with('success', 'Kartu SIM GSM berhasil dihapus.');
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
                    'warehouse'  => Warehouse::updateOrCreate(['code' => $data[0]], ['name' => $data[1] ?? '', 'type' => strtoupper($data[2] ?? 'CABANG')]),
                    'technician' => Technician::updateOrCreate(['code' => $data[0]], ['name' => $data[1] ?? '', 'area' => $data[2] ?? null]),
                    'accessory'  => $this->importAccessoryRow($data[0], $data[1] ?? '', intval($data[2] ?? 0)),
                    'simcard'    => GsmSimcard::updateOrCreate(['msisdn' => $data[0]], ['provider' => $data[1] ?? 'Unknown', 'category' => $data[2] ?? 'General', 'status' => $data[3] ?? 'IN_STOCK']),
                    'customer'   => Customer::updateOrCreate(['name' => $data[0]], ['phone' => $data[1] ?? null, 'address' => $data[2] ?? null, 'contract_no' => $data[3] ?? null]),
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
        $filters = $reports->resolveFilters($request->only(['from', 'to', 'period', 'warehouse']));

        $statusStats = [
            'IN_STOCK'   => Device::where('status', 'IN_STOCK')->count(),
            'IN_TRANSIT' => Device::where('status', 'IN_TRANSIT')->count(),
            'ISSUED'     => Device::where('status', 'ISSUED')->count(),
            'INSTALLED'  => Device::where('status', 'INSTALLED')->count(),
            'REPAIR'     => Device::where('status', 'REPAIR')->count(),
            'SCRAP'      => Device::where('status', 'SCRAP')->count(),
        ];

        $data = [
            'filters'        => $filters,
            'warehouses'     => $reports->warehouseOptions(),
            'statusStats'    => $statusStats,
            'executive'      => $reports->executiveSummary($filters),
            'movement'       => $reports->inOutMovement($filters),
            'movementDaily'  => $reports->dailyMovement($filters),
            'technicianStock'=> $reports->technicianStock(),
            'customerStock'  => $reports->customerStock(),
            'aging'          => $reports->aging($filters['warehouse']),
            'quality'        => $reports->quality($filters),
            'adjustment'     => $reports->adjustmentAudit($filters),
            'stockcard'      => $reports->stockCard($filters),
            // Untuk view: tampilkan tanggal terformat
            'fromDate'       => $filters['from']->format('Y-m-d'),
            'toDate'         => $filters['to']->format('Y-m-d'),
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

        $view  = $request->query('warehouse', session('active_warehouse_code') ?: 'global');
        $scope = $view === 'global' ? null : $view;

        $insights    = $service->getInsights($scope);
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
        $deviceTx = DeviceTransaction::where('notes', $receiptNo)->where('action', 'ISSUED')->get();
        $accTx    = AccessoryTransaction::where('notes', $receiptNo)->get();

        if ($deviceTx->isEmpty() && $accTx->isEmpty()) {
            abort(404, 'Tanda terima tidak ditemukan.');
        }

        $first      = $deviceTx->first() ?? $accTx->first();
        $holderRaw  = $first->to_location ?? '';
        $issuedAt   = $first->created_at;
        $operator   = $deviceTx->first()->operator ?? 'Warehouse Operator';

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
                if (!empty($cust->contract_no)) $recipientMeta['No. Kontrak'] = $cust->contract_no;
            }
        }

        // Detail perangkat
        $deviceSns = $deviceTx->pluck('device_sn')->all();
        $devices   = Device::whereIn('serial_number', $deviceSns)->get()->keyBy('serial_number');
        $deviceItems = $deviceTx->map(function ($t) use ($devices) {
            $d = $devices->get($t->device_sn);
            return [
                'serial_number' => $t->device_sn,
                'type'          => $d->type ?? '-',
                'model'         => $d->model ?? '-',
                'imei'          => $d->imei ?? '-',
                'vehicle_plate' => $d->vehicle_plate ?? '-',
            ];
        })->values()->toArray();

        // Detail aksesoris
        $accNames = Accessory::whereIn('code', $accTx->pluck('accessory_code')->all())->pluck('name', 'code');
        $accItems = $accTx->map(fn($t) => [
            'code' => $t->accessory_code,
            'name' => $accNames[$t->accessory_code] ?? $t->accessory_code,
            'qty'  => $t->qty,
        ])->values()->toArray();

        $warehouseCode = $deviceTx->first()->from_location ?? $first->from_location ?? null;
        $warehouseName = $warehouseCode ? (Warehouse::where('code', $warehouseCode)->value('name') ?? $warehouseCode) : '-';

        return view('receipt', [
            'receiptNo'     => $receiptNo,
            'issuedAt'      => $issuedAt,
            'operator'      => $operator,
            'recipientType' => $recipientType,
            'recipientName' => $recipientName,
            'recipientMeta' => $recipientMeta,
            'deviceItems'   => $deviceItems,
            'accItems'      => $accItems,
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
            'warehouse'  => ['filename' => 'sample_warehouse.csv',  'header' => ['code', 'name', 'type'],                      'rows' => [['WH-PUSAT', 'Warehouse Pusat Jakarta', 'PUSAT'], ['WH-SBY', 'Warehouse Surabaya', 'REGIONAL'], ['WH-BDG', 'Warehouse Bandung', 'CABANG']]],
            'technician' => ['filename' => 'sample_technician.csv', 'header' => ['code', 'name', 'area'],                      'rows' => [['TECH-01', 'Budi Santoso', 'Malang'], ['TECH-02', 'Andi Prasetyo', 'Kediri'], ['TECH-03', 'Siti Rahayu', 'Jember']]],
            'accessory'  => ['filename' => 'sample_accessory.csv',  'header' => ['code', 'name', 'qty'],                       'rows' => [['ACC-CABLE', 'Power Harness Cable', '100'], ['ACC-RELAY', 'Relay 12V 40A', '50'], ['ACC-FUSE', 'Blade Fuse 15A', '200']]],
            'simcard'    => ['filename' => 'sample_simcard.csv',    'header' => ['msisdn', 'provider', 'category', 'status'],   'rows' => [['6281100001111', 'Telkomsel', 'Telkomsel Halo', 'IN_STOCK'], ['6285200002222', 'Indosat', 'B2B Corporate', 'IN_STOCK'], ['6287800003333', 'XL Axiata', 'XL Biz Priority', 'IN_STOCK']]],
            'customer'   => ['filename' => 'sample_customer.csv',   'header' => ['name', 'phone', 'address', 'contract_no'],   'rows' => [['PT Maju Bersama', '08123456789', 'Jl. Sudirman No 1', 'KONTRAK-2026-001'], ['Budi Santoso', '08567890123', 'Jl. Merdeka No 45', '']]],
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
}
