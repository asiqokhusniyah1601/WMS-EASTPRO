<?php

namespace App\Services;

use App\Models\Device;
use App\Models\WarehouseAccessory;
use App\Models\StockAlertThreshold;
use App\Models\Warehouse;
use App\Models\Accessory;
use App\Models\DeviceTransaction;
use App\Models\GsmSimcard;
use Carbon\Carbon;

class DashboardInsightService
{
    /**
     * Get all AI Insights and Alerts for the Dashboard.
     *
     * @param  string|null  $warehouseCode  Limit insights to a single warehouse (null = global).
     * @return array
     */
    public function getInsights(?string $warehouseCode = null)
    {
        $insights = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        // 1. DEAD STOCK ANALYSIS (Technicians holding devices for too long)
        // Aggregation happens in the DB engine (GROUP BY) instead of hydrating rows.
        $deadStocks = Device::where('status', 'ISSUED')
            ->where('updated_at', '<', Carbon::now()->subDays(7))
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->selectRaw('current_holder, count(*) as total')
            ->groupBy('current_holder')
            ->get();

        foreach ($deadStocks as $row) {
            $count = (int) $row->total;
            if ($count > 0) {
                // Remove "Technician: " prefix if present for cleaner display
                $name = str_replace('Technician: ', '', (string) $row->current_holder);
                $insights['warning'][] = [
                    'icon' => 'fa-user-clock',
                    'message' => "Teknisi <strong>{$name}</strong> menahan {$count} unit perangkat lebih dari 7 hari tanpa instalasi.",
                    'time' => 'Terdeteksi baru saja'
                ];
            }
        }

        // 2. LOW STOCK ALERTS (Below Minimum Threshold) — sumber data tunggal.
        foreach ($this->getStockAlerts($warehouseCode) as $alert) {
            $insights[$alert['level']][] = [
                'icon' => $alert['icon'],
                'message' => $alert['message'],
                'time' => 'Real-time',
            ];
        }

        // 3. DEPLETION RATE / BURN RATE TREND (Predictive)
        // Check how many devices were issued in the last 7 days.
        $last7DaysIssue = Device::where('status', '!=', 'IN_STOCK')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();

        $totalInStock = Device::where('status', 'IN_STOCK')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();

        if ($last7DaysIssue > 0 && $totalInStock > 0) {
            $dailyBurnRate = $last7DaysIssue / 7;
            $daysLeft = floor($totalInStock / $dailyBurnRate);

            if ($daysLeft <= 14) {
                $insights['critical'][] = [
                    'icon' => 'fa-chart-line',
                    'message' => "<strong>Trend AI:</strong> Berdasarkan rata-rata pengeluaran (" . round($dailyBurnRate, 1) . " unit/hari), total stok diprediksi akan habis dalam <strong>{$daysLeft} hari</strong>.",
                    'time' => 'AI Prediction'
                ];
            } else {
                $insights['info'][] = [
                    'icon' => 'fa-robot',
                    'message' => "<strong>Trend AI:</strong> Laju pengeluaran perangkat stabil di angka rata-rata " . round($dailyBurnRate, 1) . " unit/hari minggu ini.",
                    'time' => 'AI Insight'
                ];
            }
        }

        return $insights;
    }

