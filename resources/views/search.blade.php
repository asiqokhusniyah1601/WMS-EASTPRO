@extends('layouts.app')

<!--@yield('title', 'Global Search & Audit Trail | DLMS')-->

@section('styles')
<style>
    .suggestions-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-top: 4px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        max-height: 300px;
        overflow-y: auto;
        box-sizing: border-box;
    }
    .suggestion-item {
        padding: 10px 16px;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s;
    }
    .suggestion-item:last-child {
        border-bottom: none;
    }
    .suggestion-item:hover {
        background: rgba(59, 130, 246, 0.1);
    }
    .s-sn {
        font-weight: 600;
        color: var(--accent-blue);
        font-size: 14px;
    }
    .s-detail {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 2px;
    }
    .suggestion-empty {
        padding: 12px 16px;
        font-size: 13px;
        color: var(--text-muted);
        text-align: center;
    }
    /* ---- Pagination ---- */
    .pg-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 8px;
    }
    .pg-info { font-size: 13px; color: var(--text-muted); }
    .pg-nav { display: flex; gap: 4px; align-items: center; }
    .pg-btn {
        padding: 5px 10px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: var(--bg-secondary);
        color: var(--text-primary);
        cursor: pointer;
        font-size: 13px;
        transition: background 0.15s;
    }
    .pg-btn:hover:not(:disabled) { background: rgba(59,130,246,0.12); border-color: var(--accent-blue); }
    .pg-btn.active { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); font-weight: 600; }
    .pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .pg-perpage { font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
    .pg-perpage select { font-size: 13px; padding: 3px 6px; border-radius: 5px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); }
    .warehouse-toast {
        position: fixed;
        top: 90px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        background: rgba(239, 68, 68, 0.95);
        color: #fff;
        padding: 12px 28px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        z-index: 9999;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease, transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }
    .warehouse-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
</style>
@endsection

