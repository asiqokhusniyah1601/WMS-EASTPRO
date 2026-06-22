@extends('layouts.app')

@section('title', 'Laporan & Analitik | DLMS')

@php
    $period = $filters['period'];
    $whCode = $filters['warehouse'] ?? 'all';
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

    .cal-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
    .cal-month-title { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; text-transform: capitalize; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
    .cal-dow { text-align: center; font-size: 11px; font-weight: 600; color: var(--text-muted); padding: 4px 0; }
    .cal-cell {
        position: relative; min-height: 58px; border: 1px solid var(--border-color); border-radius: 8px;
        padding: 4px 6px; background: var(--bg-primary); display: flex; flex-direction: column; gap: 2px;
    }
    .cal-cell.empty { background: transparent; border: none; }
    .cal-cell.out-range { opacity: 0.35; }
    .cal-daynum { font-size: 11px; font-weight: 600; color: var(--text-secondary); }
    .cal-vals { display: flex; gap: 6px; font-size: 12px; font-weight: 700; line-height: 1; }
    .cal-vals .v-in { color: var(--accent-emerald); }
    .cal-vals .v-out { color: var(--accent-amber); }
    .cal-net { font-size: 10px; font-weight: 600; color: var(--text-muted); }
    .cal-cell.net-pos { border-color: rgba(16,185,129,0.5); background: rgba(16,185,129,0.08); }
    .cal-cell.net-neg { border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.08); }
    .cal-cell.net-zero { border-color: var(--border-color); }
    .cal-cell.today { box-shadow: 0 0 0 2px var(--accent-blue); }
    .cal-cell.today .cal-daynum { color: var(--accent-blue); }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-chart-line"
        title="Laporan & Analitik"
        subtitle="Mutasi barang, stok teknisi, aging, kualitas, dan audit koreksi — dengan filter periode & gudang.">
        <a href="{{ route('reports.print', $exportParams) }}" target="_blank" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </a>
    </x-page-header>

    <!-- Filter Bar -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="{{ route('reports') }}" id="filterForm"
            style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <div class="form-group" style="margin: 0;">
                <label>Dari Tanggal</label>
                <input type="date" name="from" value="{{ $fromDate }}" class="form-control">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Sampai Tanggal</label>
                <input type="date" name="to" value="{{ $toDate }}" class="form-control">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Periode Grafik</label>
                <select name="period" class="form-control">
                    <option value="day" {{ $period === 'day' ? 'selected' : '' }}>Harian</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Mingguan</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Bulanan</option>
                </select>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Gudang</label>
                <select name="warehouse" class="form-control">
                    <option value="all" {{ $whCode === 'all' ? 'selected' : '' }}>Semua Gudang</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->code }}" {{ $whCode === $wh->code ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Terapkan</button>
            <div style="display: flex; gap: 6px;">
                <button type="button" class="btn btn-outline btn-preset" data-days="6" style="padding: 8px 12px;">7 Hari</button>
                <button type="button" class="btn btn-outline btn-preset" data-days="29" style="padding: 8px 12px;">30 Hari</button>
                <button type="button" class="btn btn-outline btn-preset" data-days="89" style="padding: 8px 12px;">90 Hari</button>
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

    <!-- Tabs -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin: 8px 0 24px; flex-wrap: wrap;">
        @php
            $tabs = [
                'stockcard' => ['Kartu Stok', 'fa-table-list'],
                'inout' => ['Mutasi In/Out', 'fa-right-left'],
                'tech' => ['Stok Teknisi', 'fa-user-gear'],
                'customer' => ['Stok Customer', 'fa-user-tag'],
                'aging' => ['Aging / Dead Stock', 'fa-hourglass-half'],
                'quality' => ['Kualitas (QC)', 'fa-magnifying-glass-chart'],
                'adjustment' => ['Audit Koreksi', 'fa-pen-to-square'],
                'exec' => ['Ringkasan', 'fa-chart-pie'],
            ];
        @endphp
        @foreach($tabs as $key => $tab)
            <button class="btn btn-outline report-tab-btn {{ $loop->first ? 'active-tab' : '' }}" data-tab="{{ $key }}"
                style="border: none; border-bottom: 2px solid {{ $loop->first ? 'var(--accent-blue)' : 'transparent' }}; border-radius: 0; padding-bottom: 12px; background: none; color: {{ $loop->first ? 'var(--text-primary)' : 'var(--text-secondary)' }};">
                <i class="fa-solid {{ $tab[1] }}"></i> {{ $tab[0] }}
            </button>
        @endforeach
    </div>

    <!-- ============ TAB: KARTU STOK ============ -->
    <div class="report-panel" id="panel-stockcard">
        @php
            $stockCats = [
                'device'    => ['Device / Perangkat', 'fa-microchip', $stockcard['device']],
                'accessory' => ['Aksesoris', 'fa-plug-circle-bolt', $stockcard['accessory']],
                'gsm'       => ['Kartu GSM', 'fa-sim-card', $stockcard['gsm']],
            ];
        @endphp
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div class="card-title"><i class="fa-solid fa-table-list"></i> Kartu Stok &mdash; Stok Awal, Masuk, Keluar, Sisa</div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('reports.export', array_merge(['type' => 'stockcard'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'stockcard', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>

            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                Saldo periode <strong>{{ $fromDate }}</strong> s/d <strong>{{ $toDate }}</strong> &middot; Gudang: <strong>{{ $whCode === 'all' ? 'Semua Gudang' : $whCode }}</strong>.
                <em>Stok Awal</em> = akumulasi mutasi sebelum tanggal awal; klik <i class="fa-solid fa-list"></i> untuk melihat buku besar (saldo berjalan).
            </p>

            <!-- Sub-kategori -->
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                @foreach($stockCats as $cat => $info)
                    <button type="button" class="btn btn-outline sc-cat-btn {{ $loop->first ? 'active' : '' }}" data-cat="{{ $cat }}"
                        style="font-size:13px; {{ $loop->first ? 'border-color: var(--accent-blue); color: var(--accent-blue);' : '' }}">
                        <i class="fa-solid {{ $info[1] }}"></i> {{ $info[0] }}
                        <span class="badge badge-info" style="margin-left:4px;">{{ count($info[2]['rows']) }}</span>
                    </button>
                @endforeach
            </div>

            @foreach($stockCats as $cat => $info)
                @php $card = $info[2]; @endphp
                <div class="sc-cat-panel" id="sc-panel-{{ $cat }}" style="{{ $loop->first ? '' : 'display:none;' }}">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:36px;"></th>
                                    <th>Nama Barang</th>
                                    <th style="text-align:right;">Stok Awal</th>
                                    <th style="text-align:right; color:var(--accent-emerald);">Masuk</th>
                                    <th style="text-align:right; color:var(--accent-amber);">Keluar</th>
                                    <th>Tgl Masuk (pertama)</th>
                                    <th>Tgl Keluar (terakhir)</th>
                                    <th style="text-align:right;">Sisa Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($card['rows'] as $i => $row)
                                    @php $ledgerId = "ledger-{$cat}-{$i}"; @endphp
                                    <tr>
                                        <td>
                                            @if(!empty($row['ledger']))
                                                <button type="button" class="btn btn-outline sc-ledger-toggle" data-target="{{ $ledgerId }}" style="padding:2px 8px;" title="Lihat buku besar">
                                                    <i class="fa-solid fa-list"></i>
                                                </button>
                                            @endif
                                        </td>
                                        <td style="font-weight:600;">{{ $row['name'] }}</td>
                                        <td style="text-align:right;">{{ $row['opening'] }}</td>
                                        <td style="text-align:right; color:var(--accent-emerald); font-weight:600;">{{ $row['in'] > 0 ? '+' . $row['in'] : 0 }}</td>
                                        <td style="text-align:right; color:var(--accent-amber); font-weight:600;">{{ $row['out'] > 0 ? '-' . $row['out'] : 0 }}</td>
                                        <td style="color:var(--text-secondary);">{{ $row['first_in'] ?? '—' }}</td>
                                        <td style="color:var(--text-secondary);">{{ $row['last_out'] ?? '—' }}</td>
                                        <td style="text-align:right; font-weight:700; color:{{ $row['closing'] < 0 ? 'var(--danger-color)' : 'var(--text-primary)' }};">{{ $row['closing'] }}</td>
                                    </tr>
                                    @if(!empty($row['ledger']))
                                        <tr id="{{ $ledgerId }}" style="display:none;">
                                            <td></td>
                                            <td colspan="7" style="background: var(--bg-primary); padding: 12px;">
                                                <strong style="font-size:12px; color:var(--text-secondary);"><i class="fa-solid fa-book"></i> Buku Besar — {{ $row['name'] }}</strong>
                                                <table class="table" style="margin-top:8px;">
                                                    <thead>
                                                        <tr>
                                                            <th>Tanggal</th>
                                                            <th>Referensi</th>
                                                            <th style="text-align:right;">Masuk</th>
                                                            <th style="text-align:right;">Keluar</th>
                                                            <th style="text-align:right;">Saldo</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="4" style="color:var(--text-muted);">Saldo awal periode</td>
                                                            <td style="text-align:right; font-weight:600;">{{ $row['opening'] }}</td>
                                                        </tr>
                                                        @foreach($row['ledger'] as $l)
                                                            <tr>
                                                                <td style="white-space:nowrap;">{{ $l['date'] }}</td>
                                                                <td style="font-size:12px;">{{ $l['ref'] }}</td>
                                                                <td style="text-align:right; color:var(--accent-emerald);">{{ $l['in'] > 0 ? '+' . $l['in'] : '' }}</td>
                                                                <td style="text-align:right; color:var(--accent-amber);">{{ $l['out'] > 0 ? '-' . $l['out'] : '' }}</td>
                                                                <td style="text-align:right; font-weight:600;">{{ $l['balance'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:24px;">Tidak ada mutasi untuk kategori ini pada periode terpilih.</td></tr>
                                @endforelse
                            </tbody>
                            @if(count($card['rows']) > 0)
                                <tfoot>
                                    <tr style="font-weight:700; border-top:2px solid var(--border-color);">
                                        <td></td>
                                        <td>TOTAL</td>
                                        <td style="text-align:right;">{{ $card['totals']['opening'] }}</td>
                                        <td style="text-align:right; color:var(--accent-emerald);">+{{ $card['totals']['in'] }}</td>
                                        <td style="text-align:right; color:var(--accent-amber);">-{{ $card['totals']['out'] }}</td>
                                        <td></td>
                                        <td></td>
                                        <td style="text-align:right;">{{ $card['totals']['closing'] }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ============ TAB: MUTASI IN/OUT ============ -->
    <div class="report-panel" id="panel-inout" style="display:none;">
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-right-left"></i> Mutasi Barang Masuk vs Keluar</div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('reports.export', array_merge(['type' => 'inout'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'inout', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
            <div style="height: 320px;"><canvas id="inoutChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header" style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 10px;">
                <div class="card-title"><i class="fa-solid fa-calendar-days"></i> Rekap Mutasi (Kalender Harian)</div>
                <div class="cal-legend">
                    <span><i class="dot in"></i> Masuk</span>
                    <span><i class="dot out"></i> Keluar</span>
                    <span><i class="dot pos"></i> Net positif</span>
                    <span><i class="dot neg"></i> Net negatif</span>
                </div>
            </div>

            @php
                $calFrom = $filters['from']->copy()->startOfDay();
                $calTo   = $filters['to']->copy()->startOfDay();
                $monthCursor = $calFrom->copy()->startOfMonth();
                $lastMonth = $calTo->copy()->startOfMonth();
                $todayKey = \Illuminate\Support\Carbon::now()->format('Y-m-d');
                $dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
            @endphp

            <div class="cal-wrapper">
                @while($monthCursor->lte($lastMonth))
                    @php
                        $daysInMonth = $monthCursor->daysInMonth;
                        $firstDow = (int) $monthCursor->copy()->startOfMonth()->dayOfWeekIso; // 1=Sen..7=Min
                        $leadBlanks = $firstDow - 1;
                    @endphp
                    <div class="cal-month">
                        <div class="cal-month-title">{{ $monthCursor->copy()->locale('id')->translatedFormat('F Y') }}</div>
                        <div class="cal-grid">
                            @foreach($dayHeaders as $dh)
                                <div class="cal-dow">{{ $dh }}</div>
                            @endforeach

                            @for($b = 0; $b < $leadBlanks; $b++)
                                <div class="cal-cell empty"></div>
                            @endfor

                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $cellDate = $monthCursor->copy()->day($d);
                                    $key = $cellDate->format('Y-m-d');
                                    $inRange = $cellDate->betweenIncluded($calFrom, $calTo);
                                    $data = $movementDaily[$key] ?? null;
                                    $in = $data['in'] ?? 0;
                                    $out = $data['out'] ?? 0;
                                    $net = $in - $out;
                                    $netClass = $data ? ($net > 0 ? 'net-pos' : ($net < 0 ? 'net-neg' : 'net-zero')) : '';
                                @endphp
                                <div class="cal-cell {{ $inRange ? '' : 'out-range' }} {{ $netClass }} {{ $key === $todayKey ? 'today' : '' }}">
                                    <div class="cal-daynum">{{ $d }}</div>
                                    @if($data && $inRange)
                                        <div class="cal-vals">
                                            <span class="v-in" title="Masuk">↓{{ $in }}</span>
                                            <span class="v-out" title="Keluar">↑{{ $out }}</span>
                                        </div>
                                        <div class="cal-net">{{ $net > 0 ? '+' : '' }}{{ $net }}</div>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>
                    @php $monthCursor->addMonth(); @endphp
                @endwhile
            </div>

            @if(empty($movementDaily))
                <div style="text-align:center; color: var(--text-muted); padding: 24px;">Tidak ada transaksi pada periode ini.</div>
            @endif
        </div>
    </div>

    <!-- ============ TAB: STOK TEKNISI ============ -->
    <div class="report-panel" id="panel-tech" style="display:none;">
        <div class="card">
            @php
                $techAreas = collect($technicianStock['devices'])->pluck('area')->filter(fn($a) => $a && $a !== '-')->unique()->sort()->values();
                $preArea = request('area');
            @endphp
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div class="card-title"><i class="fa-solid fa-user-gear"></i> Aset Aktif per Teknisi</div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-location-dot" style="color: var(--text-muted); font-size: 12px;"></i>
                        <select id="techAreaFilter" class="form-control" style="width: auto; min-width: 150px; padding: 6px 10px; font-size: 13px;">
                            <option value="">Semua Area</option>
                            @foreach($techAreas as $areaOpt)
                                <option value="{{ $areaOpt }}" {{ $preArea === $areaOpt ? 'selected' : '' }}>{{ $areaOpt }}</option>
                            @endforeach
                            <option value="-" {{ $preArea === 'Tanpa Area' || $preArea === '-' ? 'selected' : '' }}>Tanpa Area</option>
                        </select>
                    </div>
                    <a href="{{ route('reports.export', array_merge(['type' => 'technicians'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'technicians', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table" id="techStockTable">
                    <thead><tr><th>Kode</th><th>Pemegang</th><th>Area</th><th style="text-align:center;">GPS</th><th style="text-align:center;">MDVR</th><th style="text-align:center;">Dashcam</th><th style="text-align:center;">Lainnya</th><th style="text-align:center;">Total</th></tr></thead>
                    <tbody>
                        @forelse($technicianStock['devices'] as $t)
                            <tr data-area="{{ $t['area'] }}">
                                <td style="font-family: monospace;">{{ $t['code'] }}</td>
                                <td style="font-weight:600;">{{ $t['name'] }}</td>
                                <td>
                                    @if(!empty($t['area']) && $t['area'] !== '-')
                                        <span class="badge badge-info"><i class="fa-solid fa-location-dot"></i> {{ $t['area'] }}</span>
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                                <td style="text-align:center;"><span class="badge badge-info">{{ $t['gps'] }}</span></td>
                                <td style="text-align:center;"><span class="badge badge-warning">{{ $t['mdvr'] }}</span></td>
                                <td style="text-align:center;"><span class="badge badge-success">{{ $t['dashcam'] }}</span></td>
                                <td style="text-align:center;">{{ $t['other'] }}</td>
                                <td style="text-align:center; font-weight:700; color: var(--accent-blue);">{{ $t['total'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="8" icon="fa-user-gear"
                                title="Belum ada perangkat di tangan teknisi"
                                message="Tidak ada perangkat yang sedang dipegang teknisi untuk filter saat ini." />
                        @endforelse
                        <tr id="techAreaEmpty" style="display:none;"><td colspan="8" style="text-align:center; color: var(--text-muted); padding: 24px;">Tidak ada teknisi pada area ini.</td></tr>
                    </tbody>
                </table>
            </div>
            <script>
                (function () {
                    const sel = document.getElementById('techAreaFilter');
                    const table = document.getElementById('techStockTable');
                    if (!sel || !table) return;
                    const emptyRow = document.getElementById('techAreaEmpty');
                    function applyFilter() {
                        const val = sel.value;
                        let shown = 0;
                        table.querySelectorAll('tbody tr[data-area]').forEach(tr => {
                            const area = tr.getAttribute('data-area') || '-';
                            const match = !val || area === val || (val === '-' && (area === '-' || area === ''));
                            tr.style.display = match ? '' : 'none';
                            if (match) shown++;
                        });
                        if (emptyRow) emptyRow.style.display = shown === 0 ? '' : 'none';
                    }
                    sel.addEventListener('change', applyFilter);
                    applyFilter();
                })();
            </script>
            <small style="color: var(--text-muted); margin-top: 12px; display:block;">*Berdasarkan pemegang aktual perangkat berstatus ISSUED / INSTALLED.</small>
        </div>

        @if(!empty($technicianStock['accessories']))
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-toolbox"></i> Aksesoris di Tangan Teknisi (net)</div></div>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Kode Teknisi</th><th>Teknisi</th><th>Aksesoris</th><th style="text-align:center;">Qty Belum Kembali</th></tr></thead>
                    <tbody>
                        @foreach($technicianStock['accessories'] as $a)
                            <tr>
                                <td style="font-family: monospace;">{{ $a['technician_code'] }}</td>
                                <td>{{ $a['technician_name'] ?? $a['technician_code'] }}</td>
                                <td>{{ $a['accessory_name'] ?? $a['accessory_code'] }} <span style="color: var(--text-muted); font-size: 11px;">({{ $a['accessory_code'] }})</span></td>
                                <td style="text-align:center; font-weight:600;">{{ $a['qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <small style="color: var(--text-muted); margin-top: 12px; display:block;">*Net = total dikeluarkan dikurangi yang sudah dikembalikan (berdasarkan log transaksi).</small>
        </div>
        @endif
    </div>

    <!-- ============ TAB: STOK CUSTOMER ============ -->
    <div class="report-panel" id="panel-customer" style="display:none;">
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-user-tag"></i> Aset Aktif per Customer</div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('reports.export', array_merge(['type' => 'customers'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'customers', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Customer</th><th style="text-align:center;">GPS</th><th style="text-align:center;">MDVR</th><th style="text-align:center;">Dashcam</th><th style="text-align:center;">Lainnya</th><th style="text-align:center;">Total</th></tr></thead>
                    <tbody>
                        @forelse($customerStock['devices'] as $c)
                            <tr>
                                <td style="font-weight:600;">{{ $c['name'] }}</td>
                                <td style="text-align:center;"><span class="badge badge-info">{{ $c['gps'] }}</span></td>
                                <td style="text-align:center;"><span class="badge badge-warning">{{ $c['mdvr'] }}</span></td>
                                <td style="text-align:center;"><span class="badge badge-success">{{ $c['dashcam'] }}</span></td>
                                <td style="text-align:center;">{{ $c['other'] }}</td>
                                <td style="text-align:center; font-weight:700; color: var(--accent-blue);">{{ $c['total'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="fa-user-tag"
                                title="Belum ada perangkat di customer"
                                message="Tidak ada perangkat berstatus terpasang/diserahkan ke customer saat ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <small style="color: var(--text-muted); margin-top: 12px; display:block;">*Berdasarkan pemegang aktual perangkat (current_holder = "Customer: ...").</small>
        </div>

        @if(!empty($customerStock['accessories']))
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-toolbox"></i> Aksesoris di Customer (net)</div></div>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Customer</th><th>Aksesoris</th><th style="text-align:center;">Qty Belum Kembali</th></tr></thead>
                    <tbody>
                        @foreach($customerStock['accessories'] as $a)
                            <tr>
                                <td style="font-weight:600;">{{ $a['customer'] }}</td>
                                <td>{{ $a['accessory_name'] ?? $a['accessory_code'] }} <span style="color: var(--text-muted); font-size: 11px;">({{ $a['accessory_code'] }})</span></td>
                                <td style="text-align:center; font-weight:600;">{{ $a['qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <small style="color: var(--text-muted); margin-top: 12px; display:block;">*Disimpulkan dari log transaksi keluar/kembali ke customer.</small>
        </div>
        @else
        <div class="card">
            <x-empty-state icon="fa-toolbox"
                title="Belum ada aksesoris di customer"
                message="Aksesoris yang diserahkan ke customer akan muncul di sini sebagai saldo net." />
        </div>
        @endif
    </div>

    <!-- ============ TAB: AGING ============ -->
    <div class="report-panel" id="panel-aging" style="display:none;">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-layer-group"></i> Distribusi Umur Stok</div></div>
                <div style="height: 260px;"><canvas id="agingChart"></canvas></div>
            </div>
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="card-title"><i class="fa-solid fa-hourglass-half"></i> Dead Stock (di gudang > 30 hari)</div>
                    <div style="display: flex; gap: 8px;">
                        <a href="{{ route('reports.export', array_merge(['type' => 'aging'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                        <a href="{{ route('reports.export', array_merge(['type' => 'aging', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                    </div>
                </div>
                <div class="table-wrapper" style="max-height: 360px; overflow-y: auto;">
                    <table class="table">
                        <thead><tr><th>Serial</th><th>Tipe</th><th>Gudang</th><th style="text-align:center;">Umur</th><th>Terakhir</th></tr></thead>
                        <tbody>
                            @forelse($aging['dead_stock'] as $d)
                                <tr>
                                    <td style="font-family: monospace; font-size: 12px;">{{ $d['serial_number'] }}</td>
                                    <td>{{ $d['type'] }}</td>
                                    <td>{{ $d['warehouse'] }}</td>
                                    <td style="text-align:center;"><span class="badge {{ $d['age_days'] > 90 ? 'badge-danger' : 'badge-warning' }}">{{ $d['age_days'] }} hari</span></td>
                                    <td style="font-size: 12px;">{{ $d['last_movement'] }}</td>
                                </tr>
                            @empty
                                <x-empty-state colspan="5" icon="fa-box-open"
                                    title="Tidak ada dead stock"
                                    message="Bagus! Tidak ada perangkat yang mengendap melebihi ambang batas pada periode ini." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-user-clock"></i> Perangkat Lama di Tangan Teknisi (> 14 hari)</div></div>
            <div class="table-wrapper" style="max-height: 360px; overflow-y: auto;">
                <table class="table">
                    <thead><tr><th>Serial</th><th>Tipe</th><th>Pemegang</th><th style="text-align:center;">Umur</th><th>Sejak</th></tr></thead>
                    <tbody>
                        @forelse($aging['tech_aging'] as $d)
                            <tr>
                                <td style="font-family: monospace; font-size: 12px;">{{ $d['serial_number'] }}</td>
                                <td>{{ $d['type'] }}</td>
                                <td style="font-weight:600;">{{ $d['holder'] }}</td>
                                <td style="text-align:center;"><span class="badge {{ $d['age_days'] > 30 ? 'badge-danger' : 'badge-warning' }}">{{ $d['age_days'] }} hari</span></td>
                                <td style="font-size: 12px;">{{ $d['since'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="5" icon="fa-circle-check"
                                title="Tidak ada unit menggantung"
                                message="Tidak ada unit yang tertahan terlalu lama di teknisi pada periode ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ TAB: KUALITAS ============ -->
    <div class="report-panel" id="panel-quality" style="display:none;">
        <div class="stats-grid">
            <div class="stat-card emerald"><div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div><div class="stat-details"><h3>Total Inspeksi (periode)</h3><div class="stat-value">{{ $quality['total_inspections'] }}</div></div></div>
            <div class="stat-card amber"><div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div><div class="stat-details"><h3>QC Gagal</h3><div class="stat-value">{{ $quality['qc_failed'] }}</div></div></div>
            <div class="stat-card blue"><div class="stat-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div><div class="stat-details"><h3>Sedang Repair</h3><div class="stat-value">{{ $quality['current_repair'] }}</div></div></div>
            <div class="stat-card indigo"><div class="stat-icon"><i class="fa-solid fa-trash-can"></i></div><div class="stat-details"><h3>Scrap / Disposed</h3><div class="stat-value">{{ $quality['current_scrap'] }}</div></div></div>
        </div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-magnifying-glass-chart"></i> Riwayat Inspeksi QC</div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('reports.export', array_merge(['type' => 'quality'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'quality', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
            <div class="table-wrapper" style="max-height: 420px; overflow-y: auto;">
                <table class="table">
                    <thead><tr><th>Device ID</th><th>Kondisi</th><th>Hasil</th><th>Operator</th><th>Catatan</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        @forelse($quality['recent'] as $i)
                            <tr>
                                <td>{{ $i['device_id'] }}</td>
                                <td>{{ $i['condition'] }}</td>
                                <td><span class="badge {{ str_contains(strtoupper($i['qc_result'] ?? ''), 'PASS') ? 'badge-success' : 'badge-danger' }}">{{ $i['qc_result'] }}</span></td>
                                <td>{{ $i['operator'] }}</td>
                                <td style="font-size: 12px; color: var(--text-secondary);">{{ $i['notes'] }}</td>
                                <td style="font-size: 12px;">{{ $i['created_at'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="fa-clipboard-check"
                                title="Belum ada inspeksi"
                                message="Tidak ada data inspeksi/QC pada periode yang dipilih." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ TAB: AUDIT KOREKSI ============ -->
    <div class="report-panel" id="panel-adjustment" style="display:none;">
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-pen-to-square"></i> Koreksi Unit Perangkat (ADJUSTMENT)</div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ route('reports.export', array_merge(['type' => 'adjustment'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="{{ route('reports.export', array_merge(['type' => 'adjustment', 'format' => 'excel'], $exportParams)) }}" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
            <div class="table-wrapper" style="max-height: 360px; overflow-y: auto;">
                <table class="table">
                    <thead><tr><th>Serial</th><th>Dari</th><th>Ke</th><th>Operator</th><th>Alasan</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        @forelse($adjustment['device_adjustments'] as $a)
                            <tr>
                                <td style="font-family: monospace; font-size: 12px;">{{ $a['device_sn'] }}</td>
                                <td style="font-size: 12px;">{{ $a['from'] }}</td>
                                <td style="font-size: 12px;">{{ $a['to'] }}</td>
                                <td>{{ $a['operator'] }}</td>
                                <td style="font-size: 12px; color: var(--text-secondary);">{{ $a['notes'] }}</td>
                                <td style="font-size: 12px;">{{ $a['created_at'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="fa-microchip"
                                title="Tidak ada koreksi perangkat"
                                message="Tidak ada koreksi/adjustment perangkat pada periode ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-toolbox"></i> Koreksi Stok Aksesoris (Stock Opname)</div></div>
            <div class="table-wrapper" style="max-height: 360px; overflow-y: auto;">
                <table class="table">
                    <thead><tr><th>Aksesoris</th><th style="text-align:center;">Qty</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        @forelse($adjustment['accessory_adjustments'] as $a)
                            <tr>
                                <td>{{ $a['accessory_code'] }}</td>
                                <td style="text-align:center; font-weight:600;">{{ $a['qty'] }}</td>
                                <td style="font-size: 12px;">{{ $a['from'] }}</td>
                                <td style="font-size: 12px;">{{ $a['to'] }}</td>
                                <td style="font-size: 12px; color: var(--text-secondary);">{{ $a['notes'] }}</td>
                                <td style="font-size: 12px;">{{ $a['created_at'] }}</td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="fa-plug"
                                title="Tidak ada koreksi aksesoris"
                                message="Tidak ada koreksi/adjustment aksesoris pada periode ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ TAB: RINGKASAN EKSEKUTIF ============ -->
    <div class="report-panel" id="panel-exec" style="display:none;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-pie"></i> Snapshot Status Perangkat</div></div>
                <div style="height: 280px;"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fa-solid fa-list-check"></i> Aktivitas per Jenis Transaksi</div></div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @forelse($executive['action_counts'] as $action => $count)
                        <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-primary); padding: 10px 14px; border-radius: 6px; border: 1px solid var(--border-color);">
                            <span style="font-size: 13px; font-weight: 600;">{{ $action }}</span>
                            <span class="badge badge-info">{{ $count }}x</span>
                        </div>
                    @empty
                        <div style="text-align:center; color: var(--text-muted); padding: 20px;">Tidak ada transaksi pada periode ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Tab switching
    document.querySelectorAll('.report-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.report-tab-btn').forEach(b => {
                b.style.borderBottomColor = 'transparent';
                b.style.color = 'var(--text-secondary)';
            });
            document.querySelectorAll('.report-panel').forEach(p => p.style.display = 'none');
            btn.style.borderBottomColor = 'var(--accent-blue)';
            btn.style.color = 'var(--text-primary)';
            document.getElementById('panel-' + btn.dataset.tab).style.display = 'block';
        });
    });

    // Aktifkan tab dari query (?tab=) — dipakai untuk deep-link dari Dashboard.
    (function () {
        const wantTab = new URLSearchParams(location.search).get('tab');
        if (!wantTab) return;
        const btn = document.querySelector('.report-tab-btn[data-tab="' + wantTab + '"]');
        if (btn) btn.click();
    })();

    // Kartu Stok: sub-kategori (Device / Aksesoris / GSM)
    document.querySelectorAll('.sc-cat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sc-cat-btn').forEach(b => {
                b.classList.remove('active');
                b.style.borderColor = '';
                b.style.color = '';
            });
            document.querySelectorAll('.sc-cat-panel').forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            btn.style.borderColor = 'var(--accent-blue)';
            btn.style.color = 'var(--accent-blue)';
            document.getElementById('sc-panel-' + btn.dataset.cat).style.display = 'block';
        });
    });

    // Kartu Stok: toggle buku besar (ledger)
    document.querySelectorAll('.sc-ledger-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = document.getElementById(btn.dataset.target);
            if (!row) return;
            const open = row.style.display !== 'none';
            row.style.display = open ? 'none' : 'table-row';
            btn.innerHTML = open ? '<i class="fa-solid fa-list"></i>' : '<i class="fa-solid fa-chevron-up"></i>';
        });
    });

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

    const gridColor = 'rgba(148,163,184,0.12)';
    const tickColor = '#94a3b8';
    const baseOpts = {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color: tickColor } } },
        scales: {
            x: { ticks: { color: tickColor }, grid: { color: gridColor } },
            y: { ticks: { color: tickColor }, grid: { color: gridColor }, beginAtZero: true }
        }
    };

    // In/Out chart
    new Chart(document.getElementById('inoutChart'), {
        type: 'bar',
        data: {
            labels: @json($movement['labels']),
            datasets: [
                { label: 'Masuk', data: @json($movement['in']), backgroundColor: 'rgba(16,185,129,0.7)' },
                { label: 'Keluar', data: @json($movement['out']), backgroundColor: 'rgba(245,158,11,0.7)' },
                { label: 'Net', type: 'line', data: @json($movement['net']), borderColor: '#3b82f6', backgroundColor: '#3b82f6', tension: 0.3 }
            ]
        },
        options: baseOpts
    });

    // Aging doughnut
    const agingData = @json(array_values($aging['stock_buckets']));
    new Chart(document.getElementById('agingChart'), {
        type: 'doughnut',
        data: {
            labels: @json(array_keys($aging['stock_buckets'])),
            datasets: [{ data: agingData, backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: tickColor } } } }
    });

    // Status snapshot
    const statusObj = @json($executive['status_snapshot']);
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(statusObj),
            datasets: [{ data: Object.values(statusObj), backgroundColor: ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444', '#94a3b8', '#a855f7'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: tickColor } } } }
    });
</script>
@endsection
