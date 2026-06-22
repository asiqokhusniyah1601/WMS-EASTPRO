@extends('layouts.app')

<!--@yield('title', 'Web Receiving | DLMS')-->

@section('styles')
<style>
    /* ====== Web Receiving — Focus Layout (low cognitive load) ====== */
    .receiving-split { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; }
    @media (max-width: 1100px) { .receiving-split { grid-template-columns: 1fr; } }
    .receiving-sticky { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 20px; }

    /* Status feedback bar */
    .scan-status-bar {
        display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 12px;
        font-weight: 600; font-size: 14px; margin-bottom: 16px; border: 1px solid transparent; transition: all .25s ease;
    }
    .scan-status-bar i { font-size: 18px; }
    .scan-status-bar.idle    { background: rgba(59,130,246,0.10);  border-color: rgba(59,130,246,0.25);  color: var(--accent-blue); }
    .scan-status-bar.success { background: rgba(16,185,129,0.12);  border-color: rgba(16,185,129,0.30);  color: var(--accent-emerald); }
    .scan-status-bar.error   { background: rgba(239,68,68,0.12);   border-color: rgba(239,68,68,0.30);   color: var(--danger-color, #ef4444); }

    /* Distinct scan area */
    .scan-area-card { background: var(--bg-primary); border: 1px solid var(--border-color); }

    /* Kondisi toggle buttons */
    .cond-toggle { display: flex; gap: 10px; }
    .cond-btn {
        flex: 1; padding: 14px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-secondary);
        color: var(--text-secondary); font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center;
        gap: 8px; transition: all .2s ease; font-size: 14px;
    }
    .cond-btn:hover { border-color: var(--accent-blue); }
    .cond-btn.active[data-cond="BARU"]  { background: rgba(16,185,129,0.15); border-color: var(--accent-emerald); color: var(--accent-emerald); }
    .cond-btn.active[data-cond="BEKAS"] { background: rgba(245,158,11,0.15); border-color: var(--accent-amber);   color: var(--accent-amber); }

    /* Progressive disclosure toggle */
    .detail-toggle {
        width: 100%; display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px dashed var(--border-color);
        background: none; border-radius: 10px; cursor: pointer; color: var(--text-secondary); font-size: 13px; font-weight: 500;
    }
    .detail-toggle:hover { border-color: var(--accent-blue); color: var(--text-primary); }
    .detail-toggle .fa-chevron-down { transition: transform .2s ease; }
    .model-chip { font-size: 12px; padding: 3px 10px; border-radius: 20px; background: var(--bg-secondary); color: var(--text-muted); margin-left: auto; }
    .model-chip.set { background: rgba(59,130,246,0.15); color: var(--accent-blue); font-weight: 600; }

    /* Live counter */
    .live-counter-card { text-align: center; }
    .live-counter-num { font-size: 54px; font-weight: 700; line-height: 1.1; color: var(--accent-blue); margin: 4px 0; }

    /* Focus mode toolbar */
    .focus-toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
    .focus-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-color);
        background: var(--bg-secondary); color: var(--text-secondary); cursor: pointer; font-size: 13px; font-weight: 500; transition: all .2s ease;
    }
    .focus-btn:hover { border-color: var(--accent-blue); color: var(--text-primary); }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-file-import"
        iconColor="var(--accent-emerald)"
        title="Penerimaan Barang"
        subtitle="Terima perangkat, aksesoris, dan kartu GSM/SIM dari supplier ke dalam inventaris gudang." />

    <!-- Focus Mode Toolbar -->
    <div class="focus-toolbar">
        <button type="button" id="focusModeBtn" class="focus-btn" title="Sembunyikan sidebar untuk fokus kerja">
            <i class="fa-solid fa-expand"></i> Focus Mode
        </button>
    </div>

    <!-- Tabs Navigation -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <button class="btn btn-outline active-tab-btn" id="tabDeviceBtn" style="border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-primary); font-weight: 600;">
            <i class="fa-solid fa-mobile-screen" style="color: var(--accent-blue);"></i> 1. Penerimaan Device
        </button>
        <button class="btn btn-outline" id="tabAccessoryBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-secondary);">
            <i class="fa-solid fa-plug" style="color: var(--accent-orange);"></i> 2. Penerimaan Aksesoris
        </button>
        <button class="btn btn-outline" id="tabSimBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-secondary);">
            <i class="fa-solid fa-sim-card" style="color: var(--accent-indigo);"></i> 3. Penerimaan Kartu GSM
        </button>
    </div>

    <!-- Error Audio Alert Banner -->
    <div id="duplicateAlert" class="alert-box alert-danger animate-fade-in" style="display: none;">
        <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="alert-message">
            <strong>DUPLIKAT DETEKSI!</strong> Serial Number <span id="duplicateSnText"></span> sudah ada di draft scan atau terdaftar aktif di database.
        </div>
    </div>

    <!-- TAB 1: DEVICE RECEIVING -->
    <div id="panelDevice">
        <form id="receivingForm" action="{{ route('receiving.post') }}" method="POST">
            @csrf
            <div class="receiving-split">

                <!-- LEFT: Area Input (fokus utama) -->
                <div>
                    <!-- Status feedback bar (biru = menunggu, hijau = sukses, merah = error) -->
                    <div id="scanStatusBar" class="scan-status-bar idle">
                        <i class="fa-solid fa-barcode"></i>
                        <span id="scanStatusText">Siap menerima scan — arahkan scanner ke barcode lalu tembak.</span>
                    </div>

                    <!-- Scan area (kontras, menonjol) -->
                    <div class="card scan-area-card">
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label for="barcode_input" style="font-weight: 600; color: var(--accent-blue);">SCAN SERIAL NUMBER / IMEI</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" id="barcode_input" class="form-control scan-target-input" placeholder="Tembak barcode atau ketik SN lalu Enter..." style="padding-left: 52px; font-size: 18px; font-weight: 600; height: 58px; border-color: rgba(59, 130, 246, 0.4);">
                            </div>
                        </div>

                        <!-- AI Suggestion pills -->
                        <div id="quick_device_suggestions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 18px;">
                            <span style="font-size: 12px; color: var(--text-secondary); font-weight: 500;"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent-amber);"></i> AI Suggestion:</span>
                        </div>

                        <!-- Kondisi Unit — toggle besar (lebih cepat dari dropdown) -->
                        <div style="margin-bottom: 18px;">
                            <label style="display: block; margin-bottom: 8px;">Kondisi Unit</label>
                            <div class="cond-toggle">
                                <button type="button" class="cond-btn active" data-cond="BARU"><i class="fa-solid fa-wand-sparkles"></i> Baru</button>
                                <button type="button" class="cond-btn" data-cond="BEKAS"><i class="fa-solid fa-recycle"></i> Bekas</button>
                            </div>
                            <input type="hidden" id="scan_condition" value="BARU">
                        </div>

                        <!-- Progressive disclosure: detail Merk/Tipe/Model (disembunyikan default) -->
                        <div>
                            <button type="button" id="toggleDeviceDetail" class="detail-toggle">
                                <i class="fa-solid fa-sliders"></i>
                                <span>Detail perangkat (Merk / Tipe / Model)</span>
                                <span id="deviceModelChip" class="model-chip">Belum dipilih</span>
                                <i class="fa-solid fa-chevron-down" id="detailChevron"></i>
                            </button>
                            <div id="deviceDetailWrap" style="display: none; margin-top: 14px;">
                                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                                    <div class="form-group">
                                        <label for="scan_brand">Merk Perangkat</label>
                                        <select id="scan_brand" class="form-control">
                                            <option value="">-- Pilih Merk --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="scan_type">Tipe Perangkat</label>
                                        <select id="scan_type" class="form-control" disabled>
                                            <option value="">-- Pilih Tipe --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="scan_model">Model Perangkat</label>
                                        <select id="scan_model" class="form-control" disabled>
                                            <option value="">-- Pilih Model --</option>
                                        </select>
                                    </div>
                                </div>
                                <small style="color: var(--text-muted); display: block;">Tips: klik salah satu AI Suggestion di atas untuk mengisi otomatis.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Draft Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-list"></i>
                                <span>Draft List Penerimaan (<span id="draftCount">0</span> Item)</span>
                            </div>
                            <button type="button" id="clearDraftBtn" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">Hapus Semua</button>
                        </div>

                        <div class="table-wrapper">
                            <table class="table" id="draftTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Serial Number (SN)</th>
                                        <th>IMEI (Auto-Generated)</th>
                                        <th>Tipe</th>
                                        <th>Model</th>
                                        <th>Kondisi</th>
                                        <th style="text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="draftTableBody">
                                    <tr id="emptyRowPlaceholder">
                                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada perangkat yang di-scan. Silakan gunakan scanner atau simulator.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Ringkasan & Konfigurasi (sticky) -->
                <div>
                    <div class="receiving-sticky">
                        <!-- Live counter -->
                        <div class="card live-counter-card">
                            <div style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Total Ter-scan</div>
                            <div id="liveCounter" class="live-counter-num">0</div>
                            <div style="font-size: 12px; color: var(--text-muted);">unit siap difinalisasi ke stok</div>
                        </div>

                        <!-- Config & Submit -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fa-solid fa-gears"></i>
                                    <span>Konfigurasi Penerimaan</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="warehouse_select">Gudang Tujuan (Destination Warehouse)</label>
                                <select name="warehouse" id="warehouse_select" class="form-control" required>
                                    @foreach($warehouses as $key => $name)
                                        <option value="{{ $key }}" {{ $key == session('active_warehouse_code') ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- DO Upload & Komparasi -->
                            <div class="form-group">
                                <label>Unggah Berkas DO (Delivery Order / Excel) - Opsional</label>
                                <input type="file" id="do_file_input" accept=".csv,.xlsx,.xls" style="display:none;">
                                <label for="do_file_input" class="file-upload-box" id="doUploadBox" style="cursor:pointer; display:block;">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <p style="font-size: 13px; font-weight: 500;" id="doFileName">Pilih file Excel / CSV DO</p>
                                    <span style="font-size: 11px; color: var(--text-muted);">Sistem akan mencocokkan SN & jumlah dengan hasil scan.</span>
                                </label>
                                <div style="margin-top:6px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                    <a href="data:text/csv;charset=utf-8,serial_number%0ASN-001%0ASN-002%0ASN-003" download="template_do.csv" style="font-size:11px; color:var(--accent-blue);"><i class="fa-solid fa-download"></i> Template CSV</a>
                                    <button type="button" id="doClearBtn" style="display:none; font-size:11px; background:none; border:none; color:var(--danger-color); cursor:pointer; padding:0;"><i class="fa-solid fa-xmark"></i> Hapus DO</button>
                                </div>
                            </div>

                            <!-- Hasil Komparasi DO vs Scan -->
                            <div id="doComparePanel" style="display:none; margin-bottom:16px; border:1px solid var(--border-color); border-radius:8px; padding:12px; background:var(--bg-primary);">
                                <div style="font-size:13px; font-weight:600; margin-bottom:10px;"><i class="fa-solid fa-clipboard-check"></i> Komparasi DO vs Scan</div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; margin-bottom:10px;">
                                    <div style="font-size:12px;">Total DO: <strong id="doTotal">0</strong></div>
                                    <div style="font-size:12px;">Total Scan: <strong id="doScanTotal">0</strong></div>
                                    <div style="font-size:12px; color:var(--accent-emerald);">Cocok: <strong id="doMatch">0</strong></div>
                                    <div style="font-size:12px; color:var(--accent-amber);">Belum discan: <strong id="doMissing">0</strong></div>
                                    <div style="font-size:12px; color:var(--danger-color); grid-column:span 2;">Tidak ada di DO (ekstra): <strong id="doExtra">0</strong></div>
                                </div>
                                <div id="doMatchBadge" style="font-size:12px; font-weight:600; padding:8px 10px; border-radius:6px; text-align:center;"></div>

                                <div id="doMissingWrap" style="display:none; margin-top:10px;">
                                    <div style="font-size:11px; color:var(--accent-amber); font-weight:600; margin-bottom:4px;">SN di DO tapi belum discan:</div>
                                    <div id="doMissingList" style="max-height:130px; overflow:auto; font-size:12px; line-height:1.7;"></div>
                                </div>
                                <div id="doExtraWrap" style="display:none; margin-top:10px;">
                                    <div style="font-size:11px; color:var(--danger-color); font-weight:600; margin-bottom:4px;">SN discan tapi tidak ada di DO:</div>
                                    <div id="doExtraList" style="max-height:130px; overflow:auto; font-size:12px; line-height:1.7;"></div>
                                </div>
                            </div>

                            <div style="margin-top: 24px;">
                                <button type="submit" id="submitBtn" class="btn btn-success" style="width: 100%; justify-content: center; padding: 14px;" disabled>
                                    <i class="fa-solid fa-circle-check"></i> Submit & Finalize ke Stock
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- TAB 2: ACCESSORY RECEIVING -->
    <div id="panelAccessory" style="display: none;">
        <form id="receivingAccessoryForm" action="{{ route('receiving.accessory.post') }}" method="POST">
            @csrf
            <div class="receiving-split">
                
                <!-- Left Side: Accessory Selection & Quantities -->
                <div>
                    <!-- Search Input & Autocomplete Area -->
                    <div class="card" style="padding: 20px; margin-bottom: 24px; overflow: visible;">
                        <div class="form-group" style="position: relative; margin-bottom: 12px;">
                            <label for="acc_search_input" style="font-weight: 600; color: var(--accent-orange);">CARI NAMA ATAU KODE AKSESORIS</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 15px; color: var(--text-muted);"></i>
                                <input type="text" id="acc_search_input" class="form-control" placeholder="Ketik nama atau kode aksesoris..." style="padding-left: 48px; height: 48px; border-color: rgba(249, 115, 22, 0.4);" autocomplete="off">
                            </div>
                            <!-- Autocomplete results dropdown -->
                            <div id="acc_autocomplete_list" style="position: absolute; left: 0; right: 0; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); z-index: 1000; display: none; max-height: 250px; overflow-y: auto; margin-top: 4px;">
                            </div>
                        </div>

                        <!-- Quick Suggestions (AI-Driven) -->
                        @if(!empty($suggestedAccessories))
                            <div class="ai-suggestion-container">
                                <span class="ai-suggestion-title"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Suggestion:</span>
                                @foreach($suggestedAccessories as $acc)
                                    <button type="button" class="ai-pill-btn quick-acc-btn" data-code="{{ $acc['code'] }}" data-name="{{ $acc['name'] }}">
                                        <i class="fa-solid fa-plus"></i> {{ $acc['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Draft Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-plug"></i>
                                <span>Draft Aksesoris yang Diterima</span>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table" id="accDraftTable">
                                <thead>
                                    <tr>
                                        <th>Kode Aksesoris</th>
                                        <th>Nama Aksesoris</th>
                                        <th style="width: 150px; text-align: center;">Jumlah Diterima (Qty)</th>
                                        <th style="text-align: right; width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="accDraftTableBody">
                                    <tr id="emptyAccRowPlaceholder">
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 35px;">Belum ada aksesoris yang dipilih. Cari aksesoris di atas atau gunakan tombol Saran Cepat.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Config & Submit (sticky) -->
                <div>
                    <div class="receiving-sticky">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-gears"></i>
                                <span>Konfigurasi Penerimaan Aksesoris</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="warehouse_select_acc">Gudang Tujuan (Destination Warehouse)</label>
                            <select name="warehouse" id="warehouse_select_acc" class="form-control" required>
                                @foreach($warehouses as $key => $name)
                                    <option value="{{ $key }}" {{ $key == session('active_warehouse_code') ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- DO Upload & Komparasi Qty -->
                        <div class="form-group">
                            <label>Unggah Berkas DO (Excel / CSV) - Opsional</label>
                            <input type="file" id="acc_do_file_input" accept=".csv,.xlsx,.xls" style="display:none;">
                            <label for="acc_do_file_input" class="file-upload-box" style="cursor:pointer; display:block;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p style="font-size: 13px; font-weight: 500;" id="accDoFileName">Pilih file Excel / CSV DO</p>
                                <span style="font-size: 11px; color: var(--text-muted);">Cocokkan qty per kode aksesoris.</span>
                            </label>
                            <div style="margin-top:6px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                <a href="data:text/csv;charset=utf-8,code,qty%0AACC-001,10%0AACC-002,5" download="template_do_aksesoris.csv" style="font-size:11px; color:var(--accent-blue);"><i class="fa-solid fa-download"></i> Template CSV</a>
                                <button type="button" id="accDoClearBtn" style="display:none; font-size:11px; background:none; border:none; color:var(--danger-color); cursor:pointer; padding:0;"><i class="fa-solid fa-xmark"></i> Hapus DO</button>
                            </div>
                        </div>

                        <div id="accDoComparePanel" style="display:none; margin-bottom:16px; border:1px solid var(--border-color); border-radius:8px; padding:12px; background:var(--bg-primary);">
                            <div style="font-size:13px; font-weight:600; margin-bottom:10px;"><i class="fa-solid fa-clipboard-check"></i> Komparasi DO vs Penerimaan</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; margin-bottom:10px;">
                                <div style="font-size:12px;">Total Qty DO: <strong id="accDoTotal">0</strong></div>
                                <div style="font-size:12px;">Total Qty Scan: <strong id="accDoScanTotal">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-emerald);">Kode cocok: <strong id="accDoOk">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-amber);">Kode beda: <strong id="accDoDiff">0</strong></div>
                            </div>
                            <div id="accDoMatchBadge" style="font-size:12px; font-weight:600; padding:8px 10px; border-radius:6px; text-align:center; margin-bottom:10px;"></div>
                            <div class="table-wrapper" style="max-height:200px; overflow:auto;">
                                <table class="table" style="font-size:12px;">
                                    <thead><tr><th>Kode</th><th style="text-align:center;">DO</th><th style="text-align:center;">Scan</th><th style="text-align:center;">Status</th></tr></thead>
                                    <tbody id="accDoCompareBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div style="margin-top: 24px;">
                            <button type="submit" id="submitAccBtn" class="btn btn-success" style="width: 100%; justify-content: center; padding: 14px;" disabled>
                                <i class="fa-solid fa-circle-check"></i> Submit & Tambah ke Stok Aksesoris
                            </button>
                        </div>
                    </div>
                    </div><!-- /receiving-sticky -->
                </div>

            </div>
        </form>
    </div>

    <!-- TAB 3: GSM / SIM RECEIVING -->
    <div id="panelSim" style="display: none;">
        <form id="receivingSimForm" action="{{ route('receiving.simcard.post') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="receiving-split">

                <!-- Left: 3 input modes -->
                <div>
                    <!-- Mode A: Pool select (search + checkbox) -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-list-check"></i> <span>A. Pilih dari Pool SIM (belum punya gudang)</span></div>
                            <span class="badge badge-info" id="simPoolSelectedBadge">0 dipilih</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 13px; color: var(--text-muted);"></i>
                                <input type="text" id="sim_pool_search" class="form-control" placeholder="Cari MSISDN atau provider..." style="padding-left: 44px;" autocomplete="off">
                            </div>
                        </div>

                        <div class="table-wrapper" style="max-height: 320px; overflow-y: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" id="sim_pool_check_all"></th>
                                        <th>MSISDN</th>
                                        <th>Provider</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody id="simPoolBody">
                                    @forelse($poolSimcards as $sim)
                                        <tr class="sim-pool-row" data-msisdn="{{ $sim['msisdn'] }}" data-search="{{ strtolower($sim['msisdn'] . ' ' . $sim['provider']) }}">
                                            <td><input type="checkbox" class="sim-pool-check" name="sim_ids[]" value="{{ $sim['id'] }}"></td>
                                            <td style="font-weight: 600; color: var(--accent-indigo);">{{ $sim['msisdn'] }}</td>
                                            <td>{{ $sim['provider'] }}</td>
                                            <td>{{ $sim['category'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <x-empty-state colspan="4" icon="fa-sim-card"
                                            title="Pool SIM kosong"
                                            message="Tidak ada SIM tanpa gudang. Tambahkan via input manual / CSV di bawah, atau di Master Data." />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mode B: Manual / scan rows -->
                    <div class="card" style="margin-bottom: 24px; overflow: visible;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-keyboard"></i> <span>B. Input Manual / Scan MSISDN</span></div>
                            <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="addSimRow()"><i class="fa-solid fa-plus"></i> Tambah Baris</button>
                        </div>
                        <div class="form-group" style="margin-bottom: 12px;">
                            <input type="text" id="sim_scan_input" class="form-control" placeholder="Scan / ketik MSISDN lalu Enter..." autocomplete="off">
                        </div>
                        <div class="table-wrapper">
                            <table class="table" id="simManualTable">
                                <thead>
                                    <tr><th>MSISDN</th><th style="width: 160px;">Provider</th><th style="width: 140px;">Kategori</th><th style="width: 60px; text-align:right;">Aksi</th></tr>
                                </thead>
                                <tbody id="simManualBody">
                                    <tr id="simManualEmpty"><td colspan="4" style="text-align:center; color: var(--text-muted); padding: 24px;">Belum ada MSISDN. Scan atau klik "Tambah Baris".</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mode C: Bulk CSV -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-file-csv"></i> <span>C. Bulk Upload CSV</span></div>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                            Format kolom: <code>msisdn, provider, category</code> (baris pertama header).
                            <a href="data:text/csv;charset=utf-8,msisdn,provider,category%0A6281200000001,Telkomsel,Data%0A6281200000002,XL,Data" download="template_simcard.csv" style="color: var(--accent-blue);"><i class="fa-solid fa-download"></i> Download template</a>
                        </p>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv">
                    </div>
                </div>

                <!-- Right: config & submit (sticky) -->
                <div>
                    <div class="receiving-sticky">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-gears"></i> <span>Konfigurasi Penerimaan SIM</span></div>
                        </div>
                        <div class="form-group">
                            <label for="warehouse_select_sim">Gudang Tujuan</label>
                            <select name="warehouse" id="warehouse_select_sim" class="form-control" required>
                                @foreach($warehouses as $key => $name)
                                    <option value="{{ $key }}" {{ $key == session('active_warehouse_code') ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            <small style="color: var(--text-muted); display:block; margin-top:6px;">Semua SIM yang dipilih/diisi/diunggah akan diterima ke gudang ini.</small>
                        </div>

                        <!-- DO Upload & Komparasi MSISDN -->
                        <div class="form-group">
                            <label>Unggah Berkas DO (Excel / CSV) - Opsional</label>
                            <input type="file" id="sim_do_file_input" accept=".csv,.xlsx,.xls" style="display:none;">
                            <label for="sim_do_file_input" class="file-upload-box" style="cursor:pointer; display:block;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p style="font-size: 13px; font-weight: 500;" id="simDoFileName">Pilih file Excel / CSV DO</p>
                                <span style="font-size: 11px; color: var(--text-muted);">Cocokkan MSISDN dengan yang dipilih/diisi.</span>
                            </label>
                            <div style="margin-top:6px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                <a href="data:text/csv;charset=utf-8,msisdn%0A6281200000001%0A6281200000002" download="template_do_gsm.csv" style="font-size:11px; color:var(--accent-blue);"><i class="fa-solid fa-download"></i> Template CSV</a>
                                <button type="button" id="simDoClearBtn" style="display:none; font-size:11px; background:none; border:none; color:var(--danger-color); cursor:pointer; padding:0;"><i class="fa-solid fa-xmark"></i> Hapus DO</button>
                            </div>
                        </div>

                        <div id="simDoComparePanel" style="display:none; margin-bottom:16px; border:1px solid var(--border-color); border-radius:8px; padding:12px; background:var(--bg-primary);">
                            <div style="font-size:13px; font-weight:600; margin-bottom:10px;"><i class="fa-solid fa-clipboard-check"></i> Komparasi DO vs Pilihan</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; margin-bottom:10px;">
                                <div style="font-size:12px;">Total DO: <strong id="simDoTotal">0</strong></div>
                                <div style="font-size:12px;">Total Dipilih: <strong id="simDoSelTotal">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-emerald);">Cocok: <strong id="simDoMatch">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-amber);">Belum dipilih: <strong id="simDoMissing">0</strong></div>
                                <div style="font-size:12px; color:var(--danger-color); grid-column:span 2;">Ekstra (tak ada di DO): <strong id="simDoExtra">0</strong></div>
                            </div>
                            <div id="simDoMatchBadge" style="font-size:12px; font-weight:600; padding:8px 10px; border-radius:6px; text-align:center;"></div>
                            <div id="simDoMissingWrap" style="display:none; margin-top:10px;">
                                <div style="font-size:11px; color:var(--accent-amber); font-weight:600; margin-bottom:4px;">MSISDN di DO tapi belum dipilih:</div>
                                <div id="simDoMissingList" style="max-height:130px; overflow:auto; font-size:12px; line-height:1.7;"></div>
                            </div>
                        </div>

                        <div style="margin-top: 24px;">
                            <button type="submit" class="btn btn-success" style="width: 100%; justify-content: center; padding: 14px;">
                                <i class="fa-solid fa-circle-check"></i> Submit & Terima SIM ke Stok
                            </button>
                        </div>
                    </div>
                    </div><!-- /receiving-sticky -->
                </div>

            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    // List of already registered SNs in DB from session to prevent DB duplicate scanning
    const dbSns = @json(collect(session('devices', []))->pluck('serial_number')->toArray());
    
    // Master data device models
    const deviceModelsData = @json($deviceModels ?? []);

    // Suggested device models (AI Suggestions)
    const suggestedDevices = @json($suggestedDevices ?? []);

    // Master data accessories
    const accessoriesListData = @json($accessories ?? []);

    // SIM providers for manual entry dropdown
    const simProvidersData = @json($simProviders ?? []);
    
    // Set of currently scanned SNs in this session
    const scanDraftSns = new Set();
    let scanCount = 0;

    // Set of selected accessory codes
    const selectedAccessoryCodes = new Set();

    let activeTab = 'device';

    const barcodeInput = document.getElementById('barcode_input');
    const receivingForm = document.getElementById('receivingForm');
    const draftTableBody = document.getElementById('draftTableBody');
    const emptyRowPlaceholder = document.getElementById('emptyRowPlaceholder');
    const draftCountSpan = document.getElementById('draftCount');
    const submitBtn = document.getElementById('submitBtn');
    const duplicateAlert = document.getElementById('duplicateAlert');
    const duplicateSnText = document.getElementById('duplicateSnText');

    // UI Tab toggle
    const tabDeviceBtn = document.getElementById('tabDeviceBtn');
    const tabAccessoryBtn = document.getElementById('tabAccessoryBtn');
    const tabSimBtn = document.getElementById('tabSimBtn');
    const panelDevice = document.getElementById('panelDevice');
    const panelAccessory = document.getElementById('panelAccessory');
    const panelSim = document.getElementById('panelSim');
    const emulatorTarget = document.getElementById('emulatorTarget');

    function resetTabButtons() {
        [tabDeviceBtn, tabAccessoryBtn, tabSimBtn].forEach(b => {
            if (!b) return;
            b.style.borderBottomColor = 'transparent';
            b.style.color = 'var(--text-secondary)';
            b.style.fontWeight = 'normal';
        });
        if (panelDevice) panelDevice.style.display = 'none';
        if (panelAccessory) panelAccessory.style.display = 'none';
        if (panelSim) panelSim.style.display = 'none';
    }

    tabDeviceBtn.addEventListener('click', () => {
        activeTab = 'device';
        resetTabButtons();
        tabDeviceBtn.style.borderBottomColor = 'var(--accent-blue)';
        tabDeviceBtn.style.color = 'var(--text-primary)';
        tabDeviceBtn.style.fontWeight = '600';
        panelDevice.style.display = 'block';
        if (emulatorTarget) {
            emulatorTarget.value = '#barcode_input';
        }
        focusInput();
    });

    tabAccessoryBtn.addEventListener('click', () => {
        activeTab = 'accessory';
        resetTabButtons();
        tabAccessoryBtn.style.borderBottomColor = 'var(--accent-orange)';
        tabAccessoryBtn.style.color = 'var(--text-primary)';
        tabAccessoryBtn.style.fontWeight = '600';
        panelAccessory.style.display = 'block';
        if (emulatorTarget) {
            emulatorTarget.value = ''; // Disable barcode scan emulator auto-focusing here
        }
        const accSearch = document.getElementById('acc_search_input');
        if (accSearch) {
            accSearch.focus();
        }
    });

    if (tabSimBtn) {
        tabSimBtn.addEventListener('click', () => {
            activeTab = 'sim';
            resetTabButtons();
            tabSimBtn.style.borderBottomColor = 'var(--accent-indigo)';
            tabSimBtn.style.color = 'var(--text-primary)';
            tabSimBtn.style.fontWeight = '600';
            panelSim.style.display = 'block';
            if (emulatorTarget) emulatorTarget.value = '';
            const simScan = document.getElementById('sim_scan_input');
            if (simScan) simScan.focus();
        });
    }

    // Persistensi tab: setelah simpan, buka kembali tab terkait (?tab=device|accessory|simcard).
    (function restoreActiveTab() {
        const urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab === 'accessory') {
            tabAccessoryBtn.click();
        } else if (urlTab === 'simcard' && tabSimBtn) {
            tabSimBtn.click();
        }
        // 'device' adalah default; tidak perlu aksi.
    })();

    // Auto-focus logic
    function focusInput() {
        if (activeTab === 'device' && barcodeInput) {
            barcodeInput.focus();
        }
    }

    // Auto focus on page load
    window.addEventListener('DOMContentLoaded', () => {
        focusInput();
        
        // Initialize Brand Dropdown
        const scanBrand = document.getElementById('scan_brand');
        const scanType = document.getElementById('scan_type');
        const scanModel = document.getElementById('scan_model');

        if(deviceModelsData.length > 0) {
            const brands = [...new Set(deviceModelsData.map(item => item.brand))];
            brands.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand;
                option.innerText = brand;
                scanBrand.appendChild(option);
            });

            // Populate Quick Device Suggestions (AI-Driven)
            const quickDeviceDiv = document.getElementById('quick_device_suggestions');
            if (quickDeviceDiv && suggestedDevices.length > 0) {
                suggestedDevices.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-outline quick-device-btn';
                    btn.style.padding = '4px 10px';
                    btn.style.fontSize = '11px';
                    btn.style.borderRadius = '20px';
                    btn.style.background = 'none';
                    btn.style.borderColor = 'var(--accent-blue)';
                    btn.style.color = 'var(--accent-blue)';
                    btn.style.cursor = 'pointer';
                    btn.style.display = 'inline-flex';
                    btn.style.alignItems = 'center';
                    btn.style.gap = '4px';
                    btn.innerHTML = `<i class="fa-solid fa-plus-circle"></i> ${item.brand} - ${item.model}`;

                    btn.addEventListener('click', function() {
                        scanBrand.value = item.brand;
                        scanBrand.dispatchEvent(new Event('change'));

                        scanType.value = item.type;
                        scanType.dispatchEvent(new Event('change'));

                        scanModel.value = item.model;
                        scanModel.dispatchEvent(new Event('change'));
                        
                        // Focus back to barcode scanner
                        focusInput();
                    });
                    quickDeviceDiv.appendChild(btn);
                });
            }

            scanBrand.addEventListener('change', function() {
                scanType.innerHTML = '<option value="">-- Pilih Tipe --</option>';
                scanModel.innerHTML = '<option value="">-- Pilih Model --</option>';
                scanType.disabled = true;
                scanModel.disabled = true;

                if (this.value) {
                    const types = [...new Set(deviceModelsData.filter(item => item.brand === this.value).map(item => item.type))];
                    types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type;
                        option.innerText = type;
                        scanType.appendChild(option);
                    });
                    scanType.disabled = false;
                }
            });

            scanType.addEventListener('change', function() {
                scanModel.innerHTML = '<option value="">-- Pilih Model --</option>';
                scanModel.disabled = true;

                if (this.value) {
                    const models = deviceModelsData.filter(item => item.brand === scanBrand.value && item.type === this.value).map(item => item.model);
                    models.forEach(model => {
                        const option = document.createElement('option');
                        option.value = model;
                        option.innerText = model;
                        scanModel.appendChild(option);
                    });
                    scanModel.disabled = false;
                }
            });
        }
    });

    // Enforce focus - if user clicks elsewhere, return focus to scanner input
    document.addEventListener('click', (e) => {
        // Keep focus unless clicking on other input fields or select dropdowns
        if (e.target.closest('#panelAccessory')) {
            return; // Don't enforce scanner focus when interacting inside accessory tab
        }
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'OPTION' && e.target.tagName !== 'TEXTAREA' && !e.target.closest('.scanner-emulator')) {
            focusInput();
        }
    });

    // Scanner keypress detection (Catch Enter Key Event)
    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Stop form submission
                
                const rawSn = this.value.trim();
                if (rawSn !== '') {
                    processScan(rawSn);
                }
                
                // Auto-clear and re-focus
                this.value = '';
                focusInput();
            }
        });
    }

    function processScan(sn) {
        // Hide previous alert
        duplicateAlert.style.display = 'none';

        // 1. Duplicate check in current draft session
        if (scanDraftSns.has(sn)) {
            triggerDuplicateError(sn);
            return;
        }

        // 2. Duplicate check in database session
        if (dbSns.includes(sn)) {
            triggerDuplicateError(sn);
            return;
        }

        // Add to draft list
        scanDraftSns.add(sn);
        scanCount++;

        // Remove empty state placeholder
        if (emptyRowPlaceholder) {
            emptyRowPlaceholder.style.display = 'none';
        }

        // Generate mock IMEI & other info
        const mockImei = '35' + Math.floor(1000000000000 + Math.random() * 9000000000000);
        const deviceType = document.getElementById('scan_type').value || 'N/A';
        const deviceModel = document.getElementById('scan_model').value || 'N/A';
        const deviceCondition = (document.getElementById('scan_condition') || {}).value === 'BEKAS' ? 'BEKAS' : 'BARU';
        const condBadgeClass = deviceCondition === 'BEKAS' ? 'badge-warning' : 'badge-success';

        // Play success beep
        if (window.playBeep) {
            window.playBeep('success');
        }

        // Create table row
        const newRow = document.createElement('tr');
        newRow.setAttribute('id', `row-${sn}`);
        newRow.className = 'animate-fade-in';
        newRow.innerHTML = `
            <td>${scanCount}</td>
            <td style="font-weight:600; color:var(--accent-blue);">${sn}
                <input type="hidden" name="sns[]" value="${sn}">
            </td>
            <td>${mockImei}
                <input type="hidden" name="imeis[]" value="${mockImei}">
            </td>
            <td><span class="badge badge-info">${deviceType}</span>
                <input type="hidden" name="types[]" value="${deviceType}">
            </td>
            <td>${deviceModel}
                <input type="hidden" name="models[]" value="${deviceModel}">
            </td>
            <td><span class="badge ${condBadgeClass}">${deviceCondition}</span>
                <input type="hidden" name="conditions[]" value="${deviceCondition}">
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeScanRow('${sn}')" style="padding:4px 8px; font-size:11px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        draftTableBody.appendChild(newRow);

        // Update count & button status
        draftCountSpan.innerText = scanCount;
        submitBtn.disabled = false;

        // Status feedback (hijau = sukses)
        if (window.setScanStatus) {
            window.setScanStatus('success', 'Tersimpan: ' + sn + ' — total ' + scanCount + ' unit.');
        }
    }

    function triggerDuplicateError(sn) {
        // Play error warning beep
        if (window.playBeep) {
            window.playBeep('error');
        }

        // Status feedback (merah = error)
        if (window.setScanStatus) {
            window.setScanStatus('error', 'Duplikat ditolak: ' + sn + ' sudah ada.');
        }

        // Show banner alert
        duplicateSnText.innerText = sn;
        duplicateAlert.style.display = 'flex';
        
        // Auto scroll to alert
        duplicateAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Remove row function
    window.removeScanRow = function(sn) {
        const row = document.getElementById(`row-${sn}`);
        if (row) {
            row.remove();
            scanDraftSns.delete(sn);
            scanCount--;
            draftCountSpan.innerText = scanCount;
            
            if (scanCount === 0) {
                if (emptyRowPlaceholder) {
                    emptyRowPlaceholder.style.display = 'table-row';
                }
                submitBtn.disabled = true;
            }
            
            // Re-sequence the numbers
            const rows = draftTableBody.querySelectorAll('tr:not(#emptyRowPlaceholder)');
            rows.forEach((r, idx) => {
                r.cells[0].innerText = idx + 1;
            });
        }
        focusInput();
    }

    // Clear all draft rows
    document.getElementById('clearDraftBtn').addEventListener('click', () => {
        const rows = draftTableBody.querySelectorAll('tr:not(#emptyRowPlaceholder)');
        rows.forEach(r => r.remove());
        scanDraftSns.clear();
        scanCount = 0;
        draftCountSpan.innerText = 0;
        if (emptyRowPlaceholder) {
            emptyRowPlaceholder.style.display = 'table-row';
        }
        submitBtn.disabled = true;
        duplicateAlert.style.display = 'none';
        if (window.setScanStatus) window.setScanStatus('idle', 'Draft dibersihkan — siap menerima scan baru.');
        focusInput();
    });

    // ============================================
    // UI/UX HELPERS: status bar, kondisi toggle,
    // progressive disclosure, live counter, focus mode
    // ============================================

    // Status feedback bar (idle / success / error)
    window.setScanStatus = function (state, msg) {
        const bar = document.getElementById('scanStatusBar');
        const txt = document.getElementById('scanStatusText');
        if (!bar) return;
        bar.classList.remove('idle', 'success', 'error');
        bar.classList.add(state);
        const iconEl = bar.querySelector('i');
        if (iconEl) {
            const icon = state === 'success' ? 'fa-circle-check' : (state === 'error' ? 'fa-circle-xmark' : 'fa-barcode');
            iconEl.className = 'fa-solid ' + icon;
        }
        if (txt && msg) txt.textContent = msg;
    };

    // Kondisi Unit toggle buttons -> hidden #scan_condition
    document.querySelectorAll('.cond-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cond-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const inp = document.getElementById('scan_condition');
            if (inp) inp.value = this.dataset.cond;
            focusInput();
        });
    });

    // Progressive disclosure: detail Merk/Tipe/Model
    const toggleDeviceDetail = document.getElementById('toggleDeviceDetail');
    const deviceDetailWrap = document.getElementById('deviceDetailWrap');
    const detailChevron = document.getElementById('detailChevron');
    if (toggleDeviceDetail && deviceDetailWrap) {
        toggleDeviceDetail.addEventListener('click', () => {
            const show = deviceDetailWrap.style.display === 'none';
            deviceDetailWrap.style.display = show ? 'block' : 'none';
            if (detailChevron) detailChevron.style.transform = show ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    // Selected-model chip (auto-fill summary)
    window.updateDeviceModelChip = function () {
        const chip = document.getElementById('deviceModelChip');
        if (!chip) return;
        const b = (document.getElementById('scan_brand') || {}).value || '';
        const t = (document.getElementById('scan_type') || {}).value || '';
        const m = (document.getElementById('scan_model') || {}).value || '';
        if (b || t || m) {
            chip.textContent = [b, t, m].filter(Boolean).join(' · ');
            chip.classList.add('set');
        } else {
            chip.textContent = 'Belum dipilih';
            chip.classList.remove('set');
        }
    };
    ['scan_brand', 'scan_type', 'scan_model'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', window.updateDeviceModelChip);
    });

    // Live counter mirrors #draftCount into the sticky panel
    (function mirrorDraftCount() {
        const dc = document.getElementById('draftCount');
        const lc = document.getElementById('liveCounter');
        if (dc && lc) {
            new MutationObserver(() => { lc.textContent = dc.textContent; })
                .observe(dc, { childList: true, characterData: true, subtree: true });
        }
    })();

    // Focus Mode: collapse the main sidebar (reuses layout's .collapsed state)
    const focusModeBtn = document.getElementById('focusModeBtn');
    if (focusModeBtn) {
        const sb = document.getElementById('sidebar');
        const syncFocusLabel = () => {
            const collapsed = sb && sb.classList.contains('collapsed');
            focusModeBtn.innerHTML = collapsed
                ? '<i class="fa-solid fa-compress"></i> Keluar Focus Mode'
                : '<i class="fa-solid fa-expand"></i> Focus Mode';
        };
        focusModeBtn.addEventListener('click', () => {
            if (!sb) return;
            sb.classList.toggle('collapsed');
            localStorage.setItem('sidebarState', sb.classList.contains('collapsed') ? 'collapsed' : 'expanded');
            syncFocusLabel();
        });
        syncFocusLabel();
    }

    // ============================================
    // ACCESSORIES DYNAMIC AUTOCOMPLETE & SUGGESTIONS
    // ============================================
    const accSearchInput = document.getElementById('acc_search_input');
    const accAutocompleteList = document.getElementById('acc_autocomplete_list');
    const accDraftTableBody = document.getElementById('accDraftTableBody');
    const emptyAccRowPlaceholder = document.getElementById('emptyAccRowPlaceholder');
    const submitAccBtn = document.getElementById('submitAccBtn');

    // Add accessory to draft list
    function addAccessoryToDraft(code, name) {
        if (selectedAccessoryCodes.has(code)) {
            // Already in draft, just focus the quantity input and increment it
            const qtyInput = document.getElementById(`acc-qty-${code}`);
            if (qtyInput) {
                qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                qtyInput.focus();
                qtyInput.select();
            }
            if (window.playBeep) window.playBeep('success');
            checkAccFormValidity();
            return;
        }

        // Add to tracking set
        selectedAccessoryCodes.add(code);

        // Hide empty state placeholder
        if (emptyAccRowPlaceholder) {
            emptyAccRowPlaceholder.style.display = 'none';
        }

        // Create table row
        const row = document.createElement('tr');
        row.setAttribute('id', `acc-row-${code}`);
        row.className = 'animate-fade-in';
        row.innerHTML = `
            <td style="font-weight: 600; color: var(--text-primary);">${code}</td>
            <td>${name}</td>
            <td style="text-align: center;">
                <input type="hidden" name="acc_types[]" value="${code}">
                <input type="number" id="acc-qty-${code}" name="acc_qtys[]" class="form-control acc-qty-input" min="1" value="1" style="width: 100px; text-align: center; margin: 0 auto;" required>
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeAccRow('${code}')" style="padding:4px 8px; font-size:11px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        accDraftTableBody.appendChild(row);

        // Focus the newly added input
        const newQtyInput = document.getElementById(`acc-qty-${code}`);
        if (newQtyInput) {
            newQtyInput.focus();
            newQtyInput.select();

            // Bind check on change
            newQtyInput.addEventListener('input', checkAccFormValidity);
            newQtyInput.addEventListener('change', checkAccFormValidity);
        }

        if (window.playBeep) window.playBeep('success');
        checkAccFormValidity();
    }

    // Remove accessory row
    window.removeAccRow = function(code) {
        const row = document.getElementById(`acc-row-${code}`);
        if (row) {
            row.remove();
            selectedAccessoryCodes.delete(code);
            
            if (selectedAccessoryCodes.size === 0) {
                if (emptyAccRowPlaceholder) {
                    emptyAccRowPlaceholder.style.display = 'table-row';
                }
            }
            if (window.playBeep) window.playBeep('error');
            checkAccFormValidity();
        }
        if (accSearchInput) {
            accSearchInput.focus();
        }
    }

    // Autocomplete keyboard navigation variables
    let activeIndex = -1;

    // Autocomplete input logic
    if (accSearchInput) {
        accSearchInput.addEventListener('keydown', function(e) {
            const items = accAutocompleteList.querySelectorAll('.autocomplete-item');
            if (!items.length || accAutocompleteList.style.display === 'none') return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex > -1 && items[activeIndex]) {
                    items[activeIndex].click();
                } else if (items.length > 0) {
                    items[0].click();
                }
            } else if (e.key === 'Escape') {
                accAutocompleteList.style.display = 'none';
            }
        });

        function updateActiveItem(items) {
            items.forEach((item, idx) => {
                if (idx === activeIndex) {
                    item.classList.add('active');
                    item.style.background = 'rgba(249, 115, 22, 0.15)';
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    item.classList.remove('active');
                    item.style.background = 'none';
                }
            });
        }

        accSearchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            accAutocompleteList.innerHTML = '';
            activeIndex = -1;

            if (query === '') {
                accAutocompleteList.style.display = 'none';
                return;
            }

            const matches = accessoriesListData.filter(item => 
                item.name.toLowerCase().includes(query) || 
                item.code.toLowerCase().includes(query)
            );

            if (matches.length === 0) {
                const noResult = document.createElement('div');
                noResult.style.padding = '12px 16px';
                noResult.style.color = 'var(--text-muted)';
                noResult.style.fontSize = '13px';
                noResult.innerText = 'Aksesoris tidak ditemukan.';
                accAutocompleteList.appendChild(noResult);
            } else {
                matches.forEach((item, index) => {
                    const btn = document.createElement('div');
                    btn.style.padding = '12px 16px';
                    btn.style.cursor = 'pointer';
                    btn.style.borderBottom = '1px solid var(--border-color)';
                    btn.style.fontSize = '14px';
                    btn.style.transition = 'background 0.2s';
                    btn.className = 'autocomplete-item';
                    btn.setAttribute('data-index', index);
                    
                    // Highlight matching substring
                    const highlightedName = item.name.replace(new RegExp(`(${query})`, 'gi'), '<strong>$1</strong>');
                    const highlightedCode = item.code.replace(new RegExp(`(${query})`, 'gi'), '<strong>$1</strong>');

                    btn.innerHTML = `
                        <div style="font-weight: 600; color: var(--accent-indigo);">${highlightedCode}</div>
                        <div style="color: var(--text-primary); font-size: 13px;">${highlightedName}</div>
                    `;

                    // Hover effect styling
                    btn.addEventListener('mouseenter', () => {
                        activeIndex = index;
                        updateActiveItem(accAutocompleteList.querySelectorAll('.autocomplete-item'));
                    });

                    btn.addEventListener('click', () => {
                        addAccessoryToDraft(item.code, item.name);
                        accSearchInput.value = '';
                        accAutocompleteList.style.display = 'none';
                    });

                    accAutocompleteList.appendChild(btn);
                });
            }
            accAutocompleteList.style.display = 'block';
        });

        // Hide list on click outside
        document.addEventListener('click', function(e) {
            if (accSearchInput && !accSearchInput.contains(e.target) && !accAutocompleteList.contains(e.target)) {
                accAutocompleteList.style.display = 'none';
            }
        });
    }

    // Suggestions button events
    const quickAccBtns = document.querySelectorAll('.quick-acc-btn');
    quickAccBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            const name = this.getAttribute('data-name');
            addAccessoryToDraft(code, name);
        });
    });

    // Check submit button validity
    function checkAccFormValidity() {
        let totalQty = 0;
        const inputs = accDraftTableBody.querySelectorAll('.acc-qty-input');
        inputs.forEach(input => {
            totalQty += parseInt(input.value || 0);
        });
        submitAccBtn.disabled = (totalQty <= 0 || selectedAccessoryCodes.size === 0);
    }

    // ============================================
    // GSM / SIM RECEIVING TAB
    // ============================================
    // -- Mode A: pool search + checkbox + select-all + counter --
    const simPoolSearch = document.getElementById('sim_pool_search');
    const simPoolCheckAll = document.getElementById('sim_pool_check_all');
    const simPoolSelectedBadge = document.getElementById('simPoolSelectedBadge');

    function updateSimPoolBadge() {
        if (!simPoolSelectedBadge) return;
        const n = document.querySelectorAll('.sim-pool-check:checked').length;
        simPoolSelectedBadge.innerText = n + ' dipilih';
    }

    if (simPoolSearch) {
        simPoolSearch.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.sim-pool-row').forEach(row => {
                row.style.display = (!q || (row.dataset.search || '').includes(q)) ? '' : 'none';
            });
        });
    }
    if (simPoolCheckAll) {
        simPoolCheckAll.addEventListener('change', function () {
            document.querySelectorAll('.sim-pool-row').forEach(row => {
                if (row.style.display === 'none') return; // only visible rows
                const cb = row.querySelector('.sim-pool-check');
                if (cb) cb.checked = this.checked;
            });
            updateSimPoolBadge();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('sim-pool-check')) updateSimPoolBadge();
    });

    // -- Mode B: manual / scan rows --
    const simManualBody = document.getElementById('simManualBody');
    const simManualEmpty = document.getElementById('simManualEmpty');
    const simScanInput = document.getElementById('sim_scan_input');
    let simRowSeq = 0;

    function providerOptions(selected) {
        let html = '';
        simProvidersData.forEach(p => {
            html += `<option value="${p}" ${p === selected ? 'selected' : ''}>${p}</option>`;
        });
        return html;
    }

    window.addSimRow = function (msisdn = '') {
        if (simManualEmpty) simManualEmpty.style.display = 'none';
        simRowSeq++;
        const id = 'sim-row-' + simRowSeq;
        const tr = document.createElement('tr');
        tr.id = id;
        tr.innerHTML = `
            <td><input type="text" name="sim_msisdns[]" class="form-control" value="${msisdn}" placeholder="MSISDN" required></td>
            <td><select name="sim_providers[]" class="form-control">${providerOptions('Telkomsel')}</select></td>
            <td><input type="text" name="sim_categories[]" class="form-control" value="Data"></td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-danger btn-icon-sm" style="padding:4px 8px; font-size:11px;" onclick="removeSimRow('${id}')"><i class="fa-solid fa-trash"></i></button>
            </td>`;
        simManualBody.appendChild(tr);
        return tr;
    };

    window.removeSimRow = function (id) {
        const row = document.getElementById(id);
        if (row) row.remove();
        if (simManualBody.querySelectorAll('tr:not(#simManualEmpty)').length === 0 && simManualEmpty) {
            simManualEmpty.style.display = 'table-row';
        }
    };

    if (simScanInput) {
        simScanInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const v = this.value.trim();
                if (v !== '') {
                    addSimRow(v);
                    if (window.playBeep) window.playBeep('success');
                }
                this.value = '';
                this.focus();
            }
        });
    }

    // ============================================
    // DO / Excel Upload & Komparasi Serial Number
    // ============================================
    (function initDoComparison() {
        const fileInput   = document.getElementById('do_file_input');
        const fileNameEl  = document.getElementById('doFileName');
        const clearBtn    = document.getElementById('doClearBtn');
        const panel       = document.getElementById('doComparePanel');
        const draftBody   = document.getElementById('draftTableBody');
        if (!fileInput || !panel) return;

        // Daftar SN dari berkas DO (huruf besar, trim).
        let doSns = [];

        const norm = v => String(v == null ? '' : v).trim().toUpperCase();

        // Ambil SN hasil scan terkini dari tabel draft.
        function scannedSns() {
            return Array.from(document.querySelectorAll('input[name="sns[]"]')).map(el => norm(el.value));
        }

        // Ekstrak kolom SN dari sheet (cari header mengandung 'serial'/'sn', jika tidak ada pakai kolom pertama).
        function extractSns(rows) {
            if (!rows || !rows.length) return [];
            const header = rows[0].map(h => norm(h));
            let col = header.findIndex(h => h.includes('SERIAL') || h === 'SN' || h.includes('SERIAL_NUMBER') || h.includes('SERIALNUMBER'));
            let dataRows = rows;
            if (col >= 0) {
                dataRows = rows.slice(1); // ada header
            } else {
                col = 0; // tanpa header → kolom pertama
            }
            const out = [];
            const seen = new Set();
            dataRows.forEach(r => {
                const val = norm(r[col]);
                if (val && !seen.has(val)) { seen.add(val); out.push(val); }
            });
            return out;
        }

        function render() {
            if (!doSns.length) { panel.style.display = 'none'; return; }
            panel.style.display = 'block';

            const scanned = scannedSns();
            const scanSet = new Set(scanned);
            const doSet   = new Set(doSns);

            const matched = doSns.filter(sn => scanSet.has(sn));
            const missing = doSns.filter(sn => !scanSet.has(sn));        // di DO, belum discan
            const extra   = scanned.filter(sn => !doSet.has(sn) && sn);  // discan, tak ada di DO

            document.getElementById('doTotal').innerText     = doSns.length;
            document.getElementById('doScanTotal').innerText = scanned.length;
            document.getElementById('doMatch').innerText     = matched.length;
            document.getElementById('doMissing').innerText   = missing.length;
            document.getElementById('doExtra').innerText     = extra.length;

            const badge = document.getElementById('doMatchBadge');
            if (missing.length === 0 && extra.length === 0) {
                badge.style.background = 'rgba(16,185,129,0.15)';
                badge.style.color = 'var(--accent-emerald)';
                badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cocok 100% — semua SN sesuai DO';
            } else {
                badge.style.background = 'rgba(245,158,11,0.15)';
                badge.style.color = 'var(--accent-amber)';
                badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Ada selisih: ' + missing.length + ' belum discan, ' + extra.length + ' ekstra';
            }

            const missWrap = document.getElementById('doMissingWrap');
            const missList = document.getElementById('doMissingList');
            if (missing.length) {
                missWrap.style.display = 'block';
                missList.innerHTML = missing.map(sn => `<div style="color:var(--accent-amber);"><i class="fa-solid fa-circle-minus" style="font-size:9px;"></i> ${sn}</div>`).join('');
            } else { missWrap.style.display = 'none'; }

            const extraWrap = document.getElementById('doExtraWrap');
            const extraList = document.getElementById('doExtraList');
            if (extra.length) {
                extraWrap.style.display = 'block';
                extraList.innerHTML = extra.map(sn => `<div style="color:var(--danger-color);"><i class="fa-solid fa-circle-plus" style="font-size:9px;"></i> ${sn}</div>`).join('');
            } else { extraWrap.style.display = 'none'; }
        }
        // Diakses oleh observer & handler lain.
        window.updateDoComparison = render;

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            if (typeof XLSX === 'undefined') {
                alert('Pustaka pembaca Excel belum termuat. Periksa koneksi internet lalu coba lagi.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (ev) {
                try {
                    const wb = XLSX.read(ev.target.result, { type: 'array' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false });
                    doSns = extractSns(rows);

                    if (!doSns.length) {
                        alert('Tidak ada Serial Number terbaca dari berkas. Pastikan ada kolom "serial_number" atau SN di kolom pertama.');
                        return;
                    }
                    fileNameEl.innerText = file.name + ' (' + doSns.length + ' SN)';
                    clearBtn.style.display = 'inline';
                    render();
                    if (window.playBeep) window.playBeep('success');
                } catch (err) {
                    console.error(err);
                    alert('Gagal membaca berkas DO: ' + err.message);
                }
            };
            reader.readAsArrayBuffer(file);
        });

        clearBtn.addEventListener('click', function () {
            doSns = [];
            fileInput.value = '';
            fileNameEl.innerText = 'Pilih file Excel / CSV DO';
            clearBtn.style.display = 'none';
            panel.style.display = 'none';
        });

        // Recompute setiap kali draft scan berubah (tambah/hapus/clear).
        if (draftBody) {
            new MutationObserver(() => render()).observe(draftBody, { childList: true });
        }

        // Peringatan saat submit bila hasil scan tidak cocok dengan DO.
        const devForm = document.getElementById('receivingForm');
        if (devForm) {
            devForm.addEventListener('submit', function (e) {
                if (!doSns.length) return;
                const scanned = scannedSns();
                const scanSet = new Set(scanned);
                const doSet = new Set(doSns);
                const missing = doSns.filter(sn => !scanSet.has(sn));
                const extra = scanned.filter(sn => !doSet.has(sn) && sn);
                if (missing.length || extra.length) {
                    const msg = 'Hasil scan TIDAK COCOK dengan DO:\n'
                        + '• ' + missing.length + ' SN di DO belum discan\n'
                        + '• ' + extra.length + ' SN discan tidak ada di DO\n\n'
                        + 'Tetap lanjutkan submit?';
                    if (!confirm(msg)) e.preventDefault();
                }
            });
        }
    })();

    // ============================================
    // DO / Excel Upload & Komparasi AKSESORIS (qty per kode)
    // ============================================
    (function initAccDoComparison() {
        const fileInput  = document.getElementById('acc_do_file_input');
        const fileNameEl = document.getElementById('accDoFileName');
        const clearBtn   = document.getElementById('accDoClearBtn');
        const panel      = document.getElementById('accDoComparePanel');
        const draftBody  = document.getElementById('accDraftTableBody');
        if (!fileInput || !panel) return;

        // Peta nama aksesoris untuk tampilan.
        const accNameMap = {};
        (accessoriesListData || []).forEach(a => { if (a && a.code) accNameMap[String(a.code).toUpperCase()] = a.name; });

        let doMap = {}; // { CODE: qty }

        const norm = v => String(v == null ? '' : v).trim().toUpperCase();

        function scannedMap() {
            const map = {};
            document.querySelectorAll('#accDraftTableBody tr').forEach(tr => {
                const codeEl = tr.querySelector('input[name="acc_types[]"]');
                const qtyEl  = tr.querySelector('.acc-qty-input');
                if (!codeEl) return;
                const code = norm(codeEl.value);
                const qty  = parseInt((qtyEl && qtyEl.value) || 0) || 0;
                if (code) map[code] = (map[code] || 0) + qty;
            });
            return map;
        }

        function extractAccMap(rows) {
            const out = {};
            if (!rows || !rows.length) return out;
            const header = rows[0].map(h => norm(h));
            let codeCol = header.findIndex(h => h.includes('CODE') || h.includes('KODE') || h === 'SKU');
            let qtyCol  = header.findIndex(h => h.includes('QTY') || h.includes('JUMLAH') || h.includes('QUANTITY'));
            let dataRows = rows;
            if (codeCol >= 0 || qtyCol >= 0) {
                dataRows = rows.slice(1);
                if (codeCol < 0) codeCol = 0;
                if (qtyCol < 0) qtyCol = 1;
            } else {
                codeCol = 0; qtyCol = 1; // tanpa header
            }
            dataRows.forEach(r => {
                const code = norm(r[codeCol]);
                const qty  = parseInt(r[qtyCol]) || 0;
                if (code) out[code] = (out[code] || 0) + qty;
            });
            return out;
        }

        function render() {
            if (!Object.keys(doMap).length) { panel.style.display = 'none'; return; }
            panel.style.display = 'block';

            const scan = scannedMap();
            const codes = Array.from(new Set([...Object.keys(doMap), ...Object.keys(scan)])).sort();

            let doTotal = 0, scanTotal = 0, okCount = 0, diffCount = 0;
            let bodyHtml = '';
            codes.forEach(code => {
                const d = doMap[code] || 0;
                const s = scan[code] || 0;
                doTotal += d; scanTotal += s;
                const ok = d === s;
                if (ok) okCount++; else diffCount++;
                const name = accNameMap[code] || '';
                const color = ok ? 'var(--accent-emerald)' : 'var(--accent-amber)';
                const icon = ok ? 'fa-circle-check' : 'fa-triangle-exclamation';
                bodyHtml += `<tr>
                    <td style="font-weight:600;">${code}${name ? ' <span style="color:var(--text-muted); font-weight:400;">· ' + name + '</span>' : ''}</td>
                    <td style="text-align:center;">${d}</td>
                    <td style="text-align:center;">${s}</td>
                    <td style="text-align:center; color:${color};"><i class="fa-solid ${icon}"></i> ${ok ? 'cocok' : (s - d > 0 ? '+' + (s - d) : (s - d))}</td>
                </tr>`;
            });

            document.getElementById('accDoTotal').innerText = doTotal;
            document.getElementById('accDoScanTotal').innerText = scanTotal;
            document.getElementById('accDoOk').innerText = okCount;
            document.getElementById('accDoDiff').innerText = diffCount;
            document.getElementById('accDoCompareBody').innerHTML = bodyHtml;

            const badge = document.getElementById('accDoMatchBadge');
            if (diffCount === 0) {
                badge.style.background = 'rgba(16,185,129,0.15)'; badge.style.color = 'var(--accent-emerald)';
                badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Semua qty sesuai DO';
            } else {
                badge.style.background = 'rgba(245,158,11,0.15)'; badge.style.color = 'var(--accent-amber)';
                badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + diffCount + ' kode berbeda dengan DO';
            }
        }

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            if (typeof XLSX === 'undefined') { alert('Pustaka pembaca Excel belum termuat.'); return; }
            const reader = new FileReader();
            reader.onload = function (ev) {
                try {
                    const wb = XLSX.read(ev.target.result, { type: 'array' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false });
                    doMap = extractAccMap(rows);
                    if (!Object.keys(doMap).length) {
                        alert('Tidak ada data terbaca. Pastikan ada kolom "code" dan "qty".');
                        return;
                    }
                    fileNameEl.innerText = file.name + ' (' + Object.keys(doMap).length + ' kode)';
                    clearBtn.style.display = 'inline';
                    render();
                    if (window.playBeep) window.playBeep('success');
                } catch (err) { console.error(err); alert('Gagal membaca berkas DO: ' + err.message); }
            };
            reader.readAsArrayBuffer(file);
        });

        clearBtn.addEventListener('click', function () {
            doMap = {};
            fileInput.value = '';
            fileNameEl.innerText = 'Pilih file Excel / CSV DO';
            clearBtn.style.display = 'none';
            panel.style.display = 'none';
        });

        if (draftBody) {
            new MutationObserver(() => render()).observe(draftBody, { childList: true });
            draftBody.addEventListener('input', () => render());
        }

        const accForm = document.getElementById('receivingAccessoryForm');
        if (accForm) {
            accForm.addEventListener('submit', function (e) {
                if (!Object.keys(doMap).length) return;
                const scan = scannedMap();
                const codes = new Set([...Object.keys(doMap), ...Object.keys(scan)]);
                let diff = 0;
                codes.forEach(c => { if ((doMap[c] || 0) !== (scan[c] || 0)) diff++; });
                if (diff > 0) {
                    if (!confirm('Qty aksesoris TIDAK COCOK dengan DO pada ' + diff + ' kode.\n\nTetap lanjutkan submit?')) e.preventDefault();
                }
            });
        }
    })();

    // ============================================
    // DO / Excel Upload & Komparasi GSM (MSISDN)
    // ============================================
    (function initSimDoComparison() {
        const fileInput  = document.getElementById('sim_do_file_input');
        const fileNameEl = document.getElementById('simDoFileName');
        const clearBtn   = document.getElementById('simDoClearBtn');
        const panel      = document.getElementById('simDoComparePanel');
        if (!fileInput || !panel) return;

        let doMsisdns = [];
        const norm = v => String(v == null ? '' : v).trim();

        // MSISDN yang akan diterima = pool tercentang + baris manual terisi.
        function selectedMsisdns() {
            const out = [];
            document.querySelectorAll('.sim-pool-check:checked').forEach(cb => {
                const row = cb.closest('.sim-pool-row');
                if (row && row.dataset.msisdn) out.push(norm(row.dataset.msisdn));
            });
            document.querySelectorAll('input[name="sim_msisdns[]"]').forEach(el => {
                const v = norm(el.value);
                if (v) out.push(v);
            });
            return out;
        }

        function extractMsisdns(rows) {
            if (!rows || !rows.length) return [];
            const header = rows[0].map(h => norm(h).toUpperCase());
            let col = header.findIndex(h => h.includes('MSISDN') || h.includes('NOMOR') || h.includes('PHONE') || h.includes('GSM'));
            let dataRows = rows;
            if (col >= 0) dataRows = rows.slice(1); else col = 0;
            const out = []; const seen = new Set();
            dataRows.forEach(r => {
                const v = norm(r[col]);
                if (v && !seen.has(v)) { seen.add(v); out.push(v); }
            });
            return out;
        }

        function render() {
            if (!doMsisdns.length) { panel.style.display = 'none'; return; }
            panel.style.display = 'block';
            const sel = selectedMsisdns();
            const selSet = new Set(sel);
            const doSet = new Set(doMsisdns);
            const matched = doMsisdns.filter(m => selSet.has(m));
            const missing = doMsisdns.filter(m => !selSet.has(m));
            const extra = sel.filter(m => !doSet.has(m));

            document.getElementById('simDoTotal').innerText = doMsisdns.length;
            document.getElementById('simDoSelTotal').innerText = sel.length;
            document.getElementById('simDoMatch').innerText = matched.length;
            document.getElementById('simDoMissing').innerText = missing.length;
            document.getElementById('simDoExtra').innerText = extra.length;

            const badge = document.getElementById('simDoMatchBadge');
            if (missing.length === 0 && extra.length === 0) {
                badge.style.background = 'rgba(16,185,129,0.15)'; badge.style.color = 'var(--accent-emerald)';
                badge.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cocok 100% — semua MSISDN sesuai DO';
            } else {
                badge.style.background = 'rgba(245,158,11,0.15)'; badge.style.color = 'var(--accent-amber)';
                badge.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + missing.length + ' belum dipilih, ' + extra.length + ' ekstra';
            }

            const mWrap = document.getElementById('simDoMissingWrap');
            const mList = document.getElementById('simDoMissingList');
            if (missing.length) {
                mWrap.style.display = 'block';
                mList.innerHTML = missing.map(m => `<div style="color:var(--accent-amber);"><i class="fa-solid fa-circle-minus" style="font-size:9px;"></i> ${m}</div>`).join('');
            } else { mWrap.style.display = 'none'; }
        }

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            if (typeof XLSX === 'undefined') { alert('Pustaka pembaca Excel belum termuat.'); return; }
            const reader = new FileReader();
            reader.onload = function (ev) {
                try {
                    const wb = XLSX.read(ev.target.result, { type: 'array' });
                    const ws = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false });
                    doMsisdns = extractMsisdns(rows);
                    if (!doMsisdns.length) { alert('Tidak ada MSISDN terbaca dari berkas.'); return; }
                    fileNameEl.innerText = file.name + ' (' + doMsisdns.length + ' MSISDN)';
                    clearBtn.style.display = 'inline';
                    render();
                    if (window.playBeep) window.playBeep('success');
                } catch (err) { console.error(err); alert('Gagal membaca berkas DO: ' + err.message); }
            };
            reader.readAsArrayBuffer(file);
        });

        clearBtn.addEventListener('click', function () {
            doMsisdns = [];
            fileInput.value = '';
            fileNameEl.innerText = 'Pilih file Excel / CSV DO';
            clearBtn.style.display = 'none';
            panel.style.display = 'none';
        });

        // Recompute saat pilihan pool berubah atau baris manual diisi/dihapus.
        document.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('sim-pool-check')) render();
        });
        if (simManualBody) {
            new MutationObserver(() => render()).observe(simManualBody, { childList: true });
            simManualBody.addEventListener('input', () => render());
        }
    })();
</script>
@endsection
