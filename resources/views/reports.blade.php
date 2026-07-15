@extends('layouts.app')

@section('title', 'Laporan & Analitik | DLMS')

@php
    $period = $filters['period'];
    $whCode = $rawWarehouse ?? 'all';
    $exportParams = ['from' => $fromDate, 'to' => $toDate, 'period' => $period, 'warehouse' => $whCode];
@endphp

@section('styles')
<style>
    .cal-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--text-secondary); }
    .cal-legend .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
    .cal-legend .dot.in { background: var(--accent-emerald); }
    .cal-legend .dot.out { background: var(--accent-amber); }
    .cal-legend .dot.pos { background: rgba(16,185,129,0.55); }
    .cal-legend .dot.neg { background: rgba(239,68,68,0.55); }

    /* REVISI RESPONSIVE GRID KALENDER */
    .cal-wrapper { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
        gap: 24px; 
    }
    
    @media (min-width: 1200px) {
        .cal-wrapper {
            grid-template-columns: repeat(4, 1fr); /* Tetap 4 kolom sejajar jika layar sangat lebar */
        }
    }

    @media (max-width: 1199px) and (min-width: 768px) {
        .cal-wrapper {
            grid-template-columns: repeat(2, 1fr); /* Pecah jadi 2x2 di layar laptop kecil / tablet */
        }
    }

    .cal-month-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; text-transform: capitalize; }
    
    /* Memastikan grid hari tidak pecah */
    .cal-grid { 
        display: grid; 
        grid-template-columns: repeat(7, 1fr); 
        gap: 4px; 
    }
    
    .cal-dow { text-align: center; font-size: 11px; font-weight: 600; color: var(--text-muted); padding: 4px 0; }
    
    /* Memberikan fleksibilitas tinggi cell kalender agar konten angka mutasi tidak menumpuk */
    .cal-cell {
        position: relative; 
        min-height: 64px; 
        border: 1px solid var(--border-color); 
        border-radius: 8px;
        padding: 6px; 
        background: var(--bg-primary); 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between;
        gap: 2px;
        overflow: hidden;
    }
    .cal-cell.empty { background: transparent; border: none; }
    .cal-cell.out-range { opacity: 0.35; }
    .cal-daynum { font-size: 11px; font-weight: 600; color: var(--text-secondary); }
    
    /* Nilai Masuk / Keluar */
    .cal-vals { 
        display: flex; 
        justify-content: space-between;
        gap: 2px; 
        font-size: 10px; 
        font-weight: 700; 
        line-height: 1; 
        flex-wrap: wrap; /* Supaya panah in/out turun rapi jika kolom menyempit */
    }
    .cal-vals .v-in { color: var(--accent-emerald); }
    .cal-vals .v-out { color: var(--accent-amber); }
    .cal-net { font-size: 9px; font-weight: 600; color: var(--text-muted); text-align: right; margin-top: auto; }
    
    .cal-cell.net-pos { border-color: rgba(16,185,129,0.5); background: rgba(16,185,129,0.08); }
    .cal-cell.net-neg { border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.08); }
    .cal-cell.net-zero { border-color: var(--border-color); }
    .cal-cell.today { box-shadow: 0 0 0 2px var(--accent-blue); }
    .cal-cell.today .cal-daynum { color: var(--accent-blue); }

    /* REVISI RESPONSIVE UNTUK FORM FILTER BAR */
    .filter-form-responsive {
        display: flex; 
        flex-wrap: wrap; 
        gap: 16px; 
        align-items: flex-end;
    }
    .filter-form-responsive .form-group {
        flex: 1 1 200px; /* Membuat input melebar proporsional dan membungkus otomatis */
        margin: 0;
    }
    .filter-form-responsive .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    /* Export Dropdown */
    .export-dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 220px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.25);
        z-index: 100;
        padding: 6px 0;
        animation: fadeSlideDown 0.15s ease-out;
    }
    .export-dropdown-menu.show { display: block; }
    .export-dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        color: var(--text-primary);
        text-decoration: none;
        font-size: 13px;
        transition: background 0.15s;
    }
    .export-dropdown-item:hover {
        background: var(--bg-tertiary);
        color: var(--text-primary);
    }
    @keyframes fadeSlideDown {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Style wrapper & table for dark theme */
    .dataTables_wrapper {
        color: var(--text-primary) !important;
        font-family: 'Outfit', sans-serif;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        color: var(--text-secondary) !important;
        margin-bottom: 12px;
    }
    /* Style length select dropdown */
    .dataTables_wrapper .dataTables_length select {
        background-color: var(--bg-secondary) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-primary) !important;
        border-radius: 8px !important;
        padding: 6px 12px !important;
    }
    /* Style buttons (colvis) */
    .dt-buttons {
        margin-bottom: 16px;
    }
    .dt-button.buttons-excel {
        background: #16a34a !important;
        border: 1px solid #15803d !important;
        color: #ffffff !important;
        border-radius: 8px !important;
        padding: 8px 16px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        margin-right: 8px !important;
    }
    .dt-button.buttons-excel:hover {
        background: #15803d !important;
    }
    .dt-button.buttons-colvis {
        background: var(--bg-secondary) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-primary) !important;
        border-radius: 8px !important;
        padding: 8px 16px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }
    .dt-button.buttons-colvis:hover {
        background: var(--bg-tertiary) !important;
        border-color: var(--accent-blue) !important;
    }
    /* Style column visibility collection dropdown list */
    .dt-button-collection {
        background-color: var(--bg-secondary) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        padding: 8px !important;
        z-index: 2002 !important;
    }
    .dt-button-collection .dt-button {
        background: transparent !important;
        border: none !important;
        color: var(--text-secondary) !important;
        text-align: left !important;
        padding: 8px 12px !important;
        display: block !important;
        width: 100% !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
    }
    .dt-button-collection .dt-button.active {
        background: rgba(59, 130, 246, 0.15) !important;
        color: var(--accent-blue) !important;
        font-weight: 600 !important;
    }
    .dt-button-collection .dt-button:hover {
        background: var(--bg-tertiary) !important;
        color: var(--text-primary) !important;
    }
    /* Style pagination buttons */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        background: var(--bg-secondary) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-secondary) !important;
        border-radius: 8px !important;
        padding: 6px 12px !important;
        margin: 0 3px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--bg-tertiary) !important;
        border-color: var(--accent-blue) !important;
        color: var(--text-primary) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--accent-blue) !important;
        border-color: var(--accent-blue) !important;
        color: white !important;
        font-weight: 600 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        opacity: 0.4 !important;
        cursor: default !important;
        background: transparent !important;
        border-color: var(--border-color) !important;
    }
    /* Style table headers and cells */
    #unifiedPreviewTable {
        border-collapse: collapse !important;
    }
    #unifiedPreviewTable th,
    #unifiedPreviewTable td {
        border-color: var(--border-color) !important;
    }
    html.light-theme .dt-button-collection {
        background-color: #ffffff !important;
        border-color: #e2e8f0 !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    }
    html.light-theme .dt-button.buttons-colvis {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
        color: #1e293b !important;
    }
    html.light-theme .dataTables_wrapper .dataTables_paginate .paginate_button {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #1e293b !important;
    }

    /* Section headers style */
    .sec-hdr-1 { background-color: #fca5a5; color: #7f1d1d; }
    .sec-hdr-2 { background-color: #bbf7d0; color: #14532d; }
    .sec-hdr-3 { background-color: #fed7aa; color: #7c2d12; }
    .sec-hdr-4 { background-color: #e9d5ff; color: #581c87; }

    html.dark-theme .sec-hdr-1 { background-color: #991b1b; color: #fecaca; }
    html.dark-theme .sec-hdr-2 { background-color: #065f46; color: #a7f3d0; }
    html.dark-theme .sec-hdr-3 { background-color: #9a3412; color: #ffedd5; }
    html.dark-theme .sec-hdr-4 { background-color: #5b21b6; color: #f3e8ff; }

    .dt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-chart-line"
        title="Laporan & Analitik"
        subtitle="Mutasi barang, stok teknisi, aging, kualitas, dan audit koreksi — dengan filter periode & gudang.">
        <div style="position: relative; display: inline-block;" id="exportDropdownWrap">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('exportMenu').classList.toggle('show')" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-download"></i> Cetak / Ekspor <i class="fa-solid fa-chevron-down" style="font-size: 10px;"></i>
            </button>
            <div id="exportMenu" class="export-dropdown-menu">
                <a href="{{ route('reports.print', $exportParams) }}" target="_blank" class="export-dropdown-item">
                    <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Cetak / Simpan PDF
                </a>
                <a href="#" class="export-dropdown-item" onclick="openExportModal('excel')">
                    <i class="fa-solid fa-file-excel" style="color: #16a34a;"></i> Download Excel (.xlsx)
                </a>
                <a href="#" class="export-dropdown-item" onclick="openExportModal('csv')">
                    <i class="fa-solid fa-file-csv" style="color: #3b82f6;"></i> Download CSV
                </a>
            </div>
        </div>
    </x-page-header>

    <!-- Filter Bar -->
<div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="{{ route('reports') }}" id="filterForm" class="filter-form-responsive">
            <div class="form-group">
                <label>Dari Tanggal</label>
                <input type="date" name="from" value="{{ $fromDate }}" class="form-control">
            </div>
            <div class="form-group">
                <label>Sampai Tanggal</label>
                <input type="date" name="to" value="{{ $toDate }}" class="form-control">
            </div>
            <div class="form-group">
                <label>Periode Grafik</label>
                <select name="period" class="form-control">
                    <option value="day" {{ $period === 'day' ? 'selected' : '' }}>Harian</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Mingguan</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Bulanan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Gudang</label>
                @php
                    $activeWhCode = session('active_warehouse_code');
                    $isGlobal     = session('global_mode');
                    $isRegional   = is_string($activeWhCode) && preg_match('/^__region_([A-Z]+)__$/', $activeWhCode);
                    $isBranch     = !$isGlobal && !$isRegional && $activeWhCode;
                    $isBound      = auth()->user()?->isWarehouseBound();
                @endphp

                @if($isBound || $isBranch)
                    {{-- Cabang: terkunci, tidak bisa ganti --}}
                    <input type="hidden" name="warehouse" value="{{ $whCode }}">
                    <div class="form-control" style="background: var(--bg-tertiary); cursor: default;">
                        <i class="fa-solid fa-lock" style="color: var(--text-muted); font-size: 11px;"></i>
                        {{ collect($warehouses)->firstWhere('code', $whCode)?->name ?? $whCode }}
                    </div>
                @elseif($isGlobal)
                    {{-- Global: bisa pilih Semua, East Area, West Area --}}
                    <select name="warehouse" class="form-control">
                        <option value="all" {{ $whCode === 'all' ? 'selected' : '' }}>Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}" {{ $whCode === $wh->code ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @elseif($isRegional)
                    {{-- Regional: bisa pilih Semua Regional atau salah satu cabang --}}
                    <select name="warehouse" class="form-control">
                        <option value="{{ $activeWhCode }}" {{ $whCode === $activeWhCode ? 'selected' : '' }}>Semua ({{ preg_match('/^__region_([A-Z]+)__$/', $activeWhCode, $_m) ? $_m[1] : '' }} Area)</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}" {{ $whCode === $wh->code ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @else
                    <select name="warehouse" class="form-control">
                        <option value="all" {{ $whCode === 'all' ? 'selected' : '' }}>Semua Gudang</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}" {{ $whCode === $wh->code ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Terapkan</button>
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="btn btn-outline btn-preset" data-days="6" style="padding: 8px 12px;">7 Hari</button>
                    <button type="button" class="btn btn-outline btn-preset" data-days="29" style="padding: 8px 12px;">30 Hari</button>
                    <button type="button" class="btn btn-outline btn-preset" data-days="89" style="padding: 8px 12px;">90 Hari</button>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid">
        <div class="stat-card emerald">
            <div class="stat-icon"><i class="fa-solid fa-arrow-down-to-bracket"></i></div>
            <div class="stat-details">
                <h3>Barang Masuk (periode)</h3>
                <div class="stat-value">{{ $executive['total_in'] }}</div>
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon"><i class="fa-solid fa-arrow-up-from-bracket"></i></div>
            <div class="stat-details">
                <h3>Barang Keluar (periode)</h3>
                <div class="stat-value">{{ $executive['total_out'] }}</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="stat-details">
                <h3>Net Mutasi</h3>
                <div class="stat-value">{{ $executive['net'] > 0 ? '+' : '' }}{{ $executive['net'] }}</div>
            </div>
        </div>
        <div class="stat-card indigo">
            <div class="stat-icon"><i class="fa-solid fa-people-carry-box"></i></div>
            <div class="stat-details">
                <h3>Di Tangan Teknisi</h3>
                <div class="stat-value">{{ $statusStats['ISSUED'] }}</div>
            </div>
        </div>
    </div>
    <!-- ============ TAB NAVIGATION ============ -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin: 24px 0 16px; flex-wrap: wrap;">
        <button class="btn btn-outline report-tab-btn active-tab" data-tab="stok-barang"
            style="border: none; border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; color: var(--text-primary); font-weight: 600; cursor: pointer;">
            <i class="fa-solid fa-boxes-stacked" style="margin-right: 4px;"></i> Laporan Stok Barang
        </button>
        <button class="btn btn-outline report-tab-btn" data-tab="barang-masuk"
            style="border: none; border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; color: var(--text-secondary); cursor: pointer;">
            <i class="fa-solid fa-arrow-down-long" style="margin-right: 4px;"></i> Barang Masuk
        </button>
        <button class="btn btn-outline report-tab-btn" data-tab="barang-keluar"
            style="border: none; border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; color: var(--text-secondary); cursor: pointer;">
            <i class="fa-solid fa-arrow-up-long" style="margin-right: 4px;"></i> Barang Keluar
        </button>
        <button class="btn btn-outline report-tab-btn" data-tab="stok-teknisi"
            style="border: none; border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; color: var(--text-secondary); cursor: pointer;">
            <i class="fa-solid fa-user-gear" style="margin-right: 4px;"></i> Stok Teknisi
        </button>
    </div>

    <!-- ============ PANEL: LAPORAN STOK BARANG ============ -->
    <div class="report-panel" id="panel-stok-barang">
        <div class="card">
            <div class="card-header sec-hdr-1" style="border-radius: 12px 12px 0 0; padding: 12px 16px;">
                <div class="card-title" style="color: inherit; font-size: 15px; font-weight: 700;"><i class="fa-solid fa-boxes-stacked"></i> Laporan Stok Barang</div>
            </div>
            <div class="table-wrapper" style="overflow-x: auto; padding: 16px; background: var(--bg-secondary); border-radius: 0 0 12px 12px;">
                <table class="table table-bordered table-striped" id="tableStokBarang" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th style="text-align: center;">Stok Awal</th>
                            <th style="text-align: center;">Barang Masuk</th>
                            <th style="text-align: center;">Barang Keluar</th>
                            <th style="text-align: center;">Sisa Stok</th>
                            <th style="text-align: center;">Barang Bekas</th>
                            <th style="text-align: center; font-weight: bold;">STOCK AKHIR (Baru)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deviceRows as $r)
                            @php 
                                $bekas = $bekasByModel[$r['name']] ?? 0;
                                $stockAkhir = max(0, $r['closing'] - $bekas);
                            @endphp
                            <tr>
                                <td style="font-weight: 600;">{{ $r['name'] }}</td>
                                <td>Pcs</td>
                                <td style="text-align: center;">{{ $r['opening'] }}</td>
                                <td style="text-align: center;">{{ $r['in'] }}</td>
                                <td style="text-align: center;">{{ $r['out'] }}</td>
                                <td style="text-align: center; font-weight: 600;">{{ $r['closing'] }}</td>
                                <td style="text-align: center; color: var(--accent-amber);">{{ $bekas }}</td>
                                <td style="text-align: center; font-weight: bold; color: var(--accent-emerald);">{{ $stockAkhir }}</td>
                            </tr>
                        @endforeach
                        @foreach ($stockcard['accessory']['rows'] ?? [] as $r)
                            @php 
                                $stockAkhir = max(0, $r['closing']);
                            @endphp
                            <tr>
                                <td style="font-weight: 600; color: var(--accent-orange);">ACC: {{ $r['name'] }}</td>
                                <td>Pcs</td>
                                <td style="text-align: center;">{{ $r['opening'] }}</td>
                                <td style="text-align: center; color: var(--accent-emerald);">{{ $r['in'] }}</td>
                                <td style="text-align: center; color: var(--accent-rose);">{{ $r['out'] }}</td>
                                <td style="text-align: center;">{{ $r['closing'] }}</td>
                                <td style="text-align: center; color: var(--accent-amber);">-</td>
                                <td style="text-align: center; font-weight: bold; color: var(--accent-emerald);">{{ $stockAkhir }}</td>
                            </tr>
                        @endforeach
                        @foreach ($stockcard['gsm']['rows'] ?? [] as $r)
                            @php 
                                $stockAkhir = max(0, $r['closing']);
                            @endphp
                            <tr>
                                <td style="font-weight: 600; color: var(--accent-indigo);">GSM: {{ $r['name'] }}</td>
                                <td>Pcs</td>
                                <td style="text-align: center;">{{ $r['opening'] }}</td>
                                <td style="text-align: center; color: var(--accent-emerald);">{{ $r['in'] }}</td>
                                <td style="text-align: center; color: var(--accent-rose);">{{ $r['out'] }}</td>
                                <td style="text-align: center;">{{ $r['closing'] }}</td>
                                <td style="text-align: center; color: var(--accent-amber);">-</td>
                                <td style="text-align: center; font-weight: bold; color: var(--accent-emerald);">{{ $stockAkhir }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ PANEL: BARANG MASUK ============ -->
    <div class="report-panel" id="panel-barang-masuk" style="display: none;">
        <div class="card">
            <div class="card-header sec-hdr-2" style="border-radius: 12px 12px 0 0; padding: 12px 16px; display:flex; align-items:center; justify-content:space-between;">
                <div class="card-title" style="color: inherit; font-size: 15px; font-weight: 700;"><i class="fa-solid fa-arrow-down-long"></i> Riwayat Barang Masuk</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-outline" id="btnMasukSummary" onclick="switchMasukView('summary')" style="font-size:12px; padding:4px 12px;"><i class="fa-solid fa-table-cells"></i> Summary</button>
                    <button class="btn btn-primary" id="btnMasukPerSN" onclick="switchMasukView('persn')" style="font-size:12px; padding:4px 12px;"><i class="fa-solid fa-list"></i> Per SN</button>
                </div>
            </div>
            <!-- Summary Barang Masuk -->
            <div id="viewMasukSummary" style="display:none; padding:16px; background:var(--bg-secondary);">
                <table class="table table-bordered table-striped" id="tableMasukSummary" style="width:100%; font-size:13px;">
                    <thead><tr><th>Tgl</th><th>Kode Barang</th><th>Nama Barang</th><th style="text-align:center;">Jumlah</th><th>Satuan</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @php
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
                            foreach($accInTransactions as $t) { 
                                $date = $t->created_at->format('d/m/Y');
                                $code = $t->accessory_code;
                                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                                $ket = $t->to_location ?? '-';
                                $key = "$date|$code|$name|$ket";
                                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                                $summaryMasuk[$key]['qty'] += $t->qty;
                            }
                            foreach($gsmInTransactions as $t) { 
                                $date = $t->created_at->format('d/m/Y');
                                $code = 'GSM';
                                $name = 'GSM / SIMCARD';
                                $ket = $t->to_location ?? '-';
                                $key = "$date|$code|$name|$ket";
                                if(!isset($summaryMasuk[$key])) $summaryMasuk[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                                $summaryMasuk[$key]['qty'] += 1;
                            }
                            usort($summaryMasuk, function($a, $b) { return strtotime(str_replace('/','-',$b['date'])) <=> strtotime(str_replace('/','-',$a['date'])); });
                        @endphp
                        @foreach($summaryMasuk as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td style="text-align:center; font-weight:bold;">{{ $row['qty'] }}</td>
                            <td>Pcs</td>
                            <td>{{ $row['ket'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <!-- Per SN Barang Masuk -->
            <div id="viewMasukPerSN" style="display:block;">
            <div class="table-wrapper" style="overflow-x: auto; padding: 16px; background: var(--bg-secondary); border-radius: 0 0 12px 12px;">
                <table class="table table-bordered table-striped" id="tableBarangMasuk" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Tgl</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th style="text-align: center;">Jumlah</th>
                            <th>Satuan</th>
                            <th>Rak / Posisi</th>
                            <th>Dipegang Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inTransactions as $tin)
                            <tr>
                                <td>{{ $tin->created_at->format('d/m/y') }}</td>
                                <td>{{ $tin->device_sn }}</td>
                                <td>{{ $tin->device->model ?? '-' }}</td>
                                <td style="text-align: center;">1</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-blue);">{{ $tin->device->rack_barcode ?? '—' }}</td>
                                <td style="font-size:12px;">{{ $tin->to_location ?: ($tin->device->current_holder ?? '—') }}</td>
                            </tr>
                        @endforeach
                        @foreach ($accInTransactions as $ain)
                            <tr>
                                <td>{{ $ain->created_at->format('d/m/y') }}</td>
                                <td style="color: var(--accent-orange);">{{ $ain->accessory_code }}</td>
                                <td>ACC: {{ $ain->accessory->name ?? '-' }}</td>
                                <td style="text-align: center; font-weight: bold;">{{ $ain->qty }}</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-blue);">-</td>
                                <td style="font-size:12px;">{{ $ain->to_location ?? '-' }}</td>
                            </tr>
                        @endforeach
                        @foreach ($gsmInTransactions as $gin)
                            <tr>
                                <td>{{ $gin->created_at->format('d/m/y') }}</td>
                                <td style="color: var(--accent-indigo);">{{ $gin->msisdn }}</td>
                                <td>GSM / SIMCARD</td>
                                <td style="text-align: center; font-weight: bold;">1</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-blue);">-</td>
                                <td style="font-size:12px;">{{ $gin->to_location ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>

    <!-- ============ PANEL: BARANG KELUAR ============ -->
    <div class="report-panel" id="panel-barang-keluar" style="display: none;">
        <div class="card">
            <div class="card-header sec-hdr-3" style="border-radius: 12px 12px 0 0; padding: 12px 16px; display:flex; align-items:center; justify-content:space-between;">
                <div class="card-title" style="color: inherit; font-size: 15px; font-weight: 700;"><i class="fa-solid fa-arrow-up-long"></i> Riwayat Barang Keluar</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-outline" id="btnKeluarSummary" onclick="switchKeluarView('summary')" style="font-size:12px; padding:4px 12px;"><i class="fa-solid fa-table-cells"></i> Summary</button>
                    <button class="btn btn-primary" id="btnKeluarPerSN" onclick="switchKeluarView('persn')" style="font-size:12px; padding:4px 12px;"><i class="fa-solid fa-list"></i> Per SN</button>
                </div>
            </div>
            <!-- Summary Barang Keluar -->
            <div id="viewKeluarSummary" style="display:none; padding:16px; background:var(--bg-secondary);">
                <table class="table table-bordered table-striped" id="tableKeluarSummary" style="width:100%; font-size:13px;">
                    <thead><tr><th>Tgl</th><th>Kode Barang</th><th>Nama Barang</th><th style="text-align:center;">Jumlah</th><th>Satuan</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @php
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
                            foreach($accOutTransactions as $t) { 
                                $date = $t->created_at->format('d/m/Y');
                                $code = $t->accessory_code;
                                $name = 'ACC: '.($t->accessory->name ?? $t->accessory_code);
                                $ket = $t->to_location ?? '-';
                                $key = "$date|$code|$name|$ket";
                                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                                $summaryKeluar[$key]['qty'] += $t->qty;
                            }
                            foreach($gsmOutTransactions as $t) { 
                                $date = $t->created_at->format('d/m/Y');
                                $code = 'GSM';
                                $name = 'GSM / SIMCARD';
                                $ket = $t->to_location ?? '-';
                                $key = "$date|$code|$name|$ket";
                                if(!isset($summaryKeluar[$key])) $summaryKeluar[$key] = ['date'=>$date, 'code'=>$code, 'name'=>$name, 'qty'=>0, 'ket'=>$ket];
                                $summaryKeluar[$key]['qty'] += 1;
                            }
                            usort($summaryKeluar, function($a, $b) { return strtotime(str_replace('/','-',$b['date'])) <=> strtotime(str_replace('/','-',$a['date'])); });
                        @endphp
                        @foreach($summaryKeluar as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td style="text-align:center; font-weight:bold;">{{ $row['qty'] }}</td>
                            <td>Pcs</td>
                            <td>{{ $row['ket'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <!-- Per SN Barang Keluar -->
            <div id="viewKeluarPerSN" style="display:block;">
            <div class="table-wrapper" style="overflow-x: auto; padding: 16px; background: var(--bg-secondary); border-radius: 0 0 12px 12px;">
                <table class="table table-bordered table-striped" id="tableBarangKeluar" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Tgl</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th style="text-align: center;">Jumlah</th>
                            <th>Satuan</th>
                            <th>Rak / Posisi</th>
                            <th>Dipegang Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($outTransactions as $tout)
                            <tr>
                                <td>{{ $tout->created_at->format('d/m/y') }}</td>
                                <td>{{ $tout->device_sn }}</td>
                                <td>{{ $tout->device->model ?? '-' }}</td>
                                <td style="text-align: center;">1</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-amber);">{{ $tout->device->rack_barcode ?? '—' }}</td>
                                <td style="font-size:12px;">{{ $tout->to_location ?: ($tout->device->current_holder ?? '—') }}</td>
                            </tr>
                        @endforeach
                        @foreach ($accOutTransactions as $aout)
                            <tr>
                                <td>{{ $aout->created_at->format('d/m/y') }}</td>
                                <td style="color: var(--accent-orange);">{{ $aout->accessory_code }}</td>
                                <td>ACC: {{ $aout->accessory->name ?? '-' }}</td>
                                <td style="text-align: center; font-weight: bold;">{{ $aout->qty }}</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-amber);">-</td>
                                <td style="font-size:12px;">{{ $aout->to_location ?? '-' }}</td>
                            </tr>
                        @endforeach
                        @foreach ($gsmOutTransactions as $gout)
                            <tr>
                                <td>{{ $gout->created_at->format('d/m/y') }}</td>
                                <td style="color: var(--accent-indigo);">{{ $gout->msisdn }}</td>
                                <td>GSM / SIMCARD</td>
                                <td style="text-align: center; font-weight: bold;">1</td>
                                <td>Pcs</td>
                                <td style="font-size:12px; color:var(--accent-amber);">-</td>
                                <td style="font-size:12px;">{{ $gout->to_location ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>

    <!-- ============ PANEL: STOK TEKNISI ============ -->
    <div class="report-panel" id="panel-stok-teknisi" style="display: none;">
        <div class="card">
            <div class="card-header sec-hdr-4" style="border-radius: 12px 12px 0 0; padding: 12px 16px;">
                <div class="card-title" style="color: inherit; font-size: 15px; font-weight: 700;"><i class="fa-solid fa-user-gear"></i> Aset di Tangan Teknisi</div>
            </div>
            <div style="background: var(--bg-secondary); border-radius: 0 0 12px 12px; padding: 16px;">
                @if ($techniciansList->isEmpty())
                    <div style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                        <i class="fa-solid fa-user-slash" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                        <div style="font-size: 15px; font-weight: 600; margin-bottom: 6px;">Tidak ada aset di tangan teknisi</div>
                        <div style="font-size: 13px;">Tidak ada perangkat dengan status ISSUED/INSTALLED yang tercatat di gudang ini.</div>
                    </div>
                @else
                    <div class="table-wrapper" style="overflow-x: auto;">
                        <table class="table table-bordered table-striped" id="tableStokTeknisi" style="width: 100%; font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Nama Barang</th>
                                    @foreach ($techniciansList as $tech)
                                        <th style="text-align: center;">{{ $tech->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_keys($techStockMatrix) as $modelName)
                                    <tr>
                                        <td style="font-weight: 600;">{{ $modelName }}</td>
                                        @foreach ($techniciansList as $tech)
                                            @php
                                                $qty = $techStockMatrix[$modelName][$tech->name] ?? '';
                                            @endphp
                                            <td style="text-align: center; font-weight: {{ $qty ? '700' : '400' }}; color: {{ $qty ? 'var(--accent-emerald)' : 'inherit' }};">{{ $qty }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>


{{-- Modal Export --}}
<div id="exportModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--bg-primary); border-radius:14px; padding:28px 32px; min-width:340px; box-shadow:0 8px 40px rgba(0,0,0,.3); position:relative;">
        <h4 style="margin:0 0 18px; font-size:16px; font-weight:700;"><i class="fa-solid fa-download" style="color:#3b82f6;"></i> Pilih Data yang Ingin Diunduh</h4>
        <input type="hidden" id="exportFormat" value="">
        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                <input type="checkbox" id="chkStok" checked style="width:16px;height:16px;"> <span>Laporan Stok Barang</span>
            </label>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                <input type="checkbox" id="chkMasuk" checked style="width:16px;height:16px;"> <span>Barang Masuk</span>
            </label>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                <input type="checkbox" id="chkKeluar" checked style="width:16px;height:16px;"> <span>Barang Keluar</span>
            </label>
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:14px;">
                <input type="checkbox" id="chkTeknisi" checked style="width:16px;height:16px;"> <span>Stok Teknisi</span>
            </label>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="btn btn-outline" onclick="document.getElementById('exportModal').style.display='none'">Batal</button>
            <button class="btn btn-primary" onclick="executeDownload()"><i class="fa-solid fa-download"></i> Download</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    // Date presets
    document.querySelectorAll('.btn-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const days = parseInt(btn.dataset.days, 10);
            const to = new Date();
            const from = new Date();
            from.setDate(to.getDate() - days);
            document.querySelector('input[name="from"]').value = from.toISOString().slice(0, 10);
            document.querySelector('input[name="to"]').value = to.toISOString().slice(0, 10);
            document.getElementById('filterForm').submit();
        });
    });

    $(document).ready(function() {
        // Tab switching
        $('.report-tab-btn').on('click', function() {
            $('.report-tab-btn').removeClass('active-tab').css({
                'border-bottom-color': 'transparent',
                'color': 'var(--text-secondary)'
            });
            $('.report-panel').hide();
            
            $(this).addClass('active-tab').css({
                'border-bottom-color': 'var(--accent-blue)',
                'color': 'var(--text-primary)'
            });
            
            const targetTab = $(this).data('tab');
            $('#panel-' + targetTab).show();
            
            // Adjust DataTables scroll headers when tab becomes visible
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });

        // Initialize DataTable configs
        const dtConfigs = {
            pageLength: 20,
            dom: '<"dt-header"lB>rtip',
            buttons: [
                {
                    extend: 'colvis',
                    text: '<i class="fa-solid fa-eye-slash"></i> Hide/Unhide Kolom',
                    className: 'buttons-colvis'
                }
            ],
            language: {
                search: "",
                searchPlaceholder: "Cari data...",
                lengthMenu: "Tampilkan _MENU_ baris",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                paginate: {
                    first: "Awal",
                    last: "Akhir",
                    next: "Lanjut",
                    previous: "Kembali"
                }
            },
            ordering: true,
            scrollX: true
        };

        // Per-column filter helper: bind inputs in thead filter row to DataTable column().search()
        function bindColFilters(tableId) {
            const dt = $(tableId).DataTable(dtConfigs);
            // The filter row is the 2nd <tr> in thead; its inputs map to their th index
            $(tableId + ' thead tr:eq(1) input.col-filter').each(function(i) {
                $(this).on('keyup change', function() {
                    // We need to map filter-row column index to actual table column
                    // Walk through all th in filter row to find which have inputs
                    let actualColIdx = 0;
                    $(tableId + ' thead tr:eq(1) th').each(function(thIdx) {
                        if (thIdx === i) return false; // found
                        actualColIdx++;
                    });
                    if (dt.column(actualColIdx).search !== undefined) {
                        dt.column(actualColIdx).search($(this).val()).draw();
                    }
                });
            });
            return dt;
        }

        $('#tableStokBarang').DataTable(dtConfigs);

        // Tables with column filters
        bindColFilters('#tableBarangMasuk');
        bindColFilters('#tableBarangKeluar');

        if ($('#tableStokTeknisi').length) {
            $('#tableStokTeknisi').DataTable(dtConfigs);
        }
    });

    // Close export dropdown on click outside
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('exportDropdownWrap');
        const menu = document.getElementById('exportMenu');
        if (wrap && menu && !wrap.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    // Toggle Barang Masuk: Summary vs Per SN
    function switchMasukView(mode) {
        const isSummary = mode === 'summary';
        document.getElementById('viewMasukSummary').style.display = isSummary ? 'block' : 'none';
        document.getElementById('viewMasukPerSN').style.display  = isSummary ? 'none'  : 'block';
        document.getElementById('btnMasukSummary').className = isSummary ? 'btn btn-primary' : 'btn btn-outline';
        document.getElementById('btnMasukPerSN').className   = isSummary ? 'btn btn-outline' : 'btn btn-primary';
        document.getElementById('btnMasukSummary').style.fontSize = '12px';
        document.getElementById('btnMasukSummary').style.padding  = '4px 12px';
        document.getElementById('btnMasukPerSN').style.fontSize   = '12px';
        document.getElementById('btnMasukPerSN').style.padding    = '4px 12px';
    }

    // Toggle Barang Keluar: Summary vs Per SN
    function switchKeluarView(mode) {
        const isSummary = mode === 'summary';
        document.getElementById('viewKeluarSummary').style.display = isSummary ? 'block' : 'none';
        document.getElementById('viewKeluarPerSN').style.display  = isSummary ? 'none'  : 'block';
        document.getElementById('btnKeluarSummary').className = isSummary ? 'btn btn-primary' : 'btn btn-outline';
        document.getElementById('btnKeluarPerSN').className   = isSummary ? 'btn btn-outline' : 'btn btn-primary';
        document.getElementById('btnKeluarSummary').style.fontSize = '12px';
        document.getElementById('btnKeluarSummary').style.padding  = '4px 12px';
        document.getElementById('btnKeluarPerSN').style.fontSize   = '12px';
        document.getElementById('btnKeluarPerSN').style.padding    = '4px 12px';
    }

    function openExportModal(format) {
        event.preventDefault();
        document.getElementById('exportMenu').classList.remove('show');
        document.getElementById('exportFormat').value = format;
        document.getElementById('exportModal').style.display = 'flex';
    }

    function executeDownload() {
        const format = document.getElementById('exportFormat').value;
        const stok   = document.getElementById('chkStok').checked;
        const masuk  = document.getElementById('chkMasuk').checked;
        const keluar = document.getElementById('chkKeluar').checked;
        const teknisi = document.getElementById('chkTeknisi').checked;
        
        if (!stok && !masuk && !keluar && !teknisi) {
            alert('Pilih setidaknya satu data untuk diunduh!');
            return;
        }

        if (format === 'csv') {
            const base = '{{ route("reports.export", array_merge(["type" => "all"], $exportParams, ["format" => "csv"])) }}';
            let delay = 0;
            if (stok) { setTimeout(function(){ window.open(base + '&scope=stok', '_blank'); }, delay); delay += 300; }
            if (masuk) { setTimeout(function(){ window.open(base + '&scope=masuk', '_blank'); }, delay); delay += 300; }
            if (keluar) { setTimeout(function(){ window.open(base + '&scope=keluar', '_blank'); }, delay); delay += 300; }
            if (teknisi) { setTimeout(function(){ window.open(base + '&scope=teknisi', '_blank'); }, delay); delay += 300; }
        } else if (format === 'excel') {
            let url = '{{ route("reports.export.custom_excel", $exportParams) }}';
            url += '&stok=' + (stok ? 1 : 0) + '&masuk=' + (masuk ? 1 : 0) + '&keluar=' + (keluar ? 1 : 0) + '&teknisi=' + (teknisi ? 1 : 0);
            window.open(url, '_blank');
        }
        document.getElementById('exportModal').style.display = 'none';
    }

    document.getElementById('exportModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
</script>
@endsection
