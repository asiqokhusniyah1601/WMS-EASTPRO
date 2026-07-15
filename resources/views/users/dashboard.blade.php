@extends('layouts.app')

<!-- G@yield('title', 'Dashboard | DLMS')-->

@section('styles')
<style>
    /* ====== Command Center 2026 — Unified Dashboard + Alert Center ====== */
    .dash-split {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 24px;
        align-items: start;
        margin-top: 24px;
    }
    @media (max-width: 1200px) { 
        .dash-split { grid-template-columns: 1fr; }
        #charts-grid { grid-template-columns: 1fr !important; }
    }

    .dash-sticky { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 20px; }

    .dash-section-title {
        font-size: 16px; font-weight: 600; margin: 0 0 14px;
        color: var(--text-primary); display: flex; align-items: center; gap: 8px;
    }
    .dash-section-title:not(:first-child) { margin-top: 28px; }

    /* ---- Priority Stream (thin actionable banner under header) ---- */
    .priority-stream {
        display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
        padding: 10px 16px; border-radius: 12px; margin-top: 20px;
        font-size: 13px; font-weight: 600; border: 1px solid transparent;
    }
    .priority-stream.is-critical { background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.35); color: var(--accent-red, #ef4444); }
    .priority-stream.is-warning  { background: rgba(245,158,11,.10); border-color: rgba(245,158,11,.35); color: #d97706; }
    .priority-stream.is-healthy  { background: rgba(16,185,129,.10); border-color: rgba(16,185,129,.30); color: #059669; }
    .priority-stream .ps-lead { display: inline-flex; align-items: center; gap: 8px; }
    .priority-stream .ps-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px;
        background: var(--bg-secondary); color: var(--text-primary); text-decoration: none;
        border: 1px solid var(--border-color); font-size: 12px; transition: transform .12s ease, box-shadow .12s ease;
    }
    .priority-stream .ps-pill:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.3); }
    .priority-stream .ps-pill .num { font-weight: 800; }

    /* ---- Footer area: Pusat Peringatan Warehouse (Unified) ---- */
    .dash-footer {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
        align-items: start;
        margin-top: 24px;
    }
    /* Tinggi widget disesuaikan agar pas di halaman */
    .dash-footer .card-body { max-height: 380px; overflow-y: auto; padding: 20px; }

    /* ---- Alert Center feed ---- */
    .alert-feed-card .card-body { max-height: 380px; overflow-y: auto; padding: 20px; }
    .alert-feed-item {
        display: flex; gap: 12px; padding: 12px 14px; border-radius: 10px; margin-bottom: 10px;
        background: var(--bg-color); border-left: 4px solid var(--border-color);
    }
    .alert-feed-item.lvl-critical { border-left-color: var(--accent-red, #ef4444); background: rgba(239,68,68,.06); }
    .alert-feed-item.lvl-warning  { border-left-color: #f59e0b; background: rgba(245,158,11,.06); }
    .alert-feed-item.lvl-info     { border-left-color: var(--accent-blue, #3b82f6); background: rgba(59,130,246,.06); }
    .alert-feed-item .afi-icon { flex-shrink: 0; width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; }
    .alert-feed-item.lvl-critical .afi-icon { background: rgba(239,68,68,.15); color: var(--accent-red, #ef4444); }
    .alert-feed-item.lvl-warning  .afi-icon { background: rgba(245,158,11,.15); color: #d97706; }
    .alert-feed-item.lvl-info     .afi-icon { background: rgba(59,130,246,.15); color: var(--accent-blue, #3b82f6); }
    .alert-feed-item .afi-body { flex: 1; min-width: 0; }
    .alert-feed-item .afi-msg { font-size: 12.5px; line-height: 1.45; color: var(--text-primary); }
    .alert-feed-item .afi-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
    .alert-action-btn {
        display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; padding: 4px 10px;
        border-radius: 8px; font-size: 11px; font-weight: 600; text-decoration: none;
        background: var(--accent-indigo, #6366f1); color: #fff; border: none; cursor: pointer;
        transition: opacity .12s ease;
    }
    .alert-action-btn:hover { opacity: .88; }
    .alert-action-btn.secondary { background: transparent; color: var(--accent-indigo, #6366f1); border: 1px solid var(--accent-indigo, #6366f1); }
    .alert-feed-empty { text-align: center; color: var(--text-muted); font-size: 13px; padding: 24px 8px; }
    .alert-feed-empty i { color: #10b981; font-size: 22px; display: block; margin-bottom: 8px; }

    /* ---- Drill-down modal ---- */
    .drill-overlay {
        display: none; position: fixed; inset: 0; z-index: 1200;
        background: rgba(2, 6, 23, .55); backdrop-filter: blur(3px);
        align-items: flex-start; justify-content: center; padding: 6vh 16px;
    }
    .drill-overlay.open { display: flex; animation: drillFade .15s ease-out; }
    @keyframes drillFade { from { opacity: 0; } to { opacity: 1; } }
    .drill-box {
        width: 100%; max-width: 860px; background: var(--bg-secondary, #fff);
        border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden;
        box-shadow: 0 24px 60px rgba(0,0,0,.4); display: flex; flex-direction: column; max-height: 86vh;
    }
    .drill-head, .drill-foot {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px; border-bottom: 1px solid var(--border-color);
    }
    .drill-foot { border-bottom: none; border-top: 1px solid var(--border-color); }
    .drill-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; color: var(--text-primary); }
    .drill-title i { color: var(--accent-indigo, #6366f1); }
    .drill-title .badge { font-size: 11px; }
    .drill-close { background: none; border: none; color: var(--text-muted); font-size: 18px; cursor: pointer; padding: 4px 8px; border-radius: 8px; }
    .drill-close:hover { color: var(--text-primary); background: var(--bg-color); }
    .drill-body { overflow: auto; padding: 6px 0; }
    .drill-body table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .drill-body th, .drill-body td { padding: 10px 16px; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
    .drill-body th { position: sticky; top: 0; background: var(--bg-tertiary, #1e293b); color: var(--text-secondary); font-weight: 600; z-index: 1; }
    .drill-body td { color: var(--text-primary); }
    .drill-loading, .drill-empty { padding: 36px 16px; text-align: center; color: var(--text-muted); font-size: 14px; }
    .drill-hint { font-size: 11px; color: var(--text-muted); }

    /* Map Markers */
    .marker-balloon {
        background: var(--bg-secondary, #fff);
        border: 2px solid var(--accent-indigo, #6366f1);
        border-radius: 8px;
        padding: 4px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        position: relative;
    }
    .marker-balloon::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 50%;
        margin-left: -6px;
        border-width: 6px 6px 0;
        border-style: solid;
        border-color: var(--accent-indigo, #6366f1) transparent transparent transparent;
    }
    .marker-title {
        font-size: 10px;
        font-weight: 600;
        color: var(--text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .marker-val {
        font-size: 13px;
        font-weight: 800;
        color: var(--text-primary);
    }
    
    /* Marker Status Colors */
    .marker-balloon.marker-warning { border-color: #ffc107; }
    .marker-balloon.marker-warning::after { border-top-color: #ffc107; }
    
    .marker-balloon.marker-danger { border-color: #dc3545; }
    .marker-balloon.marker-danger::after { border-top-color: #dc3545; }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-satellite-dish"
        iconColor="var(--accent-red); animation: pulse 2s infinite"
        title="DASHBOARD EASTPRO (Real-time)"
        subtitle="Memantau siklus hidup perangkat dan tren AI secara global secara real-time.">
        <div style="display: flex; align-items: center; gap: 6px;">
            @if(!auth()->user()?->isWarehouseBound())
            <i class="fa-solid fa-filter" style="color: var(--text-muted); font-size: 12px;"></i>
            <select id="warehouse-view-filter" class="form-control" style="width: auto; min-width: 180px; padding: 6px 10px; font-size: 13px;">
                @foreach($warehouses as $code => $name)
                    <option value="{{ $code }}" {{ $view === $code ? 'selected' : '' }}>
                        {{ $code === 'global' || str_starts_with($code, '__region') ? 'View: ' . $name : $name . ' (' . $code . ')' }}
                    </option>
                @endforeach
            </select>
            @else
            <span class="badge badge-info" style="font-size: 12px;"><i class="fa-solid fa-lock"></i> Scope: {{ $warehouses[$view] ?? $view }}</span>
            @endif
        </div>
        <span class="badge badge-success" id="connection-status"><i class="fa-solid fa-link"></i> Connected (Live)</span>
    </x-page-header>

    @php
        $criticalCount = collect($stockAlerts ?? [])->where('level', 'critical')->count();
        $warningCount  = collect($stockAlerts ?? [])->where('level', 'warning')->count();
        $pendingIncoming = $pendingIncoming ?? 0;
        $hasUrgent = $criticalCount > 0 || $pendingIncoming > 0;
        $streamClass = $criticalCount > 0 ? 'is-critical' : (($warningCount > 0 || $pendingIncoming > 0) ? 'is-warning' : 'is-healthy');
    @endphp

    <!-- PRIORITY STREAM: ringkas, langsung bisa ditindaklanjuti -->
    <div class="priority-stream {{ $streamClass }}" id="priorityStream">
        @if($hasUrgent || $warningCount > 0)
            <span class="ps-lead"><i class="fa-solid fa-bolt"></i> Perlu perhatian:</span>
            @if($criticalCount > 0)
                <a href="{{ route('alerts') }}" class="ps-pill" title="Lihat stok kritis di Alert Center">
                    <i class="fa-solid fa-circle-exclamation" style="color: var(--accent-red, #ef4444);"></i>
                    <span class="num">{{ $criticalCount }}</span> stok kritis (habis)
                </a>
            @endif
            @if($warningCount > 0)
                <a href="{{ route('alerts') }}" class="ps-pill" title="Lihat stok menipis di Alert Center">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b;"></i>
                    <span class="num">{{ $warningCount }}</span> stok menipis
                </a>
            @endif
            @if($pendingIncoming > 0)
                <a href="{{ route('transfer') }}" class="ps-pill" title="Terima transfer yang sedang dalam perjalanan">
                    <i class="fa-solid fa-truck-fast" style="color: var(--accent-indigo, #6366f1);"></i>
                    <span class="num">{{ $pendingIncoming }}</span> transfer menunggu diterima
                </a>
            @endif
        @else
            <span class="ps-lead"><i class="fa-solid fa-circle-check"></i> Semua sehat — tidak ada stok kritis maupun transfer tertunda.</span>
        @endif
    </div>

    <!-- Global Stock Map -->
    <h3 style="font-size: 18px; font-weight: 600; margin: 32px 0 16px; color: var(--text-primary);">
        <i class="fa-solid fa-earth-asia"></i> Stock Preview
        <span style="font-size: 13px; font-weight: 400; color: var(--text-muted); margin-left: 8px;">
            &mdash; {{ $stockMetricsLabel ?? 'Global (Semua Gudang)' }}
        </span>
    </h3>
    <div class="stats-grid" id="global-stock-map">
        <x-stat-card color="rose" icon="fa-clipboard-check" title="Antrian QC"
            :value="$metrics['total_pending_qc'] ?? 0" valueId="val-pending-qc"
            :href="route('quality.control', ['tab' => 'incoming'])" drill="pending_qc" hint="Proses QC" />
        <x-stat-card color="emerald" icon="fa-check-double" title="QC Done"
            :value="$metrics['total_qc_done'] ?? 0" valueId="val-qc-done"
            :href="route('quality.control', ['tab' => 'report'])" drill="qc_done" hint="Lihat Detail QC" />
        <x-stat-card color="sky" icon="fa-truck-fast" title="In Transit"
            :value="$metrics['total_in_transit'] ?? 0" valueId="val-in-transit"
            :href="route('search', ['q' => 'IN_TRANSIT'])" drill="in_transit" hint="Sedang Transfer" />
        <x-stat-card color="blue" icon="fa-warehouse" title="Warehouse"
            :value="$metrics['total_in_stock']" valueId="val-in-stock"
            :href="route('search', ['q' => 'IN_STOCK'])" drill="in_stock" />
        <x-stat-card color="amber" icon="fa-user-gear" title="Mutasi Done"
            :value="$metrics['total_issued']" valueId="val-issued"
            :href="route('search', ['q' => 'ISSUED'])" drill="issued" />
        <x-stat-card color="teal" icon="fa-tower-cell" title="Installed"
            :value="$metrics['total_installed'] ?? 0" valueId="val-installed"
            :href="route('search', ['q' => 'INSTALLED'])" drill="installed" />
        <x-stat-card color="slate" icon="fa-ban" title="Reject"
            :value="$metrics['total_rejected'] ?? 0" valueId="val-rejected"
            :href="route('search', ['q' => 'REJECTED'])" drill="rejected" />
        <x-stat-card color="red" icon="fa-triangle-exclamation" title="Flagged"
            :value="$metrics['total_flagged'] ?? 0" valueId="val-flagged"
            :href="route('search', ['q' => 'FLAGGED'])" drill="flagged" hint="Bermasalah" />
        <x-stat-card color="indigo" icon="fa-cubes-stacked" title="Total"
            :value="$metrics['total_devices']" valueId="val-total"
            :href="route('reports')" drill="total_devices" hint="Lihat laporan" />
    </div>

    <!-- ===== STOK DEVICE ===== -->
    <h3 style="font-size: 18px; font-weight: 600; margin: 32px 0 16px; color: var(--text-primary);">
        <i class="fa-solid fa-server"></i> Stok Device
        <span style="font-size: 13px; font-weight: 400; color: var(--text-muted);">&mdash; {{ session('active_warehouse_name') ?: 'Global (Semua Gudang)' }}</span>
    </h3>
    <div id="devStockGrid" style="display: grid; gap: 20px;">
        <div id="devStockLoading" style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px;"></i>
            <p style="margin-top: 10px;">Memuat data stok...</p>
        </div>
    </div>

        <!-- Detail Tabel (tersembunyi, muncul saat kartu diklik) -->
        <div id="devStockDetailWrap" style="display: none; margin-top: 20px; animation: fadeIn .2s ease;">
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title">
                        <i class="fa-solid fa-list-ul"></i>
                        <span id="devStockDetailTitle">Detail Stok</span>
                    </div>
                    <button id="devStockDetailClose" class="btn btn-outline" style="padding: 4px 12px; font-size: 12px;">
                        <i class="fa-solid fa-xmark"></i> Tutup
                    </button>
                </div>
                <div class="table-wrapper" style="max-height: 360px; overflow-y: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Serial Number</th>
                                <th>Model</th>
                                <th>Tipe</th>
                                <th style="text-align:center;">Kondisi</th>
                                <th>Lokasi Rak</th>
                            </tr>
                        </thead>
                        <tbody id="devStockDetailBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- ===== END STOK DEVICE ===== -->

    <!-- Accessories & GSM Stock Map -->
    <h3 style="font-size: 18px; font-weight: 600; margin: 32px 0 16px; color: var(--text-primary);">
        <i class="fa-solid fa-plug"></i> Aksesoris &amp; Kartu GSM
    </h3>
    <div class="stats-grid">
        <x-stat-card color="orange" icon="fa-plug" title="Aksesoris di Gudang (Qty)"
            :value="$metrics['total_accessories'] ?? 0" valueId="val-accessories"
            :href="route('reports')" drill="accessories" hint="Lihat laporan" />
        <x-stat-card color="indigo" icon="fa-sim-card" title="SIM Siap di Gudang"
            :value="$metrics['total_sim_in_stock'] ?? 0" valueId="val-sim-stock"
            :href="route('master_data', ['tab' => 'simcard'])" drill="sim_stock" />
        <x-stat-card color="emerald" icon="fa-tower-cell" title="SIM Terpasang (Installed)"
            :value="$metrics['total_sim_installed'] ?? 0" valueId="val-sim-installed"
            :href="route('master_data', ['tab' => 'simcard'])" drill="sim_installed" />
    </div>

    <!-- Stok Tersedia per Area (Gudang) -->
    <div class="card" style="margin-top: 32px;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-map-location-dot" style="color: var(--accent-indigo);"></i>
                <span>Stok Tersedia per Area (Gudang)</span>
            </div>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary active" id="btn-view-table"><i class="fa-solid fa-table"></i> Tabel</button>
                <button type="button" class="btn btn-outline-primary" id="btn-view-map"><i class="fa-solid fa-map"></i> Peta</button>
            </div>
        </div>
        
        <div id="areaMapContainer" style="display: none; height: 350px; width: 100%; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;"></div>
        
        <div class="table-responsive" id="areaTableContainer">
            <table class="table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th style="text-align:center;"><i class="fa-solid fa-microchip"></i> Device</th>
                        <th style="text-align:center;"><i class="fa-solid fa-sim-card"></i> GSM</th>
                        <th style="text-align:center;"><i class="fa-solid fa-plug"></i> Aksesoris</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($areaStock ?? []) as $area => $s)
                        <tr data-drill="area_field" data-area="{{ $area }}" style="cursor: pointer;" title="Lihat detail perangkat di area {{ $area }}">
                            <td style="font-weight:600;">
                                <i class="fa-solid fa-location-dot" style="color: var(--accent-indigo); margin-right:6px;"></i>{{ $area }}
                            </td>
                            <td style="text-align:center;">{{ $s['devices'] }}</td>
                            <td style="text-align:center;">{{ $s['sim'] }}</td>
                            <td style="text-align:center;">{{ $s['accessories'] }}</td>
                        </tr>
                    @empty
                        <x-empty-state colspan="4" icon="fa-map-location-dot"
                            title="Belum ada stok di lapangan"
                            message="Belum ada barang yang dipegang teknisi. Set area teknisi di Master Data agar stok lapangan terkelompok per area." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Visual Trends -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 24px;" id="charts-grid">
        <div class="card" style="margin: 0; min-width: 0;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-chart-line" style="color: var(--accent-red);"></i>
                    <span>Device Burn Rate (30 Hari Terakhir)</span>
                </div>
            </div>
            <div class="card-body" style="height: 300px; position: relative; width: 100%;">
                <canvas id="burnRateChart"></canvas>
            </div>
        </div>
        <div class="card" style="margin: 0; min-width: 0;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-chart-pie" style="color: var(--accent-indigo);"></i>
                    <span id="distribution-title">Stock Distribution</span>
                </div>
            </div>
            <div class="card-body" style="height: 300px; display: flex; align-items: center; justify-content: center; position: relative; width: 100%;">
                <canvas id="distributionChart"></canvas>
            </div>
        </div>
    </div>


    <!-- Recent Transactions -->
    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-list-check"></i>
                <span>Live Transaction Stream</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table" id="recent-tx-table">
                <thead>
                    <tr>
                        <th>Waktu (Live)</th>
                        <th>Perangkat (SN)</th>
                        <th>Aktivitas</th>
                        <th>Dari</th>
                        <th>Ke</th>
                        <th>Operator</th>
                    </tr>
                </thead>
                <tbody id="tx-tbody">
                    @forelse($recent_tx as $tx)
                        <tr>
                            <td>{{ $tx['timestamp'] }}</td>
                            <td><strong>{{ $tx['device_sn'] }}</strong></td>
                            <td>
                                @if($tx['action'] === 'RECEIVE' || $tx['action'] === 'RETURN')
                                    <span class="badge badge-success">{{ $tx['action'] }}</span>
                                @elseif($tx['action'] === 'ISSUE' || $tx['action'] === 'TRANSFER_OUT')
                                    <span class="badge badge-warning">{{ $tx['action'] }}</span>
                                @else
                                    <span class="badge" style="background: var(--bg-color); color: var(--text-primary); border: 1px solid var(--border-color);">{{ $tx['action'] }}</span>
                                @endif
                            </td>
                            <td>{{ $tx['from'] }}</td>
                            <td>{{ $tx['to'] }}</td>
                            <td>{{ $tx['operator'] }}</td>
                        </tr>
                    @empty
                        <tr id="empty-tx-row"><td colspan="6" style="text-align:center; color: var(--text-muted);">Belum ada transaksi terekam.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============ FOOTER: Pusat Peringatan Warehouse (Unified) ============ -->
    @php
        $unifiedAlerts = [];
        $seenMessages = [];

        // 1. Add Stock Alerts (sumber data utama)
        foreach (($stockAlerts ?? []) as $a) {
            $msgKey = strip_tags($a['message']);
            $unifiedAlerts[] = [
                'level' => $a['level'] ?? 'warning',
                'icon' => $a['icon'] ?? 'fa-circle-info',
                'message' => $a['message'],
                'time' => 'Real-time',
                'is_stock_alert' => true,
                'alert_data' => $a
            ];
            $seenMessages[$msgKey] = true;
        }

        // 2. Add AI Insights (deduplicate if message is similar or contains same low stock info)
        $insightLevels = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];
        foreach ($insightLevels as $lvl => $cls) {
            foreach (($insights[$lvl] ?? []) as $i) {
                $msgKey = strip_tags($i['message']);
                // Deduplicate if already displayed or if it has 'Real-time' time tag (low stock)
                if (isset($seenMessages[$msgKey]) || (isset($i['time']) && $i['time'] === 'Real-time')) {
                    continue;
                }
                $unifiedAlerts[] = [
                    'level' => $lvl === 'critical' ? 'critical' : ($lvl === 'info' ? 'info' : 'warning'),
                    'icon' => $i['icon'] ?? 'fa-circle-info',
                    'message' => $i['message'],
                    'time' => $i['time'] ?? '',
                    'is_stock_alert' => false
                ];
                $seenMessages[$msgKey] = true;
            }
        }

        // 3. Add Pending Transfers
        if (($pendingIncoming ?? 0) > 0) {
            $unifiedAlerts[] = [
                'level' => 'warning',
                'icon' => 'fa-truck-fast',
                'message' => "<strong>{$pendingIncoming}</strong> transfer sedang dalam perjalanan menunggu diterima.",
                'time' => 'Pending Action',
                'is_stock_alert' => false,
                'is_transfer' => true
            ];
        }

        // Sort by severity (critical, warning, info)
        usort($unifiedAlerts, function($a, $b) {
            $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
            return ($order[$a['level']] ?? 2) <=> ($order[$b['level']] ?? 2);
        });
    @endphp

    <div class="dash-footer">
        <div class="card alert-feed-card" style="margin: 0;">
            <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-color);">
                <div class="card-title" style="margin: 0; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: var(--accent-red, #ef4444);"></i>
                    <span>Pusat Peringatan Warehouse</span>
                </div>
                <a href="{{ route('alerts') }}" style="font-size: 12px; font-weight: 600; text-decoration: none; color: var(--accent-indigo, #6366f1);">
                    Buka semua <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                </a>
            </div>
            <div class="card-body" id="warehouse-alerts-container" style="display: flex; flex-direction: column; gap: 12px;">
                @forelse($unifiedAlerts as $alert)
                    @if(!empty($alert['is_stock_alert']))
                        <x-alert-item :alert="$alert['alert_data']" variant="feed" />
                    @elseif(!empty($alert['is_transfer']))
                        <div class="alert-feed-item lvl-warning">
                            <div class="afi-icon"><i class="fa-solid fa-truck-fast"></i></div>
                            <div class="afi-body">
                                <div class="afi-msg">{!! $alert['message'] !!}</div>
                                <div class="afi-meta"><i class="fa-solid fa-route"></i> Operasional · Transfer</div>
                                <a href="{{ route('transfer') }}" class="alert-action-btn"><i class="fa-solid fa-circle-check"></i> Terima Transfer</a>
                            </div>
                        </div>
                    @else
                        <div class="alert-feed-item lvl-{{ $alert['level'] }}">
                            <div class="afi-icon"><i class="fa-solid {{ $alert['icon'] }}"></i></div>
                            <div class="afi-body">
                                <div class="afi-msg">{!! $alert['message'] !!}</div>
                                @if(!empty($alert['time']))
                                    <div class="afi-meta"><i class="fa-regular fa-clock"></i> {{ $alert['time'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="alert-feed-empty">
                        <i class="fa-solid fa-circle-check"></i>
                        Tidak ada peringatan aktif. Gudang aman dan terkendali.
                    </div>
                @endforelse
            </div>
        </div>
    </div><!-- /dash-footer -->
</div>

<!-- DRILL-DOWN MODAL -->
<div class="drill-overlay" id="drillOverlay" role="dialog" aria-modal="true">
    <div class="drill-box" role="document">
        <div class="drill-head">
            <div class="drill-title" style="flex: 1;">
                <i class="fa-solid fa-layer-group"></i>
                <span id="drillTitle">Detail</span>
                <span class="badge" id="drillTotal" style="display:none;"></span>
            </div>
            <input type="text" id="drillFilter" class="form-control" placeholder="Filter data tabel..." style="max-width: 250px; font-size: 13px; display: none; margin-right: 15px;" />
            <button type="button" class="drill-close" id="drillClose" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="drill-body" id="drillBody">
            <div class="drill-loading"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data…</div>
        </div>
        <div class="drill-foot">
            <span class="drill-hint">Menampilkan maksimal 100 baris teratas.</span>
            <a href="#" id="drillFullLink" class="alert-action-btn secondary"><i class="fa-solid fa-up-right-from-square"></i> Buka halaman lengkap</a>
        </div>
    </div>
</div>

<style>
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.4; }
        100% { opacity: 1; }
    }

    /* Clickable stat cards (drill-down to detail) */
    .stat-card-link {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        position: relative;
    }
    .stat-card-link:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    }
    .stat-detail-hint {
        display: inline-block;
        margin-top: 8px;
        font-size: 11px;
        font-weight: 600;
        opacity: 0;
        color: var(--text-secondary);
        transition: opacity 0.15s ease;
    }
    .stat-card-link:hover .stat-detail-hint {
        opacity: 0.9;
    }
    
    .number-animate {
        animation: highlightData 1s ease-out;
    }

    @keyframes highlightData {
        0% { color: var(--accent-green); transform: scale(1.1); }
        100% { color: inherit; transform: scale(1); }
    }
</style>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    // ----- Bootstrap data from the server -----
    const CURRENT_VIEW = @json($view);
    let burnRateData = @json($burnRate);
    let distributionData = @json($distribution);

    let burnRateChart = null;
    let distributionChart = null;

    const DONUT_PALETTE = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#64748b'];

    function cssVar(name, fallback) {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name);
        return v && v.trim() ? v.trim() : fallback;
    }

    function distributionTitleFor(view) {
        return view === 'global' ? 'Stock Distribution by Warehouse' : 'Stock Distribution by Status';
    }

    function initCharts() {
        const textColor = cssVar('--text-secondary', '#94a3b8');
        const gridColor = cssVar('--border-color', 'rgba(148,163,184,0.15)');
        const accentRed = cssVar('--accent-red', '#ef4444');

        document.getElementById('distribution-title').innerText = distributionTitleFor(CURRENT_VIEW);

        burnRateChart = new Chart(document.getElementById('burnRateChart'), {
            type: 'line',
            data: {
                labels: burnRateData.labels,
                datasets: [{
                    label: 'Unit Keluar',
                    data: burnRateData.values,
                    borderColor: accentRed,
                    backgroundColor: 'rgba(239, 68, 68, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: textColor, maxTicksLimit: 10 }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: gridColor } }
                }
            }
        });

        distributionChart = new Chart(document.getElementById('distributionChart'), {
            type: 'doughnut',
            data: {
                labels: distributionData.labels,
                datasets: [{
                    data: distributionData.values,
                    backgroundColor: DONUT_PALETTE,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 12 } } }
            }
        });
    }

    function updateCharts(burnRate, distribution, view) {
        if (burnRateChart) {
            burnRateChart.data.labels = burnRate.labels;
            burnRateChart.data.datasets[0].data = burnRate.values;
            burnRateChart.update();
        }
        if (distributionChart) {
            distributionChart.data.labels = distribution.labels;
            distributionChart.data.datasets[0].data = distribution.values;
            distributionChart.update();
            document.getElementById('distribution-title').innerText = distributionTitleFor(view);
        }
    }

    // Pick the data slice matching the currently selected view from a broadcast payload.
    function sliceForView(payload, view) {
        if (!payload) return null;
        if (view === 'global') return payload.global;
        return (payload.warehouses && payload.warehouses[view]) ? payload.warehouses[view] : payload.global;
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();

        // Reload the dashboard scoped to the chosen warehouse.
        const filter = document.getElementById('warehouse-view-filter');
        if (filter) {
            filter.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('view', this.value);
                window.location.href = url.toString();
            });
        }

        if (typeof window.Echo !== 'undefined') {
            console.log('Echo connected, listening to dashboard-updates...');

            window.Echo.channel('dashboard-updates')
                .listen('GlobalStockUpdated', (e) => {
                    console.log('Live Data Received:', e);

                    const view = filter ? filter.value : CURRENT_VIEW;
                    const slice = sliceForView(e.payload, view) || { metrics: e.metrics, insights: e.insights, burnRate: burnRateData, distribution: distributionData };

                    // Update Metrics with animation
                    if (slice.metrics.total_in_stock !== undefined) updateMetric('val-in-stock', slice.metrics.total_in_stock);
                    if (slice.metrics.total_stock_baru !== undefined) updateMetric('val-stock-baru', slice.metrics.total_stock_baru);
                    if (slice.metrics.total_stock_bekas !== undefined) updateMetric('val-stock-bekas', slice.metrics.total_stock_bekas);
                    if (slice.metrics.total_issued !== undefined) updateMetric('val-issued', slice.metrics.total_issued);
                    if (slice.metrics.total_at_customer !== undefined) updateMetric('val-at-customer', slice.metrics.total_at_customer);
                    if (slice.metrics.total_pending_qc !== undefined) updateMetric('val-pending-qc', slice.metrics.total_pending_qc);
                    if (slice.metrics.total_qc_done !== undefined) updateMetric('val-qc-done', slice.metrics.total_qc_done);
                    if (slice.metrics.total_in_transit !== undefined) updateMetric('val-in-transit', slice.metrics.total_in_transit);
                    if (slice.metrics.total_installed !== undefined) updateMetric('val-installed', slice.metrics.total_installed);
                    if (slice.metrics.total_rejected !== undefined) updateMetric('val-rejected', slice.metrics.total_rejected);
                    if (slice.metrics.total_flagged !== undefined) updateMetric('val-flagged', slice.metrics.total_flagged);
                    if (slice.metrics.total_devices !== undefined) updateMetric('val-total', slice.metrics.total_devices);

                    // Aksesoris & Kartu GSM (real-time)
                    if (slice.metrics.total_accessories !== undefined) updateMetric('val-accessories', slice.metrics.total_accessories);
                    if (slice.metrics.total_sim_in_stock !== undefined) updateMetric('val-sim-stock', slice.metrics.total_sim_in_stock);
                    if (slice.metrics.total_sim_installed !== undefined) updateMetric('val-sim-installed', slice.metrics.total_sim_installed);

                    // Re-render Insights
                    renderInsights(slice.insights);

                    // Update charts for the selected view
                    if (slice.burnRate && slice.distribution) {
                        updateCharts(slice.burnRate, slice.distribution, view);
                    }

                    // Flash connection badge to indicate receive
                    const badge = document.getElementById('connection-status');
                    badge.style.transform = 'scale(1.1)';
                    setTimeout(() => badge.style.transform = 'scale(1)', 300);
                });
        } else {
            console.warn('Laravel Echo is not defined. Please run npm run dev/build.');
            document.getElementById('connection-status').innerHTML = '<i class="fa-solid fa-link-slash"></i> Offline';
            document.getElementById('connection-status').className = 'badge badge-danger';
        }
    });

    function updateMetric(id, newValue) {
        const el = document.getElementById(id);
        if (el && parseInt(el.innerText) !== parseInt(newValue)) {
            el.innerText = newValue;
            el.classList.remove('number-animate');
            void el.offsetWidth; // trigger reflow
            el.classList.add('number-animate');
        }
    }

    // ----- Drill-down modal -----
    (function () {
        const overlay   = document.getElementById('drillOverlay');
        const titleEl   = document.getElementById('drillTitle');
        const totalEl   = document.getElementById('drillTotal');
        const bodyEl    = document.getElementById('drillBody');
        const fullLink  = document.getElementById('drillFullLink');
        const closeBtn  = document.getElementById('drillClose');
        const filterEl  = document.getElementById('drillFilter');
        if (!overlay) return;

        const DRILL_BASE = @json(route('dashboard.drilldown'));
        const REPORTS_BASE = @json(route('reports'));

        function esc(v) {
            return String(v ?? '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
        }

        function openModal() { overlay.classList.add('open'); }
        function closeModal() { overlay.classList.remove('open'); }

        async function loadDrill(metric, fallbackUrl, area) {
            titleEl.innerText = 'Memuat…';
            totalEl.style.display = 'none';
            bodyEl.innerHTML = '<div class="drill-loading"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data…</div>';
            fullLink.href = area ? `${REPORTS_BASE}?tab=tech&area=${encodeURIComponent(area)}` : (fallbackUrl || '#');
            openModal();

            try {
                let url = `${DRILL_BASE}?metric=${encodeURIComponent(metric)}&view=${encodeURIComponent(CURRENT_VIEW)}`;
                if (area) url += `&area=${encodeURIComponent(area)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                titleEl.innerText = data.title || 'Detail';
                if (typeof data.total === 'number') {
                    totalEl.innerText = data.total + ' item';
                    totalEl.className = 'badge badge-info';
                    totalEl.style.display = '';
                }

                if (!data.rows || !data.rows.length) {
                    bodyEl.innerHTML = '<div class="drill-empty"><i class="fa-solid fa-inbox"></i><br>Tidak ada data untuk ditampilkan.</div>';
                    if(filterEl) filterEl.style.display = 'none';
                    return;
                }

                let html = '<table><thead><tr>';
                (data.columns || []).forEach(c => html += `<th>${esc(c)}</th>`);
                html += '</tr></thead><tbody>';
                data.rows.forEach(r => {
                    html += '<tr>' + r.map(cell => `<td>${esc(cell)}</td>`).join('') + '</tr>';
                });
                html += '</tbody></table>';
                bodyEl.innerHTML = html;
                if(filterEl) {
                    filterEl.style.display = 'inline-block';
                    filterEl.value = '';
                }
            } catch (e) {
                bodyEl.innerHTML = '<div class="drill-empty">Gagal memuat data. Coba buka halaman lengkap.</div>';
                if(filterEl) filterEl.style.display = 'none';
            }
        }

        if (filterEl) {
            filterEl.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                const trs = bodyEl.querySelectorAll('tbody tr');
                trs.forEach(tr => {
                    tr.style.display = tr.innerText.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }

        document.querySelectorAll('[data-drill]').forEach(el => {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                loadDrill(this.getAttribute('data-drill'), this.getAttribute('href'), this.getAttribute('data-area'));
            });
        });

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

        // --- Area Map Logic ---
        let areaMap = null;
        const areaData = @json($areaStock ?? []);
        
        const cityCoords = {
            'Surabaya': [-7.2504, 112.7688],
            'Malang': [-7.9839, 112.6214],
            'Semarang': [-6.9667, 110.4167],
            'Jakarta': [-6.2088, 106.8456],
            'Bandung': [-6.9175, 107.6191],
            'Yogyakarta': [-7.7956, 110.3695],
            'Sidoarjo': [-7.4478, 112.7183],
            'Gresik': [-7.1558, 112.6555],
            'Mojokerto': [-7.4726, 112.4337],
            'Madiun': [-7.6298, 111.5239],
            'Kediri': [-7.8200, 112.0150],
            'Jember': [-8.1725, 113.7000],
            'Banyuwangi': [-8.2192, 114.3692],
            'Pasuruan': [-7.6453, 112.9075],
            'Probolinggo': [-7.7569, 113.2115],
            'Tuban': [-6.8976, 112.0649],
            'Bojonegoro': [-7.1502, 111.8818],
            'Bekasi': [-6.2383, 106.9756],
            'Bali': [-8.4095, 115.1889],
            'Dumai': [1.6667, 101.4500],
            'Jogja': [-7.7956, 110.3695],
            'Balikpapan': [-1.2379, 116.8529],
            'Makassar': [-5.1477, 119.4327],
            'Padang': [-0.9471, 100.4172],
            'Solo': [-7.5755, 110.8243],
            'Sorowako': [-2.5316, 121.3571]
        };

        function initAreaMap() {
            if (areaMap) return;
            areaMap = L.map('areaMapContainer').setView([-7.2504, 112.7688], 7);
            
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
            }).addTo(areaMap);

            let bounds = [];
            const MIN_STOCK = 5; // Parameter stok minimum per area (bisa disesuaikan)

            Object.keys(areaData).forEach(area => {
                const data = areaData[area];
                const coords = cityCoords[area];
                if (coords) {
                    const total = parseInt(data.devices) + parseInt(data.sim) + parseInt(data.accessories);
                    
                    let statusClass = '';
                    if (total <= MIN_STOCK) {
                        statusClass = 'marker-danger'; // Merah (sama dengan atau di bawah minimum)
                    } else if (total <= MIN_STOCK * 1.5) {
                        statusClass = 'marker-warning'; // Kuning (menipis, misal <= 1.5 * minimum)
                    }

                    const balloonIcon = L.divIcon({
                        className: 'custom-map-balloon',
                        html: `<div class="marker-balloon ${statusClass}">
                                <div class="marker-title">${area}</div>
                                <div class="marker-val"><i class="fa-solid fa-box"></i> ${total}</div>
                            </div>`,
                        iconSize: [80, 40],
                        iconAnchor: [40, 40]
                    });

                    const marker = L.marker(coords, {icon: balloonIcon}).addTo(areaMap);
                    bounds.push(coords);
                    
                    marker.on('click', () => {
                        const link = `${REPORTS_BASE}?tab=tech&area=${encodeURIComponent(area)}`;
                        loadDrill('area_field', link, area);
                    });
                }
            });
            
            if (bounds.length > 0) {
                areaMap.fitBounds(bounds, {padding: [50, 50]});
            }
        }

        const btnMap = document.getElementById('btn-view-map');
        const btnTable = document.getElementById('btn-view-table');
        const tableContainer = document.getElementById('areaTableContainer');
        const mapContainer = document.getElementById('areaMapContainer');

        if(btnMap && btnTable) {
            btnMap.addEventListener('click', function() {
                tableContainer.style.display = 'none';
                mapContainer.style.display = 'block';
                this.classList.add('active');
                btnTable.classList.remove('active');
                initAreaMap();
                areaMap.invalidateSize();
            });

            btnTable.addEventListener('click', function() {
                tableContainer.style.display = 'block';
                mapContainer.style.display = 'none';
                this.classList.add('active');
                btnMap.classList.remove('active');
            });
        }
    })();

    function renderInsights(insights) {
        const container = document.getElementById('warehouse-alerts-container');
        if (!container || !insights) return;
        container.innerHTML = '';
        
        let hasInsights = false;

        function renderItem(i, level) {
            hasInsights = true;
            let actionBtn = '';
            
            // Add Restock buttons based on icon (e.g. device/accessory warning)
            if (i.icon === 'fa-microchip') {
                actionBtn = `<a href="/receiving" class="alert-action-btn"><i class="fa-solid fa-truck-ramp-box"></i> Restock</a>`;
            } else if (i.icon === 'fa-plug') {
                actionBtn = `<a href="/receiving?tab=accessory" class="alert-action-btn"><i class="fa-solid fa-truck-ramp-box"></i> Restock</a>`;
            }

            container.innerHTML += `
                <div class="alert-feed-item lvl-${level}" style="animation: fade-in 0.5s;">
                    <div class="afi-icon"><i class="fa-solid ${i.icon}"></i></div>
                    <div class="afi-body">
                        <div class="afi-msg">${i.message}</div>
                        ${i.time ? `<div class="afi-meta"><i class="fa-regular fa-clock"></i> ${i.time}</div>` : ''}
                        ${actionBtn}
                    </div>
                </div>`;
        }

        // Render critical, warning, info in order
        if (insights.critical) insights.critical.forEach(i => renderItem(i, 'critical'));
        if (insights.warning) insights.warning.forEach(i => renderItem(i, 'warning'));
        if (insights.info) insights.info.forEach(i => renderItem(i, 'info'));

        if (!hasInsights) {
            container.innerHTML = `
                <div class="alert-feed-empty">
                    <i class="fa-solid fa-circle-check"></i>
                    Tidak ada peringatan aktif. Gudang aman dan terkendali.
                </div>`;
        }
    }

    // ==========================================
    // STOK DEVICE LOGIC
    // ==========================================
    (function () {
        const STOCK_API       = '/api/dashboard/stock';
        const DETAIL_API      = '/api/dashboard/stock/details';
        const grid            = document.getElementById('devStockGrid');
        const detailWrap      = document.getElementById('devStockDetailWrap');
        const detailTitle     = document.getElementById('devStockDetailTitle');
        const detailBody      = document.getElementById('devStockDetailBody');
        const closeDetailBtn  = document.getElementById('devStockDetailClose');

        let activeType = null;

        const COLORS = ['#6366f1','#10b981','#f59e0b','#3b82f6','#ec4899','#8b5cf6','#14b8a6','#f97316'];

        function colorFor(str) {
            let h = 0;
            for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) & 0xffffffff;
            return COLORS[Math.abs(h) % COLORS.length];
        }

        function loadGrid() {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin" style="font-size:24px;"></i><p style="margin-top:10px;">Memuat data stok...</p></div>';
            detailWrap.style.display = 'none';
            activeType = null;

            fetch(`${STOCK_API}`)
                .then(r => r.json())
                .then(data => {
                    grid.innerHTML = '';
                    if (data.length === 0) {
                        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);"><i class="fa-solid fa-box-open" style="font-size:32px;"></i><p style="margin-top:10px;">Tidak ada stok ditemukan.</p></div>';
                        return;
                    }
                    // Set grid columns dynamically based on item count
                    let cols = data.length;
                    if (cols === 2) grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                    else if (cols === 3) grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
                    else grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';

                    data.forEach(item => {
                        const typeName = item.type || 'Tipe Lain';
                        const c     = colorFor(typeName);
                        const card  = document.createElement('div');
                        card.style.cssText = `
                            background: var(--bg-secondary);
                            border: 1px solid var(--border-color);
                            border-radius: 12px;
                            padding: 18px 14px;
                            cursor: pointer;
                            transition: all .2s ease;
                            border-top: 3px solid ${c};
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 8px;
                            text-align: center;
                        `;
                        card.dataset.type  = typeName;
                        card.innerHTML = `
                            <div style="width:42px;height:42px;border-radius:50%;background:${c}22;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-microchip" style="color:${c};font-size:18px;"></i>
                            </div>
                            <div style="font-size:22px;font-weight:700;color:${c};">${item.total}</div>
                            <div style="font-size:14px;font-weight:600;color:var(--text-primary);line-height:1.3;">${typeName}</div>
                        `;
                        card.addEventListener('mouseenter', () => {
                            card.style.transform = 'translateY(-3px)';
                            card.style.boxShadow = `0 8px 20px ${c}33`;
                        });
                        card.addEventListener('mouseleave', () => {
                            if (!card.classList.contains('dev-stock-active')) {
                                card.style.transform = '';
                                card.style.boxShadow = '';
                            }
                        });
                        card.addEventListener('click', () => {
                            document.querySelectorAll('.dev-stock-active').forEach(el => {
                                el.classList.remove('dev-stock-active');
                                el.style.transform = '';
                                el.style.boxShadow = '';
                            });
                            card.classList.add('dev-stock-active');
                            card.style.boxShadow = `0 8px 20px ${c}44`;
                            loadDetail(item.type);
                        });
                        grid.appendChild(card);
                    });
                })
                .catch(() => {
                    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">Gagal memuat data. Coba refresh halaman.</div>';
                });
        }

        function loadDetail(type) {
            activeType = type;
            detailWrap.style.display = 'block';
            detailTitle.textContent = `Detail Stok — ${type || 'Semua'}`;
            detailBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;"><i class="fa-solid fa-spinner fa-spin"></i> Memuat...</td></tr>';
            detailWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            fetch(`${DETAIL_API}?type=${encodeURIComponent(type || '')}`)
                .then(r => r.json())
                .then(rows => {
                    if (rows.length === 0) {
                        detailBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">Tidak ada data.</td></tr>';
                        return;
                    }
                    detailBody.innerHTML = rows.map(d => {
                        const kondisi = d.unit_condition === 'BEKAS'
                            ? '<span class="badge badge-warning" style="font-size:10px;">BEKAS</span>'
                            : '<span class="badge badge-success" style="font-size:10px;">BARU</span>';
                        return `<tr>
                            <td style="font-weight:600;color:var(--accent-blue);">${d.serial_number}</td>
                            <td>${d.model || '-'}</td>
                            <td>${d.type || '-'}</td>
                            <td style="text-align:center;">${kondisi}</td>
                            <td style="font-size:12px;color:var(--text-muted);">${d.rack_barcode || '—'}</td>
                        </tr>`;
                    }).join('');
                });
        }

        closeDetailBtn.addEventListener('click', () => {
            detailWrap.style.display = 'none';
            activeType = null;
            document.querySelectorAll('.dev-stock-active').forEach(el => {
                el.classList.remove('dev-stock-active');
                el.style.transform = '';
                el.style.boxShadow = '';
            });
        });

        // Initialize grid on load
        loadGrid();
    })();
</script>
@endsection
