<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceInspection;
use App\Models\DeviceTransaction;
use App\Models\AccessoryTransaction;
use App\Models\Accessory;
use App\Models\GsmSimcard;
use App\Models\SimcardTransaction;
use App\Models\HolderAccessory;
use App\Models\Technician;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /** Aksi transaksi yang dianggap barang MASUK ke gudang. */
    public const IN_ACTIONS = ['RECEIVING', 'TRANSFER_IN', 'RETURNED', 'QC_PASSED'];

    /** Aksi transaksi yang dianggap barang KELUAR dari gudang. */
    public const OUT_ACTIONS = ['ISSUED', 'TRANSFER_OUT', 'DISPOSED', 'INSTALLED', 'QC_FAILED'];

    /** Aksi MASUK/KELUAR untuk aksesoris (label aksi berbeda dengan device). */
    public const ACC_IN_ACTIONS  = ['RECEIVING', 'TRANSFER_IN', 'RETURN', 'RETURNED'];
    public const ACC_OUT_ACTIONS = ['OUT', 'ISSUED', 'TRANSFER_OUT', 'DISPOSED'];

    /** Aksi MASUK/KELUAR untuk kartu GSM. */
    public const SIM_IN_ACTIONS  = ['RECEIVING', 'TRANSFER_IN', 'RETURNED'];
    public const SIM_OUT_ACTIONS = ['TRANSFER_OUT', 'ISSUED', 'INSTALLED'];

    /**
     * Normalisasi filter dari request menjadi struktur baku.
     */
    public function resolveFilters(array $input): array
    {
        $to = !empty($input['to']) ? Carbon::parse($input['to'])->endOfDay() : Carbon::now()->endOfDay();
        $from = !empty($input['from']) ? Carbon::parse($input['from'])->startOfDay() : (clone $to)->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [(clone $to)->startOfDay(), (clone $from)->endOfDay()];
        }

        $periodInput = $input['period'] ?? 'day';
        $period = in_array($periodInput, ['day', 'week', 'month'], true) ? $periodInput : 'day';
        $warehouse = !empty($input['warehouse']) && $input['warehouse'] !== 'all' ? $input['warehouse'] : null;

        return compact('from', 'to', 'period', 'warehouse');
    }

    private function scopeWarehouse($query, ?string $warehouse, array $columns = ['from_location', 'to_location'])
    {
        if (!$warehouse) {
            return $query;
        }

        return $query->where(function ($q) use ($warehouse, $columns) {
            foreach ($columns as $col) {
                $q->orWhere($col, $warehouse);
            }
        });
    }

    private function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'month' => $date->format('Y-m'),
            'week'  => $date->format('o-\WW'),
            default => $date->format('Y-m-d'),
        };
    }

    private function bucketLabel(string $key, string $period): string
    {
        return match ($period) {
            'month' => Carbon::createFromFormat('Y-m', $key)->translatedFormat('M Y'),
            'week'  => 'Minggu ' . substr($key, -2) . ' / ' . substr($key, 0, 4),
            default => Carbon::createFromFormat('Y-m-d', $key)->translatedFormat('d M'),
        };
    }

    /**
     * A. Mutasi barang IN vs OUT per periode (device transactions).
     */
    public function inOutMovement(array $f): array
    {
        $query = DeviceTransaction::query()
            ->whereBetween('created_at', [$f['from'], $f['to']])
            ->whereIn('action', array_merge(self::IN_ACTIONS, self::OUT_ACTIONS));

        $this->scopeWarehouse($query, $f['warehouse']);

        $rows = $query->get(['action', 'created_at']);

        $buckets = [];
        foreach ($rows as $row) {
            $key = $this->bucketKey($row->created_at, $f['period']);
            $buckets[$key] ??= ['in' => 0, 'out' => 0];
            if (in_array($row->action, self::IN_ACTIONS, true)) {
                $buckets[$key]['in']++;
            } else {
                $buckets[$key]['out']++;
            }
        }

        ksort($buckets);

        $labels = [];
        $in = [];
        $out = [];
        $net = [];
        foreach ($buckets as $key => $vals) {
            $labels[] = $this->bucketLabel($key, $f['period']);
            $in[] = $vals['in'];
            $out[] = $vals['out'];
            $net[] = $vals['in'] - $vals['out'];
        }

        // Breakdown per jenis aksi
        $byAction = $rows->groupBy('action')->map->count()->sortDesc()->toArray();

        return [
            'labels'    => $labels,
            'in'        => $in,
            'out'       => $out,
            'net'       => $net,
            'total_in'  => array_sum($in),
            'total_out' => array_sum($out),
            'by_action' => $byAction,
        ];
    }

    /**
     * Mutasi harian (untuk tampilan kalender): map 'Y-m-d' => ['in' => x, 'out' => y].
     */
    public function dailyMovement(array $f): array
    {
        $query = DeviceTransaction::query()
            ->whereBetween('created_at', [$f['from'], $f['to']])
            ->whereIn('action', array_merge(self::IN_ACTIONS, self::OUT_ACTIONS));

        $this->scopeWarehouse($query, $f['warehouse']);

        $rows = $query->get(['action', 'created_at']);

        $map = [];
        foreach ($rows as $row) {
            $key = $row->created_at->format('Y-m-d');
            $map[$key] ??= ['in' => 0, 'out' => 0];
            if (in_array($row->action, self::IN_ACTIONS, true)) {
                $map[$key]['in']++;
            } else {
                $map[$key]['out']++;
            }
        }

        return $map;
    }

    /**
     * B. Stok aktif per teknisi (berdasarkan pemegang device saat ini).
     */
    public function technicianStock(): array
    {
        $deviceRows = Device::query()
            ->whereIn('status', ['ISSUED', 'INSTALLED'])
            ->whereNotNull('current_holder')
            ->where('current_holder', '!=', '')
            ->selectRaw('current_holder, type, count(*) as total')
            ->groupBy('current_holder', 'type')
            ->get();

        $techMap  = Technician::pluck('code', 'name');
        $areaMap  = Technician::pluck('area', 'name');

        $holders = [];
        foreach ($deviceRows as $row) {
            $name = $row->current_holder;
            $cleanName = preg_replace('/^Technician:\s*/', '', (string) $name);
            $holders[$name] ??= [
                'name'    => $name,
                'code'    => $techMap[$cleanName] ?? ($techMap[$name] ?? '-'),
                'area'    => ($areaMap[$cleanName] ?? $areaMap[$name] ?? null) ?: '-',
                'gps'     => 0,
                'mdvr'    => 0,
                'dashcam' => 0,
                'other'   => 0,
                'total'   => 0,
            ];

            $bucket = match ($row->type) {
                'GPS Tracker' => 'gps',
                'MDVR'        => 'mdvr',
                'Dashcam'     => 'dashcam',
                default       => 'other',
            };
            $holders[$name][$bucket] += $row->total;
            $holders[$name]['total'] += $row->total;
        }

        uasort($holders, fn($a, $b) => $b['total'] <=> $a['total']);

        // Aksesoris yang dipegang teknisi: saldo nyata dari tabel holder_accessories.
        $accNames = Accessory::pluck('name', 'code');
        $accHeld = HolderAccessory::where('holder_type', HolderAccessory::TYPE_TECHNICIAN)
            ->where('qty', '>', 0)
            ->orderByDesc('qty')
            ->get()
            ->map(fn($h) => [
                'technician_code' => $h->holder_code,
                'technician_name' => $h->holder_name ?? $h->holder_code,
                'accessory_code'  => $h->accessory_code,
                'accessory_name'  => $accNames[$h->accessory_code] ?? $h->accessory_code,
                'qty'             => (int) $h->qty,
            ])
            ->values()
            ->all();

        return [
            'devices'     => array_values($holders),
            'accessories' => $accHeld,
        ];
    }

    /**
     * B2. Stok aktif yang dipegang customer (perangkat & aksesoris).
     * Aksesoris di customer disimpulkan dari log transaksi (to/from_location
     * berawalan "Customer: ") karena tidak ada tabel saldo per-customer.
     */
    public function customerStock(): array
    {
        // Perangkat yang masih di customer (current_holder berawalan "Customer: ").
        $deviceRows = Device::query()
            ->whereIn('status', ['ISSUED', 'INSTALLED'])
            ->where('current_holder', 'like', 'Customer: %')
            ->selectRaw('current_holder, type, count(*) as total')
            ->groupBy('current_holder', 'type')
            ->get();

        $holders = [];
        foreach ($deviceRows as $row) {
            $name = trim(str_replace('Customer: ', '', (string) $row->current_holder));
            $holders[$name] ??= [
                'name'    => $name,
                'gps'     => 0,
                'mdvr'    => 0,
                'dashcam' => 0,
                'other'   => 0,
                'total'   => 0,
            ];
            $bucket = match ($row->type) {
                'GPS Tracker' => 'gps',
                'MDVR'        => 'mdvr',
                'Dashcam'     => 'dashcam',
                default       => 'other',
            };
            $holders[$name][$bucket] += $row->total;
            $holders[$name]['total'] += $row->total;
        }
        uasort($holders, fn($a, $b) => $b['total'] <=> $a['total']);

        // Aksesoris di customer: saldo nyata dari tabel holder_accessories.
        $accNames = Accessory::pluck('name', 'code');
        $accHeld = HolderAccessory::where('holder_type', HolderAccessory::TYPE_CUSTOMER)
            ->where('qty', '>', 0)
            ->orderByDesc('qty')
            ->get()
            ->map(fn($h) => [
                'customer'        => $h->holder_name ?? $h->holder_code,
                'accessory_code'  => $h->accessory_code,
                'accessory_name'  => $accNames[$h->accessory_code] ?? $h->accessory_code,
                'qty'             => (int) $h->qty,
            ])
            ->values()
            ->all();

        return [
            'devices'     => array_values($holders),
            'accessories' => $accHeld,
        ];
    }

    /**
     * C. Aging / dead stock: di gudang & di tangan teknisi.
     */
    public function aging(?string $warehouse = null): array
    {
        $lastMovement = DeviceTransaction::query()
            ->selectRaw('device_id, max(created_at) as last_at')
            ->groupBy('device_id')
            ->pluck('last_at', 'device_id');

        $now = Carbon::now();

        // Dead stock di gudang (IN_STOCK)
        $stockQuery = Device::query()->where('status', 'IN_STOCK');
        if ($warehouse) {
            $stockQuery->where('warehouse_code', $warehouse);
        }
        $stockDevices = $stockQuery->get();

        $stockBuckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        $deadStock = [];
        foreach ($stockDevices as $dev) {
            $last = isset($lastMovement[$dev->id]) ? Carbon::parse($lastMovement[$dev->id]) : Carbon::parse($dev->created_at);
            $age = $last->diffInDays($now);
            if ($age <= 30) $stockBuckets['0-30']++;
            elseif ($age <= 60) $stockBuckets['31-60']++;
            elseif ($age <= 90) $stockBuckets['61-90']++;
            else $stockBuckets['90+']++;

            if ($age > 30) {
                $deadStock[] = [
                    'serial_number' => $dev->serial_number,
                    'type'          => $dev->type,
                    'model'         => $dev->model,
                    'warehouse'     => $dev->warehouse_code,
                    'age_days'      => (int) $age,
                    'last_movement' => $last->format('Y-m-d'),
                ];
            }
        }
        usort($deadStock, fn($a, $b) => $b['age_days'] <=> $a['age_days']);

        // Aging di tangan teknisi (ISSUED tak kembali)
        $issuedDevices = Device::query()->where('status', 'ISSUED')->get();
        $techAging = [];
        foreach ($issuedDevices as $dev) {
            $last = isset($lastMovement[$dev->id]) ? Carbon::parse($lastMovement[$dev->id]) : Carbon::parse($dev->created_at);
            $age = (int) $last->diffInDays($now);
            if ($age > 14) {
                $techAging[] = [
                    'serial_number' => $dev->serial_number,
                    'type'          => $dev->type,
                    'holder'        => $dev->current_holder,
                    'age_days'      => $age,
                    'since'         => $last->format('Y-m-d'),
                ];
            }
        }
        usort($techAging, fn($a, $b) => $b['age_days'] <=> $a['age_days']);

        return [
            'stock_buckets' => $stockBuckets,
            'dead_stock'    => array_slice($deadStock, 0, 100),
            'tech_aging'    => array_slice($techAging, 0, 100),
        ];
    }

    /**
     * D. Laporan kualitas: inspeksi QC, repair, scrap.
     */
    public function quality(array $f): array
    {
        $inspections = DeviceInspection::query()
            ->whereBetween('created_at', [$f['from'], $f['to']])
            ->get();

        $qcResults = $inspections->groupBy(fn($i) => strtoupper($i->qc_result ?? 'UNKNOWN'))->map->count()->toArray();
        $conditions = $inspections->groupBy(fn($i) => $i->condition ?? 'N/A')->map->count()->toArray();

        $txInRange = DeviceTransaction::query()->whereBetween('created_at', [$f['from'], $f['to']]);
        $qcFailed = (clone $txInRange)->where('action', 'QC_FAILED')->count();
        $disposed = (clone $txInRange)->where('action', 'DISPOSED')->count();

        return [
            'total_inspections' => $inspections->count(),
            'qc_results'        => $qcResults,
            'conditions'        => $conditions,
            'qc_failed'         => $qcFailed,
            'disposed'          => $disposed,
            'current_repair'    => Device::where('status', 'REPAIR')->count(),
            'current_scrap'     => Device::where('status', 'SCRAP')->count(),
            'recent'            => $inspections->sortByDesc('created_at')->take(50)->map(fn($i) => [
                'device_id'  => $i->device_id,
                'condition'  => $i->condition,
                'qc_result'  => $i->qc_result,
                'operator'   => $i->operator,
                'notes'      => $i->notes,
                'created_at' => Carbon::parse($i->created_at)->format('Y-m-d H:i'),
            ])->values()->toArray(),
        ];
    }

    /**
     * E. Audit koreksi manual (ADJUSTMENT) device + aksesoris.
     */
    public function adjustmentAudit(array $f): array
    {
        $devQuery = DeviceTransaction::query()
            ->where('action', 'ADJUSTMENT')
            ->whereBetween('created_at', [$f['from'], $f['to']]);
        $this->scopeWarehouse($devQuery, $f['warehouse']);

        $deviceAdj = $devQuery->orderByDesc('created_at')->limit(200)->get()->map(fn($t) => [
            'device_sn'  => $t->device_sn,
            'from'       => $t->from_location,
            'to'         => $t->to_location,
            'operator'   => $t->operator,
            'notes'      => $t->notes,
            'created_at' => Carbon::parse($t->created_at)->format('Y-m-d H:i'),
        ])->toArray();

        $accQuery = AccessoryTransaction::query()
            ->where('action', 'ADJUSTMENT')
            ->whereBetween('created_at', [$f['from'], $f['to']]);
        $this->scopeWarehouse($accQuery, $f['warehouse']);

        $accAdj = $accQuery->orderByDesc('created_at')->limit(200)->get()->map(fn($t) => [
            'accessory_code' => $t->accessory_code,
            'qty'            => $t->qty,
            'from'           => $t->from_location,
            'to'             => $t->to_location,
            'notes'          => $t->notes,
            'created_at'     => Carbon::parse($t->created_at)->format('Y-m-d H:i'),
        ])->toArray();

        return [
            'device_adjustments'    => $deviceAdj,
            'accessory_adjustments' => $accAdj,
        ];
    }

    /**
     * F. Ringkasan eksekutif untuk periode terpilih.
     */
    public function executiveSummary(array $f): array
    {
        $movement = $this->inOutMovement($f);

        $txInRange = DeviceTransaction::query()->whereBetween('created_at', [$f['from'], $f['to']]);
        $this->scopeWarehouse($txInRange, $f['warehouse']);
        $actionCounts = (clone $txInRange)->selectRaw('action, count(*) as c')->groupBy('action')->pluck('c', 'action')->toArray();

        $statusSnapshot = Device::query()
            ->when($f['warehouse'], fn($q) => $q->where('warehouse_code', $f['warehouse']))
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();

        return [
            'total_in'        => $movement['total_in'],
            'total_out'       => $movement['total_out'],
            'net'             => $movement['total_in'] - $movement['total_out'],
            'action_counts'   => $actionCounts,
            'status_snapshot' => $statusSnapshot,
            'total_devices'   => array_sum($statusSnapshot),
        ];
    }

    /**
     * G. Kartu Stok (Stock Card): stok awal → masuk → keluar → sisa per jenis barang,
     *    lengkap dengan buku besar (ledger) saldo berjalan per item.
     *    Mencakup Device, Aksesoris, dan Kartu GSM.
     *
     *    Stok awal dihitung dari akumulasi seluruh mutasi SEBELUM tanggal 'from'.
     */
    public function stockCard(array $f): array
    {
        return [
            'device'    => $this->stockCardDevice($f),
            'accessory' => $this->stockCardAccessory($f),
            'gsm'       => $this->stockCardSim($f),
        ];
    }

    private function stockCardDevice(array $f): array
    {
        $typeMap = Device::pluck('type', 'id');

        $query = DeviceTransaction::query()
            ->where('created_at', '<=', $f['to'])
            ->whereIn('action', array_merge(self::IN_ACTIONS, self::OUT_ACTIONS));
        $this->scopeWarehouse($query, $f['warehouse']);

        $entries = $query->orderBy('created_at')
            ->get(['device_id', 'device_sn', 'action', 'created_at'])
            ->map(function ($t) use ($typeMap) {
                $type = $typeMap[$t->device_id] ?? 'Perangkat Lain';
                return [
                    'key'  => $type,
                    'name' => $type,
                    'dir'  => in_array($t->action, self::IN_ACTIONS, true) ? 'in' : 'out',
                    'qty'  => 1,
                    'date' => $t->created_at,
                    'ref'  => $t->device_sn . ' · ' . $t->action,
                ];
            });

        return $this->assembleStockCard($entries, $f['from']);
    }

    private function stockCardAccessory(array $f): array
    {
        $names = Accessory::pluck('name', 'code');

        $query = AccessoryTransaction::query()
            ->where('created_at', '<=', $f['to'])
            ->whereIn('action', array_merge(self::ACC_IN_ACTIONS, self::ACC_OUT_ACTIONS));
        $this->scopeWarehouse($query, $f['warehouse']);

        $entries = $query->orderBy('created_at')
            ->get(['accessory_code', 'qty', 'action', 'created_at'])
            ->map(function ($t) use ($names) {
                $name = $names[$t->accessory_code] ?? $t->accessory_code;
                return [
                    'key'  => $t->accessory_code,
                    'name' => $name,
                    'dir'  => in_array($t->action, self::ACC_IN_ACTIONS, true) ? 'in' : 'out',
                    'qty'  => (int) $t->qty,
                    'date' => $t->created_at,
                    'ref'  => $name . ' · ' . $t->action,
                ];
            });

        return $this->assembleStockCard($entries, $f['from']);
    }

    private function stockCardSim(array $f): array
    {
        $provMap = GsmSimcard::pluck('provider', 'id');

        $query = SimcardTransaction::query()
            ->where('created_at', '<=', $f['to'])
            ->whereIn('action', array_merge(self::SIM_IN_ACTIONS, self::SIM_OUT_ACTIONS));
        $this->scopeWarehouse($query, $f['warehouse']);

        $entries = $query->orderBy('created_at')
            ->get(['gsm_simcard_id', 'msisdn', 'action', 'created_at'])
            ->map(function ($t) use ($provMap) {
                $prov = $provMap[$t->gsm_simcard_id] ?? 'GSM';
                return [
                    'key'  => $prov,
                    'name' => 'SIM ' . $prov,
                    'dir'  => in_array($t->action, self::SIM_IN_ACTIONS, true) ? 'in' : 'out',
                    'qty'  => 1,
                    'date' => $t->created_at,
                    'ref'  => $t->msisdn . ' · ' . $t->action,
                ];
            });

        return $this->assembleStockCard($entries, $f['from']);
    }

    /**
     * Susun kartu stok dari kumpulan entri mutasi (sudah terurut tanggal naik).
     * Tiap entri: ['key','name','dir'(in|out),'qty','date'(Carbon),'ref'].
     */
    private function assembleStockCard(Collection $entries, Carbon $from): array
    {
        $items = [];

        foreach ($entries as $e) {
            $k = $e['key'];
            $items[$k] ??= [
                'name'     => $e['name'],
                'opening'  => 0,
                'in'       => 0,
                'out'      => 0,
                'closing'  => 0,
                'first_in' => null,
                'last_out' => null,
                'ledger'   => [],
            ];

            $signed = $e['dir'] === 'in' ? $e['qty'] : -$e['qty'];

            // Mutasi sebelum periode → hanya menambah stok awal.
            if ($e['date']->lt($from)) {
                $items[$k]['opening'] += $signed;
                continue;
            }

            if ($e['dir'] === 'in') {
                $items[$k]['in'] += $e['qty'];
                $items[$k]['first_in'] ??= $e['date']->format('Y-m-d');
            } else {
                $items[$k]['out'] += $e['qty'];
                $items[$k]['last_out'] = $e['date']->format('Y-m-d');
            }

            $items[$k]['ledger'][] = [
                'date' => $e['date']->format('Y-m-d H:i'),
                'ref'  => $e['ref'],
                'in'   => $e['dir'] === 'in' ? $e['qty'] : 0,
                'out'  => $e['dir'] === 'out' ? $e['qty'] : 0,
            ];
        }

        $rows = [];
        foreach ($items as $it) {
            $it['closing'] = $it['opening'] + $it['in'] - $it['out'];

            $bal = $it['opening'];
            foreach ($it['ledger'] as &$l) {
                $bal += $l['in'] - $l['out'];
                $l['balance'] = $bal;
            }
            unset($l);

            $rows[] = $it;
        }

        usort($rows, fn($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return [
            'rows'   => $rows,
            'totals' => [
                'opening' => array_sum(array_column($rows, 'opening')),
                'in'      => array_sum(array_column($rows, 'in')),
                'out'     => array_sum(array_column($rows, 'out')),
                'closing' => array_sum(array_column($rows, 'closing')),
            ],
        ];
    }

    public function warehouseOptions(): Collection
    {
        return Warehouse::orderBy('name')->get(['code', 'name']);
    }
}
