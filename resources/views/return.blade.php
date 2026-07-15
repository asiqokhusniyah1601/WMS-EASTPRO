@extends('layouts.app')

<!--@yield('title', 'Return Perangkat | DLMS')-->

@section('styles')
<style>
    /* ====== Return Perangkat — Focus Layout ====== */
    .return-split { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; }
    @media (max-width: 1100px) { .return-split { grid-template-columns: 1fr; } }
    .return-sticky { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 20px; }

    /* Scan area sebagai fokus utama */
    .scan-hero { transition: box-shadow .2s ease, border-color .2s ease; }
    .scan-hero:focus {
        border-color: var(--accent-blue) !important;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.18), 0 0 18px rgba(59,130,246,0.28);
        outline: none;
    }
    .scan-hero-card { border: 1px solid rgba(59,130,246,0.30); }

    /* Tombol "+ Tambah" untuk seksi opsional */
    .opt-add-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px;
        border: 1px solid var(--accent-indigo, #6366f1); background: rgba(99,102,241,0.10); color: var(--accent-indigo, #6366f1);
        font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s ease; white-space: nowrap;
    }
    .opt-add-btn:hover { background: rgba(99,102,241,0.20); }
    .opt-add-btn.open { background: var(--bg-secondary); border-color: var(--border-color); color: var(--text-secondary); }

    /* Baris perangkat yang sudah masuk daftar (feedback visual) */
    .row-added td:first-child { box-shadow: inset 3px 0 0 var(--accent-emerald); }

    /* Ringkasan pengembalian di panel sticky */
    .ship-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 4px 0 18px; }
    .ship-summary .ss-box {
        text-align: center; padding: 10px 6px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary);
    }
    .ship-summary .ss-num { font-size: 24px; font-weight: 700; line-height: 1.1; color: var(--accent-blue); }
    .ship-summary .ss-lbl { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
    .shortcut-hint { font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 10px; }
    .shortcut-hint kbd {
        background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; padding: 1px 6px; font-size: 10px;
    }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-boxes-packing"
        title="Return Perangkat & Aksesori"
        subtitle="Terima pengembalian perangkat dari teknisi atau pelanggan untuk dilakukan inspeksi." />

    @if(session('success'))
        <div class="alert-box alert-success animate-fade-in" style="margin-bottom: 24px;">
            <div class="alert-icon"><i class="fa-solid fa-check-circle"></i></div>
            <div class="alert-message">{{ session('success') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="alert-box alert-danger animate-fade-in" style="margin-bottom: 24px;">
            <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="alert-message">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div style="display:flex; gap:0; border-bottom:2px solid var(--border-color); margin-bottom:24px;">
        <button class="btn" id="tabFormBtn"
            style="border-radius:0; padding:10px 20px 12px; background:none;
                   border:none; border-bottom:3px solid var(--accent-blue);
                   color:var(--text-primary); font-size:13px; font-weight:600;
                   margin-bottom:-2px; cursor:pointer; transition:all .2s;">
            <i class="fa-solid fa-boxes-packing" style="color:var(--accent-blue); margin-right:6px;"></i>
            Form Return Perangkat
        </button>
        <button class="btn" id="tabHistoryBtn"
            style="border-radius:0; padding:10px 20px 12px; background:none;
                   border:none; border-bottom:3px solid transparent;
                   color:var(--text-secondary); font-size:13px;
                   margin-bottom:-2px; cursor:pointer; transition:all .2s;">
            <i class="fa-solid fa-clock-rotate-left" style="color:var(--text-muted); margin-right:6px;"></i>
            Riwayat Return
        </button>
    </div>

    <div id="panelFormReturn">
        <form action="{{ route('return.post') }}" method="POST" id="returnForm">
        @csrf
        <div class="return-split">

            <!-- LEFT: Scan & item opsional -->
            <div>
                <!-- Scan Perangkat (fokus utama) -->
                <div class="card scan-hero-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-barcode"></i>
                            <span>Scan Perangkat Utama</span>
                        </div>
                        <span class="badge badge-warning">→ RETURNED (menunggu QC)</span>
                    </div>

                    <!-- Mode Toggle -->
                    <div style="display:flex; gap:0; margin-bottom:18px; border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                        <button type="button" id="btnModeSingle"
                            onclick="setScanMode('single')"
                            style="flex:1; padding:10px 16px; background:var(--accent-blue); color:#fff; border:none; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .2s;">
                            <i class="fa-solid fa-barcode"></i> Scan Satu per Satu
                        </button>
                        <button type="button" id="btnModeBulk"
                            onclick="setScanMode('bulk')"
                            style="flex:1; padding:10px 16px; background:var(--bg-secondary); color:var(--text-secondary); border:none; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .2s;">
                            <i class="fa-solid fa-list-check"></i> Input Massal (Multi-SN)
                        </button>
                    </div>

                    <!-- MODE: Scan Satu per Satu -->
                    <div id="scanSingleMode">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; color: var(--accent-blue);">SCAN / KETIK SERIAL NUMBER (SN)</label>
                            <div style="display: flex; gap: 8px;">
                                <div style="position: relative; flex-grow: 1;">
                                    <i class="fa-solid fa-barcode" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                    <input type="text" id="sn_input" class="form-control scan-hero" placeholder="Scan barcode SN atau ketik manual lalu Enter..." style="padding-left: 52px; font-size: 17px; font-weight: 600; height: 54px; border-color: rgba(59,130,246,0.4); width: 100%;">
                                </div>
                                <button type="button" class="btn btn-outline" style="height: 54px;" onclick="addSn()">
                                    <i class="fa-solid fa-plus"></i> Tambah
                                </button>
                            </div>
                            <small style="color: var(--text-muted); margin-top: 6px; display: block;">Perangkat yang direturn masuk ke status <strong>RETURNED</strong> dan menunggu QC.</small>
                        </div>
                    </div>

                    <!-- MODE: Input Massal (Multi-SN) -->
                    <div id="scanBulkMode" style="display:none;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; color: var(--accent-blue);">MASUKKAN BEBERAPA SERIAL NUMBER (SATU PER BARIS)</label>
                            <textarea id="bulk_sn_input" rows="7"
                                class="form-control"
                                placeholder="Scan beberapa barcode di sini, atau ketik SN satu per baris:&#10;SN-001&#10;SN-002&#10;SN-003"
                                style="font-family: monospace; font-size: 13px; resize: vertical; line-height: 1.6; border-color: rgba(59,130,246,0.4);"></textarea>
                        </div>
                        <div style="display:flex; gap:10px; margin-top:4px;">
                            <button type="button" class="btn btn-primary" style="flex:1; justify-content:center; padding:12px;" onclick="processBulkSn()">
                                <i class="fa-solid fa-play"></i> Proses Semua SN
                            </button>
                            <button type="button" class="btn btn-outline" style="padding:12px 18px;" onclick="document.getElementById('bulk_sn_input').value='';">
                                <i class="fa-solid fa-xmark"></i> Bersihkan
                            </button>
                        </div>
                        <small style="color: var(--text-muted); margin-top: 8px; display: block;">Scan banyak barcode sekaligus ke kolom ini (scanner otomatis ganti baris), lalu klik "Proses Semua SN".</small>
                    </div>

                    <!-- Tabel hasil (tampil di kedua mode) -->
                    <div class="table-wrapper" style="margin-top: 16px;">
                        <table class="table" id="scanned_table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Serial Number</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Scanned items will appear here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Return Aksesoris (opsional, collapsible) -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-plug"></i>
                            <span>Return Aksesoris (Opsional)</span>
                        </div>
                        <button type="button" class="opt-add-btn" id="toggleAccSection"><i class="fa-solid fa-plus"></i> Tambah Aksesoris</button>
                    </div>

                    <div id="accSectionBody" style="display: none;">
                        <!-- AI Suggestion Pills for Accessories -->
                        @if(isset($suggestedAccessories) && count($suggestedAccessories) > 0)
                        <div class="ai-suggestion-container" style="margin-bottom: 16px;">
                            <span class="ai-suggestion-title">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Suggestion:
                            </span>
                            @foreach($suggestedAccessories as $sAcc)
                                <button type="button" class="ai-pill-btn quick-acc-btn"
                                    data-code="{{ $sAcc['code'] }}"
                                    onclick="addSuggestedAcc('{{ $sAcc['code'] }}')">
                                    <i class="fa-solid fa-plus" style="font-size: 9px;"></i>
                                    {{ $sAcc['name'] ?? $sAcc['code'] }}
                                </button>
                            @endforeach
                        </div>
                        @endif

                        <div id="acc_container">
                            <!-- Accessory rows here -->
                        </div>

                        <button type="button" class="btn btn-outline" style="font-size: 13px;" onclick="addAccRow()">
                            <i class="fa-solid fa-plus"></i> Tambah Item Aksesoris
                        </button>
                    </div>
                </div>

                <!-- Return Kartu GSM (opsional, collapsible) -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-sim-card" style="color: var(--accent-indigo);"></i>
                            <span>Return Kartu GSM (Opsional)</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="badge badge-info" id="returnSimBadge">0 dipilih</span>
                            <button type="button" class="opt-add-btn" id="toggleSimSection"><i class="fa-solid fa-plus"></i> Tambah Kartu GSM</button>
                        </div>
                    </div>

                    <div id="simSectionBody" style="display: none;">
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">Kartu GSM yang sebelumnya diserahkan ke teknisi/customer (tanpa perangkat) akan kembali ke status <strong>IN_STOCK</strong> di gudang penerima.</p>

                        @if(!empty($returnableSims))
                            <div class="form-group" style="margin-bottom: 12px;">
                                <div style="position:relative;">
                                    <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:16px; top:13px; color:var(--text-muted);"></i>
                                    <input type="text" id="return_sim_search" class="form-control" placeholder="Cari MSISDN / provider..." style="padding-left:44px;" autocomplete="off">
                                </div>
                            </div>
                            <div class="table-wrapper" style="max-height: 240px; overflow-y: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;"><input type="checkbox" id="return_sim_all"></th>
                                            <th>MSISDN</th>
                                            <th>Provider</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($returnableSims as $sim)
                                            <tr class="return-sim-row" data-search="{{ strtolower($sim['msisdn'] . ' ' . $sim['provider']) }}">
                                                <td><input type="checkbox" class="return-sim-check" name="return_sim_ids[]" value="{{ $sim['id'] }}"></td>
                                                <td style="font-weight:600; color:var(--accent-indigo);">{{ $sim['msisdn'] }}</td>
                                                <td>{{ $sim['provider'] }}</td>
                                                <td><span class="badge badge-warning">{{ $sim['status'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p style="font-size: 13px; color: var(--text-muted);"><i class="fa-solid fa-circle-info"></i> Tidak ada kartu GSM mandiri yang sedang dipegang teknisi/customer.</p>
                        @endif
                    </div>
                </div>

            </div>

            <!-- RIGHT: Detail pengembalian (sticky) -->
            <div>
                <div class="return-sticky">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-clipboard-check"></i>
                                <span>Detail Pengembalian</span>
                            </div>
                        </div>

                        <!-- Ringkasan barang yang dikembalikan -->
                        <div class="ship-summary">
                            <div class="ss-box"><div class="ss-num" id="sumDevices">0</div><div class="ss-lbl">Perangkat</div></div>
                            <div class="ss-box"><div class="ss-num" id="sumAcc">0</div><div class="ss-lbl">Aksesoris</div></div>
                            <div class="ss-box"><div class="ss-num" id="sumSim">0</div><div class="ss-lbl">Kartu GSM</div></div>
                        </div>

                        <x-warehouse-select
                            name="warehouse"
                            label="Gudang Penerima (Lokasi Saat Ini)"
                            :warehouses="\App\Models\Warehouse::orderBy('name')->get()"
                            hint="Gudang tempat barang ini diterima."
                            :readonly="true"
                        />

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label">Lokasi Rak Penyimpanan</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-layer-group" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" name="rack_barcode" class="form-control" placeholder="Scan/ketik barcode rak..." style="padding-left: 36px;">
                            </div>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Opsional. Barang akan diletakkan di rak ini.</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label">Dikembalikan Oleh</label>
                            @php
                                $role = auth()->user()->role;
                                $returnByOptions = [];
                                if ($role === 'super_admin' || $role === 'qc') {
                                    foreach ($technicians as $t) {
                                        $returnByOptions[] = ['value' => 'Technician: ' . $t->name, 'label' => $t->name, 'group' => 'Teknisi'];
                                    }
                                    foreach ($customers as $c) {
                                        $returnByOptions[] = ['value' => 'Customer: ' . $c->name, 'label' => $c->name, 'group' => 'Pelanggan'];
                                    }
                                } elseif ($role === 'admin' || $role === 'pic') {
                                    foreach ($customers as $c) {
                                        $returnByOptions[] = ['value' => 'Customer: ' . $c->name, 'label' => $c->name, 'group' => 'Pelanggan'];
                                    }
                                } elseif ($role === 'technician') {
                                    $returnByOptions[] = ['value' => 'Technician: ' . auth()->user()->name, 'label' => auth()->user()->name, 'group' => 'Diri Sendiri'];
                                    foreach ($customers as $c) {
                                        $returnByOptions[] = ['value' => 'Customer: ' . $c->name, 'label' => $c->name, 'group' => 'Pelanggan'];
                                    }
                                }
                            @endphp

                            {{-- Hidden input yang dikirim ke server --}}
                            <input type="hidden" name="returned_by" id="returnedByValue">

                            {{-- Custom searchable dropdown --}}
                            <div id="returnedByDropdown" style="position: relative; z-index: 99;">
                                <div style="position: relative;">
                                    <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;"></i>
                                    <input
                                        type="text"
                                        id="returnedBySearch"
                                        class="form-control"
                                        placeholder="Ketik untuk cari nama..."
                                        autocomplete="off"
                                        required
                                        style="padding-left: 36px; padding-right: 36px;"
                                    >
                                    <i class="fa-solid fa-chevron-down" id="returnedByChevron" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none; transition: transform .2s;"></i>
                                </div>
                                <div id="returnedByList" style="
                                    display: none;
                                    position: absolute;
                                    top: calc(100% + 4px);
                                    left: 0; right: 0;
                                    background: var(--bg-secondary, #ffffff);
                                    border: 1px solid var(--border-color);
                                    border-radius: 8px;
                                    max-height: 260px;
                                    overflow-y: auto;
                                    z-index: 999;
                                    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                                ">
                                    <div id="returnedByItems">
                                        @php $lastGroup = null; @endphp
                                        @foreach($returnByOptions as $opt)
                                            @if($opt['group'] !== $lastGroup)
                                                <div style="padding: 6px 12px 2px; font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; {{ $lastGroup ? 'border-top: 1px solid var(--border-color); margin-top: 4px;' : '' }}">
                                                    {{ $opt['group'] }}
                                                </div>
                                                @php $lastGroup = $opt['group']; @endphp
                                            @endif
                                            <div class="rby-item"
                                                data-value="{{ $opt['value'] }}"
                                                data-label="{{ $opt['label'] }}"
                                                data-group="{{ $opt['group'] }}"
                                                style="padding: 9px 16px; cursor: pointer; font-size: 13px; color: var(--text-primary);"
                                                onmouseover="this.style.background='var(--bg-primary)'"
                                                onmouseout="this.style.background=''">
                                                {{ $opt['label'] }}
                                                <span style="font-size:11px; color:var(--text-muted); margin-left:6px;">({{ $opt['group'] }})</span>
                                            </div>
                                        @endforeach
                                        <div id="rbyNoResult" style="display:none; padding:16px; text-align:center; color:var(--text-muted); font-size:13px;">
                                            <i class="fa-solid fa-circle-xmark"></i> Tidak ada hasil
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Pilih siapa yang mengembalikan barang ini.</small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label">Catatan Internal (Opsional)</label>
                            <textarea name="internal_note" class="form-control" rows="2" placeholder="Catatan ini hanya bisa dibaca oleh admin/penerima dan tidak akan dicetak di surat tanda terima."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alasan Pengembalian (Status Alat)</label>
                            <select name="return_reason" class="form-control" required>
                                <option value="">-- Pilih Alasan Pengembalian --</option>
                                <option value="Cabut - Uninstall">Cabut - Uninstall</option>
                                <option value="Cabut - Rusak">Cabut - Rusak</option>
                                <option value="Cabut - Sementara">Cabut - Sementara</option>
                                <option value="Alat Baru">Alat Baru</option>
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Pilih alasan atau kondisi alat saat dikembalikan.</small>
                        </div>

                        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 10px;">
                            <button type="submit" class="btn btn-primary" id="btn_submit" style="width: 100%; justify-content: center; padding: 12px;" disabled>
                                <i class="fa-solid fa-boxes-packing"></i> Proses Return
                            </button>
                            <button type="button" class="btn btn-outline" style="width: 100%; justify-content: center;" onclick="window.location.reload()">Reset</button>
                            <div class="shortcut-hint">Pintasan: <kbd>Ctrl</kbd> + <kbd>Enter</kbd> untuk Proses Return</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div>
    </form>
    </div>

    <!-- PANEL HISTORY RETURN -->
    <div id="panelHistoryReturn" style="display: none;">
        <div class="card" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                <div>
                    <h4 style="margin: 0; font-weight: 700; color: var(--text-primary); font-size: 16px;">
                        <i class="fa-solid fa-list-check" style="color: var(--accent-blue); margin-right: 8px;"></i>
                        Riwayat Return
                    </h4>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-muted);">
                        Daftar riwayat perangkat yang telah direturn ke gudang.
                    </p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary" onclick="loadHistoryReturn()" style="padding: 8px 16px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Refresh Data
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="table" id="tblHistoryReturn">
                    <thead>
                        <tr>
                            <th>Tanggal Return</th>
                            <th>No. Dokumen</th>
                            <th>Dikembalikan Oleh</th>
                            <th>Penerima (Operator)</th>
                            <th>Alasan (Keterangan)</th>
                            <th style="text-align:center;">Dokumen</th>
                        </tr>
                    </thead>
                    <tbody id="historyReturnBody">
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Memuat riwayat return...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let snCount = 0;

    // ============================
    // Toggle: Scan Mode
    // ============================
    function setScanMode(mode) {
        const singleMode = document.getElementById('scanSingleMode');
        const bulkMode   = document.getElementById('scanBulkMode');
        const btnSingle  = document.getElementById('btnModeSingle');
        const btnBulk    = document.getElementById('btnModeBulk');

        if (mode === 'single') {
            singleMode.style.display = 'block';
            bulkMode.style.display   = 'none';
            btnSingle.style.background  = 'var(--accent-blue)';
            btnSingle.style.color       = '#fff';
            btnSingle.style.fontWeight  = '600';
            btnBulk.style.background    = 'var(--bg-secondary)';
            btnBulk.style.color         = 'var(--text-secondary)';
            btnBulk.style.fontWeight    = 'normal';
            const snInputEl = document.getElementById('sn_input');
            if (snInputEl) snInputEl.focus();
        } else {
            singleMode.style.display = 'none';
            bulkMode.style.display   = 'block';
            btnBulk.style.background    = 'var(--accent-blue)';
            btnBulk.style.color         = '#fff';
            btnBulk.style.fontWeight    = '600';
            btnSingle.style.background  = 'var(--bg-secondary)';
            btnSingle.style.color       = 'var(--text-secondary)';
            btnSingle.style.fontWeight  = 'normal';
            const bulkInput = document.getElementById('bulk_sn_input');
            if (bulkInput) bulkInput.focus();
        }
    }

    // ============================
    // Proses Bulk SN
    // ============================
    function processBulkSn() {
        const textarea = document.getElementById('bulk_sn_input');
        if (!textarea) return;

        const lines = textarea.value.split('\n')
            .map(l => l.trim())
            .filter(l => l.length > 0);

        if (lines.length === 0) {
            alert('Tidak ada Serial Number yang dimasukkan.');
            return;
        }

        let added = 0, skipped = 0, duplicates = [];
        const existingSns = () => Array.from(document.querySelectorAll('input[name="sns[]"]')).map(el => el.value);

        lines.forEach(sn => {
            if (existingSns().includes(sn)) {
                skipped++;
                duplicates.push(sn);
                return;
            }
            addSnToTable(sn);
            added++;
        });

        textarea.value = '';

        let msg = `${added} SN berhasil ditambahkan ke daftar return.`;
        if (skipped > 0) msg += `\n${skipped} SN dilewati (duplikat): ${duplicates.join(', ')}`;
        if (added > 0 && window.playBeep) window.playBeep('success');
        alert(msg);
    }

    // Shared helper — tambah SN ke tabel (dipakai oleh addSn() dan processBulkSn())
    function addSnToTable(sn) {
        snCount++;
        const tbody = document.querySelector('#scanned_table tbody');
        const rowId = `row-sn-${sn.replace(/[^a-zA-Z0-9]/g, '-')}`;
        const tr = document.createElement('tr');
        tr.setAttribute('id', rowId);
        tr.className = 'animate-fade-in row-added';
        tr.innerHTML = `
            <td>${snCount}</td>
            <td style="font-weight: 600; color: var(--accent-blue);">
                <i class="fa-solid fa-circle-check" style="color:var(--accent-emerald); margin-right:6px;" title="Sudah masuk daftar return"></i>${sn}
                <input type="hidden" name="sns[]" value="${sn}">
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-outline" style="color: var(--danger-color); padding: 4px 8px;"
                    onclick="document.getElementById('${rowId}').remove(); if(window.playBeep) window.playBeep('error'); checkSubmitBtn();">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        checkSubmitBtn();
    }

    function addSn() {
        const input = document.getElementById('sn_input');
        const sn = input.value.trim();
        
        if (!sn) {
            if (window.playBeep) window.playBeep('error');
            return;
        }

        // Duplicate check
        const existingSns = Array.from(document.querySelectorAll('input[name="sns[]"]')).map(el => el.value);
        if (existingSns.includes(sn)) {
            if (window.playBeep) window.playBeep('error');
            input.value = '';
            input.focus();
            alert('Serial Number ini sudah ditambahkan ke draft!');
            return;
        }

        addSnToTable(sn);
        input.value = '';
        input.focus();
        if (window.playBeep) window.playBeep('success');
    }
    
    document.getElementById('sn_input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSn();
        }
    });
    
    function checkSubmitBtn() {
        const sns = document.querySelectorAll('input[name="sns[]"]');
        const hasDevices = sns.length > 0;

        // Also check if any accessory row has qty > 0
        let hasAcc = false;
        let accUnits = 0;
        const accQtys = document.querySelectorAll('#acc_container input[name="acc_qtys[]"]');
        accQtys.forEach(input => {
            const v = parseInt(input.value) || 0;
            if (v > 0) { hasAcc = true; accUnits += v; }
        });

        const simCount = document.querySelectorAll('.return-sim-check:checked').length;
        const hasSim = simCount > 0;

        document.getElementById('btn_submit').disabled = !(hasDevices || hasAcc || hasSim);

        // Ringkasan pengembalian di panel sticky
        const sumD = document.getElementById('sumDevices');
        const sumA = document.getElementById('sumAcc');
        const sumS = document.getElementById('sumSim');
        if (sumD) sumD.innerText = sns.length;
        if (sumA) sumA.innerText = accUnits;
        if (sumS) sumS.innerText = simCount;
    }

    // Return Kartu GSM: pencarian, pilih semua, badge.
    (function initReturnSim() {
        const search = document.getElementById('return_sim_search');
        const checkAll = document.getElementById('return_sim_all');
        const badge = document.getElementById('returnSimBadge');

        function updateBadge() {
            if (badge) badge.innerText = document.querySelectorAll('.return-sim-check:checked').length + ' dipilih';
            checkSubmitBtn();
        }
        if (search) {
            search.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('.return-sim-row').forEach(row => {
                    row.style.display = (!q || (row.dataset.search || '').includes(q)) ? '' : 'none';
                });
            });
        }
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.return-sim-row').forEach(row => {
                    if (row.style.display === 'none') return;
                    const cb = row.querySelector('.return-sim-check');
                    if (cb) cb.checked = this.checked;
                });
                updateBadge();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('return-sim-check')) updateBadge();
        });
    })();

    // Re-check submit state when accessory rows change
    const accObserver = new MutationObserver(() => checkSubmitBtn());
    accObserver.observe(document.getElementById('acc_container'), { childList: true, subtree: true });
    document.getElementById('acc_container').addEventListener('input', checkSubmitBtn);

    const accessories = @json($accessories);
    
    function addAccRow(selectedCode = '') {
        const container = document.getElementById('acc_container');
        const rowId = 'acc_row_' + Date.now();
        
        let options = '<option value="">-- Pilih Aksesoris --</option>';
        Object.values(accessories).forEach(acc => {
            const selected = acc.code === selectedCode ? 'selected' : '';
            options += `<option value="${acc.code}" ${selected}>${acc.name}</option>`;
        });
        
        const html = `
            <div class="form-group" id="${rowId}" style="display: flex; gap: 12px; align-items: flex-end; background: var(--bg-primary); padding: 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                <div style="flex-grow: 1;">
                    <label class="form-label" style="font-size: 12px;">Jenis Aksesoris</label>
                    <select name="acc_types[]" class="form-control" required>
                        ${options}
                    </select>
                </div>
                <div style="width: 120px;">
                    <label class="form-label" style="font-size: 12px;">Qty Return</label>
                    <input type="number" name="acc_qtys[]" class="form-control" min="1" value="1" required>
                </div>
                <div>
                    <button type="button" class="btn btn-outline" style="color: var(--danger-color); height: 40px;" onclick="document.getElementById('${rowId}').remove(); if(window.playBeep) window.playBeep('error');">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', html);
    }

    function addSuggestedAcc(code) {
        // Check if there is an existing row with this select value
        const selects = document.querySelectorAll('#acc_container select[name="acc_types[]"]');
        let found = false;
        selects.forEach(select => {
            if (select.value === code) {
                // Find sibling qty input
                const parent = select.closest('.form-group');
                const qtyInput = parent.querySelector('input[name="acc_qtys[]"]');
                if (qtyInput) {
                    qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                    qtyInput.focus();
                }
                found = true;
            }
        });

        if (!found) {
            // Add a new row and select the code
            addAccRow(code);
        }
        if (window.playBeep) window.playBeep('success');
    }

    // ==========================================
    // UI/UX: collapsible optional sections + shortcut
    // ==========================================
    function makeSectionToggle(btnId, bodyId, addLabel, hideLabel) {
        const btn = document.getElementById(btnId);
        const body = document.getElementById(bodyId);
        if (!btn || !body) return;
        btn.addEventListener('click', () => {
            const show = body.style.display === 'none';
            body.style.display = show ? 'block' : 'none';
            btn.classList.toggle('open', show);
            btn.innerHTML = show
                ? '<i class="fa-solid fa-chevron-up"></i> ' + hideLabel
                : '<i class="fa-solid fa-plus"></i> ' + addLabel;
        });
    }
    makeSectionToggle('toggleAccSection', 'accSectionBody', 'Tambah Aksesoris', 'Sembunyikan');
    makeSectionToggle('toggleSimSection', 'simSectionBody', 'Tambah Kartu GSM', 'Sembunyikan');

    // Keyboard shortcut: Ctrl/Cmd + Enter untuk Proses Return
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const btn = document.getElementById('btn_submit');
            if (btn && !btn.disabled && document.getElementById('panelFormReturn').style.display !== 'none') {
                e.preventDefault();
                document.getElementById('returnForm').requestSubmit();
            }
        }
    });

    // Tabs navigation
    const tabFormBtn = document.getElementById('tabFormBtn');
    const tabHistoryBtn = document.getElementById('tabHistoryBtn');
    const panelFormReturn = document.getElementById('panelFormReturn');
    const panelHistoryReturn = document.getElementById('panelHistoryReturn');

    // Auto-focus area scan saat halaman dimuat
    const snInputEl = document.getElementById('sn_input');
    if (snInputEl) snInputEl.focus();

    if (tabFormBtn && tabHistoryBtn) {
        tabFormBtn.addEventListener('click', () => {
            tabFormBtn.style.borderBottomColor = 'var(--accent-blue)';
            tabFormBtn.style.color = 'var(--text-primary)';
            tabFormBtn.style.fontWeight = '600';
            tabHistoryBtn.style.borderBottomColor = 'transparent';
            tabHistoryBtn.style.color = 'var(--text-secondary)';
            tabHistoryBtn.style.fontWeight = 'normal';
            panelFormReturn.style.display = 'block';
            panelHistoryReturn.style.display = 'none';
            if (snInputEl) snInputEl.focus();
        });

        tabHistoryBtn.addEventListener('click', () => {
            tabHistoryBtn.style.borderBottomColor = 'var(--accent-blue)';
            tabHistoryBtn.style.color = 'var(--text-primary)';
            tabHistoryBtn.style.fontWeight = '600';
            tabFormBtn.style.borderBottomColor = 'transparent';
            tabFormBtn.style.color = 'var(--text-secondary)';
            tabFormBtn.style.fontWeight = 'normal';
            panelFormReturn.style.display = 'none';
            panelHistoryReturn.style.display = 'block';
            loadHistoryReturn();
        });
    }

    function loadHistoryReturn() {
        const tbody = document.getElementById('historyReturnBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Memuat data...</td></tr>';
        
        fetch(`{{ route('api.return.history') }}?start_date=2024-01-01&end_date={{ now()->format('Y-m-d') }}`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Belum ada riwayat return.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                data.forEach(item => {
                    const dateObj = new Date(item.created_at);
                    const formattedDate = dateObj.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
                    
                    let internalNoteText = item.internal_note ? `<br><small style="color:var(--accent-orange);"><i class="fa-solid fa-note-sticky"></i> Note: ${item.internal_note}</small>` : '';
                    let reasonText = `${item.notes || '-'}${internalNoteText}`;
                    
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${formattedDate}</td>
                        <td style="font-weight:600; font-family:monospace; color:var(--accent-blue);">${item.receipt_no}</td>
                        <td>${item.returned_by || '-'}</td>
                        <td>${item.operator || '-'}</td>
                        <td>${reasonText}</td>
                        <td style="text-align:center;">
                            <a href="/return-receipt/${item.receipt_no}" target="_blank"
                               class="btn btn-secondary"
                               style="padding:4px 10px; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> Download
                            </a>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#ef4444;">Gagal memuat riwayat.</td></tr>';
                console.error(err);
            });
    }


    // ============================
    // Searchable Dropdown: Dikembalikan Oleh
    // ============================
    (function initRbyDropdown() {
        const searchInput  = document.getElementById('returnedBySearch');
        const hiddenInput  = document.getElementById('returnedByValue');
        const listEl       = document.getElementById('returnedByList');
        const chevron      = document.getElementById('returnedByChevron');
        const noResult     = document.getElementById('rbyNoResult');
        const items        = document.querySelectorAll('.rby-item');

        if (!searchInput) return;

        function openList() {
            listEl.style.display = 'block';
            chevron.style.transform = 'translateY(-50%) rotate(180deg)';
        }

        function closeList() {
            listEl.style.display = 'none';
            chevron.style.transform = 'translateY(-50%) rotate(0deg)';
        }

        function filterItems(q) {
            q = q.toLowerCase().trim();
            let anyVisible = false;
            items.forEach(item => {
                const label = (item.dataset.label || '').toLowerCase();
                const group = (item.dataset.group || '').toLowerCase();
                const visible = !q || label.includes(q) || group.includes(q);
                item.style.display = visible ? '' : 'none';
                if (visible) anyVisible = true;
            });

            // Show/hide group headers based on visibility of their items
            document.querySelectorAll('#returnedByItems > div:not(.rby-item):not(#rbyNoResult)').forEach(header => {
                const groupName = header.textContent.trim().toLowerCase();
                let hasVisible = false;
                items.forEach(item => {
                    if (item.dataset.group.toLowerCase() === groupName && item.style.display !== 'none') {
                        hasVisible = true;
                    }
                });
                header.style.display = hasVisible ? '' : 'none';
            });

            noResult.style.display = anyVisible ? 'none' : '';
        }

        // Open on focus / click
        searchInput.addEventListener('focus', function() {
            filterItems(this.value);
            openList();
        });

        searchInput.addEventListener('input', function() {
            hiddenInput.value = '';
            searchInput.style.color = 'var(--text-primary)';
            searchInput.setCustomValidity('Silakan pilih nama dari daftar yang tersedia.');
            filterItems(this.value);
            openList();
        });

        // Click item → select
        items.forEach(item => {
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                const label = this.dataset.label;
                const value = this.dataset.value;
                const group = this.dataset.group;

                searchInput.value = label + ' (' + group + ')';
                hiddenInput.value = value;
                searchInput.style.color = 'var(--accent-blue)';
                searchInput.setCustomValidity(''); // Mark valid
                closeList();
            });
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!document.getElementById('returnedByDropdown').contains(e.target)) {
                closeList();
                // If nothing was selected, clear the text
                if (!hiddenInput.value) {
                    searchInput.value = '';
                    searchInput.setCustomValidity('Bagian ini harus diisi.');
                }
            }
        });

        // Keyboard: Escape to close
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeList();
                this.blur();
            }
        });
    })();

</script>
@endsection