    /**
     * Get low-stock alerts derived from StockAlertThreshold (device + accessory).
     * Dipakai oleh ikon notifikasi (lonceng) dan halaman Alert Center.
     *
     * @param  string|null  $warehouseCode
     * @return array<int, array{level:string,icon:string,type:string,label:string,warehouse:string,current:int,min:int,message:string}>
     */
    public function getStockAlerts(?string $warehouseCode = null): array
    {
        $thresholds = StockAlertThreshold::with('warehouse')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->get();

        if ($thresholds->isEmpty()) {
            return [];
        }

        $deviceStock = Device::where('status', 'IN_STOCK')
            ->selectRaw('warehouse_code, model, count(*) as total')
            ->groupBy('warehouse_code', 'model')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->warehouse_code . '|' . $r->model => (int) $r->total]);

        $accessoryStock = WarehouseAccessory::all()
            ->mapWithKeys(fn ($r) => [$r->warehouse_code . '|' . $r->accessory_code => (int) $r->qty]);

        $accessoryNames = Accessory::pluck('name', 'code');

        // SIM cards tidak punya saldo per-gudang; dihitung global per provider
        // berdasarkan jumlah kartu berstatus IN_STOCK.
        $simStock = GsmSimcard::where('status', 'IN_STOCK')
            ->selectRaw('provider, count(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider');
        $seenSimProvider = [];

        $alerts = [];

        foreach ($thresholds as $t) {
            if ($t->min_stock_level <= 0) {
                continue;
            }

            $whName = $t->warehouse ? $t->warehouse->name : $t->warehouse_code;

            if ($t->item_type === 'DEVICE') {
                $current = $deviceStock[$t->warehouse_code . '|' . $t->item_identifier] ?? 0;
                if ($current <= $t->min_stock_level) {
                    $alerts[] = [
                        'level'     => $current === 0 ? 'critical' : 'warning',
                        'icon'      => 'fa-microchip',
                        'type'      => 'DEVICE',
                        'label'     => $t->item_identifier,
                        'warehouse' => $whName,
                        'current'   => $current,
                        'min'       => (int) $t->min_stock_level,
                        'message'   => "Stok <strong>{$t->item_identifier}</strong> di {$whName} menipis! Sisa {$current} unit (Batas minimum: {$t->min_stock_level}).",
                    ];
                }
            } elseif ($t->item_type === 'ACCESSORY') {
                $current = $accessoryStock[$t->warehouse_code . '|' . $t->item_identifier] ?? 0;
                if ($current <= $t->min_stock_level) {
                    $accName = $accessoryNames[$t->item_identifier] ?? $t->item_identifier;
                    $alerts[] = [
                        'level'     => $current === 0 ? 'critical' : 'warning',
                        'icon'      => 'fa-plug',
                        'type'      => 'ACCESSORY',
                        'label'     => $accName,
                        'warehouse' => $whName,
                        'current'   => $current,
                        'min'       => (int) $t->min_stock_level,
                        'message'   => "Stok <strong>{$accName}</strong> di {$whName} menipis! Sisa {$current} unit (Batas minimum: {$t->min_stock_level}).",
                    ];
                }
            } elseif ($t->item_type === 'SIMCARD') {
                // Hindari peringatan ganda bila provider yang sama disetel di banyak gudang
                // (SIM tidak terikat ke gudang tertentu).
                if (isset($seenSimProvider[$t->item_identifier])) {
                    continue;
                }
                $seenSimProvider[$t->item_identifier] = true;

                $current = (int) ($simStock[$t->item_identifier] ?? 0);
                if ($current <= $t->min_stock_level) {
                    $alerts[] = [
                        'level'     => $current === 0 ? 'critical' : 'warning',
                        'icon'      => 'fa-sim-card',
                        'type'      => 'SIMCARD',
                        'label'     => $t->item_identifier,
                        'warehouse' => 'Semua Gudang',
                        'current'   => $current,
                        'min'       => (int) $t->min_stock_level,
                        'message'   => "Stok kartu SIM <strong>{$t->item_identifier}</strong> menipis! Sisa {$current} kartu (Batas minimum: {$t->min_stock_level}).",
                    ];
                }
            }
        }

        // Critical (habis) tampil paling atas.
        usort($alerts, fn ($a, $b) => ($a['level'] === 'critical' ? 0 : 1) <=> ($b['level'] === 'critical' ? 0 : 1));

        return $alerts;
    }

    /**
     * Get Global Stock Summary Metrics using DB aggregation (no memory hydration).
     *
     * @param  string|null  $warehouseCode  Limit metrics to a single warehouse (null = global).
     * @return array
     */
    public function getGlobalMetrics(?string $warehouseCode = null)
    {
        $counts = Device::when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byWarehouse = Device::where('status', 'IN_STOCK')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->selectRaw('warehouse_code, count(*) as total')
            ->groupBy('warehouse_code')
            ->pluck('total', 'warehouse_code');

        // Aksesoris di gudang (scoped per warehouse bila ada).
        $totalAccessories = (int) WarehouseAccessory::when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->sum('qty');

        // Kartu GSM/SIM siap di gudang (IN_STOCK & punya gudang).
        $totalSimInStock = (int) GsmSimcard::where('status', 'IN_STOCK')
            ->whereNotNull('warehouse_code')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();

        // SIM terpasang (global; tak terikat gudang).
        $totalSimInstalled = (int) GsmSimcard::where('status', 'INSTALLED')->count();

        // Pisahkan stok yang sedang dipegang TEKNISI vs CUSTOMER (berdasarkan
        // prefix current_holder). Saat serah terima, status = ISSUED untuk keduanya.
        $issuedTechnician = (int) Device::where('status', 'ISSUED')
            ->where(function ($q) {
                $q->where('current_holder', 'not like', 'Customer:%')
                  ->orWhereNull('current_holder');
            })
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();

        $atCustomer = (int) Device::whereIn('status', ['ISSUED', 'INSTALLED'])
            ->where('current_holder', 'like', 'Customer:%')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->count();

        // Stok device di gudang dipecah berdasarkan kondisi unit (BARU vs BEKAS).
        $stockByCondition = Device::where('status', 'IN_STOCK')
            ->when($warehouseCode, fn ($q) => $q->where('warehouse_code', $warehouseCode))
            ->selectRaw('unit_condition, count(*) as total')
            ->groupBy('unit_condition')
            ->pluck('total', 'unit_condition');

        return [
            'total_in_stock' => (int) ($counts['IN_STOCK'] ?? 0),
            'total_pending_qc' => (int) ($counts['PENDING_QC'] ?? 0),
            'total_issued' => $issuedTechnician,
            'total_at_customer' => $atCustomer,
            'total_installed' => (int) ($counts['INSTALLED'] ?? 0),
            'total_devices' => (int) $counts->sum(),
            'total_stock_baru' => (int) ($stockByCondition['BARU'] ?? 0),
            'total_stock_bekas' => (int) ($stockByCondition['BEKAS'] ?? 0),
            'total_accessories' => $totalAccessories,
            'total_sim_in_stock' => $totalSimInStock,
            'total_sim_installed' => $totalSimInstalled,
            'by_warehouse' => $byWarehouse,
        ];
    }

    /**
     * Get the "Device Burn Rate" time series for a line chart.
     * Counts devices leaving stock (ISSUED / TRANSFER_OUT) per day.
     *
     * @param  string|null  $warehouseCode  Limit to devices leaving this warehouse (null = global).
     * @param  int  $days  Number of days to include (inclusive of today).
     * @return array{labels: array, values: array}
     */
    public function getBurnRateSeries(?string $warehouseCode = null, int $days = 30): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = DeviceTransaction::whereIn('action', ['ISSUED', 'TRANSFER_OUT'])
            ->where('created_at', '>=', $start)
            ->when($warehouseCode, fn ($q) => $q->where('from_location', $warehouseCode))
            ->selectRaw('DATE(created_at) as d, count(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Get stock distribution for a donut chart.
     * Global: IN_STOCK devices grouped by warehouse.
     * Scoped: devices grouped by status within the warehouse.
     *
     * @param  string|null  $warehouseCode
     * @return array{labels: array, values: array}
     */
    public function getDistribution(?string $warehouseCode = null): array
    {
        if ($warehouseCode) {
            $rows = Device::where('warehouse_code', $warehouseCode)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        } else {
            $rows = Device::where('status', 'IN_STOCK')
                ->selectRaw('warehouse_code, count(*) as total')
                ->groupBy('warehouse_code')
                ->pluck('total', 'warehouse_code');
        }

        return [
            'labels' => $rows->keys()->all(),
            'values' => $rows->values()->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Build the full dashboard data slice for a given scope.
     *
     * @param  string|null  $warehouseCode
     * @return array
     */
    public function getScopedData(?string $warehouseCode = null): array
    {
        return [
            'metrics' => $this->getGlobalMetrics($warehouseCode),
            'insights' => $this->getInsights($warehouseCode),
            'burnRate' => $this->getBurnRateSeries($warehouseCode),
            'distribution' => $this->getDistribution($warehouseCode),
        ];
    }

    /**
     * Build the complete real-time broadcast payload: a global slice plus a
     * per-warehouse map, so connected clients can re-render any selected view.
     *
     * @return array{global: array, warehouses: array}
     */
    public function getBroadcastPayload(): array
    {
        $warehouses = [];
        foreach (Warehouse::pluck('code') as $code) {
            $warehouses[$code] = $this->getScopedData($code);
        }

        return [
            'global' => $this->getScopedData(null),
            'warehouses' => $warehouses,
        ];
    }
}