@section('content')
    <div class="animate-fade-in">
        <x-page-header
            icon="fa-magnifying-glass"
            title="Pencarian Global & Audit Trail"
            subtitle="Lacak siklus hidup (lifecycle) perangkat, posisi terakhir, serta riwayat lengkap perubahan statusnya." />

        {{-- Toast: Notifikasi perangkat tidak ada di gudang aktif --}}
        @if(!$isGlobal && $notInWarehouse)
        <div id="warehouseToast" class="warehouse-toast">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Perangkat tidak ditemukan di gudang <strong>{{ $activeWarehouseName }}</strong>
        </div>
        <script>
            (function(){
                var t = document.getElementById('warehouseToast');
                if(t){
                    setTimeout(function(){ t.classList.add('show'); }, 50);
                    setTimeout(function(){ t.classList.remove('show'); }, 2500);
                }
            })();
        </script>
        @endif

        <!-- Detect browser refresh → clear search -->
        <script>
            (function(){
                var nav = window.performance && window.performance.getEntriesByType
                    ? window.performance.getEntriesByType('navigation')[0]
                    : null;
                var isReload = nav ? nav.type === 'reload'
                    : (window.performance && window.performance.navigation
                        ? window.performance.navigation.type === 1 : false);
                if (isReload && window.location.search.indexOf('q=') !== -1) {
                    window.location.replace('{{ route('search') }}');
                }
            })();
        </script>

        <!-- Search Form Bar -->
        <div class="card" style="margin-bottom: 24px; overflow: visible; position: relative; z-index: 50;">
            <form action="{{ route('search') }}" method="GET" style="display: flex; gap: 12px; align-items: flex-start;">
                <div id="multiSearchContainer" class="form-control"
                    data-warehouse="{{ session('active_warehouse_code', '') }}"
                    style="position: relative; flex-grow: 1; display: flex; flex-wrap: wrap; gap: 8px; padding: 6px 12px 6px 48px; min-height: 46px; align-items: center; cursor: text;">
                    <i class="fa-solid fa-magnifying-glass"
                        style="position: absolute; left: 16px; top: 14px; color: var(--text-muted);"></i>
                    <input type="hidden" name="q" id="hiddenSearchInput" value="{{ $q }}">
                    <input type="text" id="searchInput" autocomplete="off"
                        placeholder="Ketik SN / IMEI lalu tekan Spasi atau Koma..."
                        style="border: none; outline: none; background: transparent; flex-grow: 1; min-width: 150px; font-size: 15px; padding: 0;">
                    <div id="searchSuggestions" class="suggestions-dropdown" style="display: none; top: calc(100% + 4px);"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 24px;">Cari Perangkat</button>
            </form>
        </div>

        @if(isset($warning) && $warning)
            <div class="alert-box alert-danger animate-fade-in" style="margin-bottom: 24px;">
                <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="alert-message">{{ $warning }}</div>
            </div>
        @endif

        @if(!empty($q) && !$warning)

            @if(count($results) > 0)
            {{-- ======= DEVICE RESULTS ======= --}}
            <div style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Device Info Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-mobile-screen-button" style="color: var(--accent-blue);"></i>
                            <span>Perangkat — Hasil Pencarian</span>
                        </div>
                        <span class="badge badge-info">{{ count($results) }} item</span>
                    </div>

                    <div class="table-wrapper">
                        <table class="table" id="tblResults">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>IMEI</th>
                                    <th>Tipe Perangkat</th>
                                    <th>Model</th>
                                    <th>Kondisi</th>
                                    <th>Status State</th>
                                    <th>Lokasi / Pemegang</th>
                                    <th>Update Terakhir</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $dev)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--accent-blue);">{{ $dev['serial_number'] }}</td>
                                        <td>{{ $dev['imei'] }}</td>
                                        <td><span class="badge badge-info">{{ $dev['type'] }}</span></td>
                                        <td>{{ $dev['model'] }}</td>
                                        <td>
                                            @if(($dev['unit_condition'] ?? 'BARU') === 'BEKAS')
                                                <span class="badge badge-warning">BEKAS</span>
                                            @else
                                                <span class="badge badge-success">BARU</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dev['status'] === 'IN_STOCK')
                                                <span class="badge badge-success">IN STOCK</span>
                                            @elseif($dev['status'] === 'IN_TRANSIT')
                                                <span class="badge badge-warning">IN TRANSIT</span>
                                            @elseif($dev['status'] === 'ISSUED')
                                                <span class="badge badge-info">ISSUED</span>
                                            @elseif($dev['status'] === 'RETURNED')
                                                <span class="badge badge-warning">RETURNED</span>
                                            @elseif($dev['status'] === 'UNDER_QC')
                                                <span class="badge badge-amber">UNDER QC</span>
                                            @elseif($dev['status'] === 'FLAGGED')
                                                <span class="badge badge-danger" style="background: rgba(239, 68, 68, 0.1); color: var(--danger-color); border: 1px solid var(--danger-color);">FLAGGED</span>
                                            @elseif($dev['status'] === 'DISPOSED')
                                                <span class="badge" style="background: var(--text-muted); color: white;">DISPOSED</span>
                                            @else
                                                <span class="badge badge-danger">{{ $dev['status'] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dev['status'] === 'ISSUED')
                                                <i class="fa-solid fa-user-gear" style="color: var(--accent-indigo); margin-right: 4px;"></i>
                                                {{ $dev['current_holder'] }}
                                            @else
                                                <i class="fa-solid fa-warehouse" style="color: var(--text-muted); margin-right: 4px;"></i>
                                                {{ $dev['current_holder'] }}
                                            @endif
                                        </td>
                                        <td style="color: var(--text-secondary); font-size: 13px;">{{ $dev['updated_at'] }}</td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline btn-icon-sm" title="Koreksi unit (manual adjustment)"
                                                onclick="openAdjust(@js($dev['id']), @js($dev['serial_number']), @js($dev['status']), @js($dev['warehouse_code']), @js($dev['current_holder']))">
                                                <i class="fa-solid fa-pen-to-square"></i> Koreksi
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pg-controls" id="pgResultsCtrl">
                        <div class="pg-perpage">Tampilkan
                            <select id="pgResultsPerPage" onchange="initPagination('tblResults','pgResultsCtrl','pgResultsPerPage')">
                                <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                            </select> data per halaman</div>
                        <div class="pg-info" id="pgResultsInfo"></div>
                        <div class="pg-nav" id="pgResultsNav"></div>
                    </div>
                </div>
            </div>
            @endif

            @if(count($gsm_results) > 0)
            {{-- ======= GSM SIM CARDS STOCK ======= --}}
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-sim-card" style="color: var(--accent-emerald);"></i>
                        <span>Stok Kartu SIM GSM — Hasil Pencarian</span>
                    </div>
                    <span class="badge" style="background: rgba(16,185,129,0.12); color: var(--accent-emerald); border: 1px solid rgba(16,185,129,0.25);">
                        {{ count($gsm_results) }} kartu
                    </span>
                </div>
                <div class="table-wrapper">
                    <table class="table" id="tblGsm">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>MSISDN</th>
                                <th>Provider</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Gudang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gsm_results as $i => $sim)
                                <tr>
                                    <td style="color: var(--text-muted); font-size: 12px;">{{ $i + 1 }}</td>
                                    <td style="font-weight: 600; font-family: monospace; color: var(--accent-blue);">{{ $sim['msisdn'] }}</td>
                                    <td>{{ $sim['provider'] ?? '-' }}</td>
                                    <td>{{ $sim['category'] ?? '-' }}</td>
                                    <td>
                                        @if(($sim['status'] ?? '') === 'IN_STOCK')
                                            <span class="badge badge-success">IN STOCK</span>
                                        @elseif(($sim['status'] ?? '') === 'INSTALLED')
                                            <span class="badge badge-info">INSTALLED</span>
                                        @else
                                            <span class="badge badge-warning">{{ $sim['status'] ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $sim['warehouse_code'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pg-controls" id="pgGsmCtrl">
                    <div class="pg-perpage">Tampilkan
                        <select id="pgGsmPerPage" onchange="initPagination('tblGsm','pgGsmCtrl','pgGsmPerPage')">
                            <option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
                        </select> data per halaman</div>
                    <div class="pg-info" id="pgGsmInfo"></div>
                    <div class="pg-nav" id="pgGsmNav"></div>
                </div>
            </div>
            @endif

            @if(count($accessory_results) > 0)
            {{-- ======= ACCESSORIES STOCK ======= --}}
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-boxes-stacked" style="color: var(--accent-amber);"></i>
                        <span>Stok Aksesoris — Hasil Pencarian</span>
                    </div>
                    <span class="badge" style="background: rgba(245,158,11,0.12); color: var(--accent-amber); border: 1px solid rgba(245,158,11,0.25);">
                        {{ count($accessory_results) }} baris
                    </span>
                </div>
                <div class="table-wrapper">
                    <table class="table" id="tblAcc">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Aksesoris</th>
                                <th>Jumlah (Qty)</th>
                                <th>Gudang</th>
                                <th>Lokasi / Pemegang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accessory_results as $i => $acc)
                                <tr>
                                    <td style="color: var(--text-muted); font-size: 12px;">{{ $i + 1 }}</td>
                                    <td style="font-family: monospace; font-size: 13px; color: var(--accent-indigo);">{{ $acc['code'] }}</td>
                                    <td style="font-weight: 500;">{{ $acc['name'] }}</td>
                                    <td>
                                        <span class="badge" style="background: rgba(245,158,11,0.15); color: var(--accent-amber); border: 1px solid rgba(245,158,11,0.3); font-size: 13px; font-weight: 700;">
                                            {{ $acc['qty'] }}
                                        </span>
                                    </td>
                                    <td>{{ $acc['warehouse_code'] }}</td>
                                    <td style="color: var(--text-secondary);">{{ $acc['location'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pg-controls" id="pgAccCtrl">
                    <div class="pg-perpage">Tampilkan
                        <select id="pgAccPerPage" onchange="initPagination('tblAcc','pgAccCtrl','pgAccPerPage')">
                            <option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
                        </select> data per halaman</div>
                    <div class="pg-info" id="pgAccInfo"></div>
                    <div class="pg-nav" id="pgAccNav"></div>
                </div>
            </div>
            @endif

            @if(count($audit_trails) > 0 || (count($results) == 0 && count($gsm_results) == 0 && count($accessory_results) == 0))
            <!-- Transaction Audit Trail Logs -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Riwayat Audit Trail (Lifecycle Log)</span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="table" id="tblAudit">
                        <thead>
                            <tr>
                                <th>No</th><th>Identifier / SN</th><th>Aksi Perubahan</th>
                                <th>Dari</th><th>Ke</th><th>Operator</th><th>Scanned By</th><th>Via</th><th>Tanggal & Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($audit_trails as $i => $tx)
                                <tr>
                                    <td style="color: var(--text-muted); font-size: 12px;">{{ $i + 1 }}</td>
                                    <td style="font-weight: 600; color: var(--accent-blue);">{{ $tx['device_sn'] }}</td>
                                    <td>
                                        @if(in_array($tx['action'], ['RECEIVING','IN','ADD']))
                                            <span class="badge badge-success">{{ $tx['action'] }}</span>
                                        @elseif(in_array($tx['action'], ['TRANSFER_OUT','TRANSFER_IN','OUT','TRANSFER']))
                                            <span class="badge badge-info">{{ $tx['action'] }}</span>
                                        @else
                                            <span class="badge badge-warning">{{ $tx['action'] }}</span>
                                        @endif
                                        @if(!empty($tx['notes']))
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><i class="fa-solid fa-circle-info"></i> {{ $tx['notes'] }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $tx['from'] }}</td><td>{{ $tx['to'] }}</td>
                                    <td>{{ $tx['operator'] }}</td>
                                    <td>
                                        @if($tx['scanned_by'] !== '-')
                                            <i class="fa-solid fa-barcode" style="font-size: 11px; margin-right: 4px;"></i>{{ $tx['scanned_by'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $tx['via'] }}</td>
                                    <td style="color: var(--text-secondary); font-size: 13px;">{{ $tx['timestamp'] }}</td>
                                </tr>
                            @empty
                                <x-empty-state colspan="9" icon="fa-clock-rotate-left"
                                    title="Belum ada riwayat audit trail"
                                    message="Tidak ada riwayat audit trail untuk data yang ditemukan dari kata kunci &quot;{{ $q }}&quot;." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="pg-controls" id="pgAuditCtrl">
                    <div class="pg-perpage">Tampilkan
                        <select id="pgAuditPerPage" onchange="initPagination('tblAudit','pgAuditCtrl','pgAuditPerPage')">
                            <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                        </select> data per halaman</div>
                    <div class="pg-info" id="pgAuditInfo"></div>
                    <div class="pg-nav" id="pgAuditNav"></div>
                </div>
            </div>
            @endif

        @elseif(empty($q))
            <!-- Empty state searching -->
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-chart-line" style="font-size: 48px; margin-bottom: 16px; color: var(--border-color);"></i>
                <h4 style="color: var(--text-secondary); font-size: 16px; font-weight: 600;">Temukan Detail Perangkat & Stok</h4>
                <p style="font-size: 13px; max-width: 400px; margin: 8px auto 0;">Masukkan Serial Number, IMEI, atau MSISDN di kolom
                    pencarian di atas untuk melihat detail siklus hidup dan lokasi perangkat.</p>
            </div>
        @endif


    </div>

    <!-- Device Manual Correction Modal -->
    <div id="adjustModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center;">
        <div class="card" style="width: 480px; max-width: 92vw; margin: 0; max-height: 92vh; overflow-y: auto;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-pen-to-square" style="color: var(--accent-amber);"></i> Koreksi Unit Perangkat</div>
                <button type="button" class="btn btn-outline btn-icon-sm" onclick="closeAdjust()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form action="{{ route('device.adjust') }}" method="POST">
                @csrf
                <input type="hidden" name="device_id" id="adj_device_id">
                <div class="form-group">
                    <label>Serial Number</label>
                    <input type="text" id="adj_sn" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label for="adj_status">Status</label>
                    <select name="status" id="adj_status" class="form-control" required>
                        @foreach(['IN_STOCK','IN_TRANSIT','ISSUED','INSTALLED','RETURNED','UNDER_QC','FLAGGED','LOST','DISPOSED'] as $st)
                            <option value="{{ $st }}">{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="adj_warehouse">Gudang (warehouse_code)</label>
                    <select name="warehouse_code" id="adj_warehouse" class="form-control" required>
                        @foreach($warehouses as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="adj_holder">Pemegang / Lokasi (current_holder)</label>
                    <input type="text" name="current_holder" id="adj_holder" class="form-control" required
                        placeholder="Contoh: Warehouse WH-PUSAT / Technician: Budi / Plat B 1234 SK">
                </div>
                <div class="form-group">
                    <label for="adj_reason">Alasan Koreksi <span style="color: var(--accent-red);">*</span></label>
                    <textarea name="reason" id="adj_reason" class="form-control" rows="2" required
                        placeholder="Contoh: Unit fisik ditemukan di gudang lain / salah scan / dilaporkan hilang"></textarea>
                </div>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeAdjust()">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan Koreksi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ---- Client-side Pagination ----
        var pgState = {};

        function initPagination(tableId, ctrlId, perPageId, page) {
            var table  = document.getElementById(tableId);
            if (!table) return;
            var tbody  = table.querySelector('tbody');
            if (!tbody) return;
            var allRows = Array.from(tbody.querySelectorAll('tr:not(.pg-empty)'));
            var perPageEl = document.getElementById(perPageId);
            var perPage = perPageEl ? parseInt(perPageEl.value) : 10;
            if (!pgState[tableId] || page === 1) pgState[tableId] = { page: page || 1 };
            if (page) pgState[tableId].page = page;
            var currentPage = pgState[tableId].page;
            var total = allRows.length;
            var totalPages = Math.max(1, Math.ceil(total / perPage));
            if (currentPage > totalPages) currentPage = pgState[tableId].page = 1;
            var start = (currentPage - 1) * perPage;
            var end   = start + perPage;

            allRows.forEach(function(row, i) {
                row.style.display = (i >= start && i < end) ? '' : 'none';
            });

            // Info
            var infoEl = document.getElementById(ctrlId.replace('Ctrl','Info'));
            if (infoEl) {
                if (total === 0) {
                    infoEl.textContent = '';
                } else {
                    infoEl.textContent = 'Menampilkan ' + (Math.min(start+1, total)) + '–' + Math.min(end, total) + ' dari ' + total + ' data';
                }
            }

            // Nav
            var navEl = document.getElementById(ctrlId.replace('Ctrl','Nav'));
            if (!navEl) return;
            navEl.innerHTML = '';
            if (totalPages <= 1) return;

            function mkBtn(label, pg, active, disabled) {
                var b = document.createElement('button');
                b.className = 'pg-btn' + (active ? ' active' : '');
                b.innerHTML = label;
                b.disabled = disabled;
                if (!disabled) b.onclick = function(){ initPagination(tableId, ctrlId, perPageId, pg); };
                return b;
            }

            navEl.appendChild(mkBtn('&laquo;', 1, false, currentPage === 1));
            navEl.appendChild(mkBtn('&lsaquo;', currentPage - 1, false, currentPage === 1));

            var winStart = Math.max(1, currentPage - 2);
            var winEnd   = Math.min(totalPages, currentPage + 2);
            if (winStart > 1) navEl.appendChild(mkBtn('...', winStart - 1, false, true));
            for (var p = winStart; p <= winEnd; p++) {
                navEl.appendChild(mkBtn(p, p, p === currentPage, false));
            }
            if (winEnd < totalPages) navEl.appendChild(mkBtn('...', winEnd + 1, false, true));

            navEl.appendChild(mkBtn('&rsaquo;', currentPage + 1, false, currentPage === totalPages));
            navEl.appendChild(mkBtn('&raquo;', totalPages, false, currentPage === totalPages));
        }

        document.addEventListener('DOMContentLoaded', function() {
            initPagination('tblResults', 'pgResultsCtrl', 'pgResultsPerPage', 1);
            initPagination('tblAudit',   'pgAuditCtrl',   'pgAuditPerPage',   1);
            initPagination('tblGsm',     'pgGsmCtrl',     'pgGsmPerPage',     1);
            initPagination('tblAcc',     'pgAccCtrl',     'pgAccPerPage',     1);
        });

        function openAdjust(id, sn, status, warehouse, holder) {
            document.getElementById('adj_device_id').value = id;
            document.getElementById('adj_sn').value = sn;

            const statusSel = document.getElementById('adj_status');
            if ([...statusSel.options].some(o => o.value === status)) statusSel.value = status;

            const whSel = document.getElementById('adj_warehouse');
            if ([...whSel.options].some(o => o.value === warehouse)) whSel.value = warehouse;

            document.getElementById('adj_holder').value = holder || '';
            document.getElementById('adj_reason').value = '';
            document.getElementById('adjustModal').style.display = 'flex';
        }

        function closeAdjust() {
            document.getElementById('adjustModal').style.display = 'none';
        }

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAdjust(); });
        document.getElementById('adjustModal').addEventListener('click', function (e) {
            if (e.target === this) closeAdjust();
        });


        // Tag-based Search Auto-complete Suggestion logic
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('multiSearchContainer');
            const searchInput = document.getElementById('searchInput');
            const hiddenInput = document.getElementById('hiddenSearchInput');
            const suggestionsBox = document.getElementById('searchSuggestions');
            let debounceTimer;
            let tags = [];

            // Inisialisasi tag dari value $q sebelumnya (jika ada)
            const initialQ = hiddenInput.value.trim();
            if (initialQ) {
                tags = initialQ.split(/[\s,]+/).filter(t => t);
                renderTags();
            }

            function renderTags() {
                container.querySelectorAll('.search-tag').forEach(el => el.remove());
                tags.forEach((tag, index) => {
                    const tagEl = document.createElement('span');
                    tagEl.className = 'search-tag badge badge-info';
                    tagEl.style.display = 'inline-flex';
                    tagEl.style.alignItems = 'center';
                    tagEl.style.gap = '6px';
                    tagEl.style.fontSize = '13px';
                    tagEl.style.padding = '4px 8px';
                    tagEl.innerHTML = `${tag} <i class="fa-solid fa-xmark remove-tag" data-index="${index}" style="cursor: pointer; padding: 2px;"></i>`;
                    container.insertBefore(tagEl, searchInput);
                });
                hiddenInput.value = tags.join(' ');
                searchInput.placeholder = tags.length > 0 ? '' : 'Ketik SN / IMEI lalu tekan Spasi atau Koma...';
            }

            if (container) {
                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-tag')) {
                        const idx = e.target.getAttribute('data-index');
                        tags.splice(idx, 1);
                        renderTags();
                    } else if (e.target === container) {
                        searchInput.focus();
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === ',' || e.key === ' ') {
                        e.preventDefault();
                        const val = this.value.trim();
                        if (val) {
                            const newTags = val.split(/[\s,]+/).filter(t => t);
                            tags.push(...newTags);
                            this.value = '';
                            suggestionsBox.style.display = 'none';
                            renderTags();
                        }
                    } else if (e.key === 'Enter') {
                        const val = this.value.trim();
                        if (val) {
                            e.preventDefault();
                            const newTags = val.split(/[\s,]+/).filter(t => t);
                            tags.push(...newTags);
                            this.value = '';
                            suggestionsBox.style.display = 'none';
                            renderTags();
                        } else if (tags.length === 0) {
                            e.preventDefault(); // Jangan submit jika kosong
                        }
                    } else if (e.key === 'Backspace' && this.value === '' && tags.length > 0) {
                        tags.pop();
                        renderTags();
                    }
                });

                searchInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text');
                    const newTags = pasted.split(/[\s,]+/).filter(t => t);
                    tags.push(...newTags);
                    renderTags();
                });

                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    const query = this.value.trim();

                    if (query.length < 2) {
                        suggestionsBox.style.display = 'none';
                        return;
                    }

                    debounceTimer = setTimeout(() => {
                        const whCode = container ? container.dataset.warehouse : '';
                        let apiUrl = `{{ route('api.devices.search') }}?q=${encodeURIComponent(query)}&source_type=all`;
                        if (whCode) apiUrl += `&warehouse=${encodeURIComponent(whCode)}`;
                        fetch(apiUrl)
                            .then(res => res.json())
                            .then(resData => {
                                if (resData.suggestion_type === 'count') {
                                    suggestionsBox.innerHTML = '';
                                    const div = document.createElement('div');
                                    div.className = 'suggestion-item';
                                    div.style.padding = '12px 16px';
                                    div.innerHTML = `<div style="font-weight: 600; color: var(--accent-blue);"><i class="fa-solid fa-magnifying-glass"></i> ${resData.total} data ditemukan</div><div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Tekan Enter untuk menampilkan seluruh data ini.</div>`;
                                    div.addEventListener('click', function() {
                                        tags.push(query);
                                        searchInput.value = '';
                                        suggestionsBox.style.display = 'none';
                                        renderTags();
                                        if (form) form.submit();
                                    });
                                    suggestionsBox.appendChild(div);
                                    suggestionsBox.style.display = 'block';
                                } else {
                                    const data = resData.data || resData || [];
                                    if (data.length > 0) {
                                        suggestionsBox.innerHTML = '';
                                        data.forEach(item => {
                                            const div = document.createElement('div');
                                            div.className = 'suggestion-item';
                                            div.innerHTML = `<div class="s-sn">${item.serial_number}</div><div class="s-detail">${item.type ? item.type + ' &bull; ' : ''}${item.model} &bull; ${item.status}</div>`;
                                            div.addEventListener('click', function() {
                                                tags.push(item.serial_number);
                                                searchInput.value = '';
                                                suggestionsBox.style.display = 'none';
                                                renderTags();
                                                searchInput.focus();
                                            });
                                            suggestionsBox.appendChild(div);
                                        });
                                        suggestionsBox.style.display = 'block';
                                    } else {
                                        suggestionsBox.innerHTML = '<div class="suggestion-empty">Tidak ada data ditemukan</div>';
                                        suggestionsBox.style.display = 'block';
                                    }
                                }
                            })
                            .catch(err => console.error('Error fetching suggestions:', err));
                    }, 300);
                });

                document.addEventListener('click', function(e) {
                    if (!container.contains(e.target) && !suggestionsBox.contains(e.target)) {
                        suggestionsBox.style.display = 'none';
                    }
                });
            }
            
            // Tambahkan event onsubmit pada form agar input yang menggantung (belum di-spasi) ikut dimasukkan
            const form = searchInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const val = searchInput.value.trim();
                    if (val) {
                        const newTags = val.split(/[\s,]+/).filter(t => t);
                        tags.push(...newTags);
                        searchInput.value = '';
                        renderTags();
                    }
                });
            }
        });
    </script>
@endsection