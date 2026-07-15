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
            <i class="fa-solid fa-mobile-screen" style="color: var(--accent-blue);"></i> Penerimaan Device
        </button>
        <button class="btn btn-outline" id="tabAccessoryBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-secondary);">
            <i class="fa-solid fa-plug" style="color: var(--accent-indigo);"></i> Penerimaan Aksesoris
        </button>
        <button class="btn btn-outline" id="tabSimBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-secondary);">
            <i class="fa-solid fa-sim-card" style="color: var(--accent-indigo);"></i>Penerimaan Kartu GSM
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
                    <!-- Real-time Waktu Penerimaan -->
                    <div style="display:flex; align-items:center; gap:14px; padding:12px 16px; background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(99,102,241,0.08)); border:1px solid rgba(59,130,246,0.2); border-radius:10px; margin-bottom:14px;">
                        <i class="fa-solid fa-clock" style="font-size:22px; color:var(--accent-blue);"></i>
                        <div>
                            <div style="font-size:10px; color:var(--text-muted); font-weight:500; text-transform:uppercase; letter-spacing:0.5px;">Waktu Penerimaan Device</div>
                            <div id="receivingClock_dev" style="font-size:15px; font-weight:700; color:var(--text-primary); letter-spacing:0.3px;"></div>
                        </div>
                    </div>

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

                        <!-- Mass Input Device -->
                        <div class="form-group" style="margin-bottom: 18px; padding: 12px; background: rgba(59, 130, 246, 0.05); border: 1px dashed rgba(59, 130, 246, 0.3); border-radius: 8px;">
                            <label style="font-weight: 600; color: var(--accent-blue); font-size: 13px;">
                                <i class="fa-solid fa-paste"></i> ATAU INPUT MASSAL
                                <span style="font-weight:400; color:var(--text-muted);">(Paste banyak SN, satu baris per SN)</span>
                            </label>
                            <textarea id="device_mass_input" class="form-control" rows="3" placeholder="Contoh:&#10;FMC920-001&#10;FMC920-002&#10;FMC920-003" style="resize:vertical; font-family:monospace; margin-top: 8px; font-size: 13px;"></textarea>
                            <button type="button" id="btnDeviceMassInput" class="btn btn-primary" style="width: 100%; margin-top: 10px; justify-content: center; font-size: 13px;">
                                <i class="fa-solid fa-arrow-down"></i> Proses Input Massal
                            </button>
                        </div>


                        <!-- AI Suggestion pills -->
                        <div id="quick_device_suggestions" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 18px;">
                            <span style="font-size: 12px; color: var(--text-secondary); font-weight: 500;"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent-amber);"></i> Suggestion:</span>
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
                            <div style="display:flex; gap:12px; align-items:center;">
                                <select id="devicePerPage" class="form-control" style="width: auto; padding: 4px 8px; height: 32px; font-size: 12px;">
                                    <option value="10">10 per halaman</option>
                                    <option value="20">20 per halaman</option>
                                    <option value="50">50 per halaman</option>
                                    <option value="100">100 per halaman</option>
                                    <option value="200">200 per halaman</option>
                                </select>
                                <button type="button" id="clearDraftBtn" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;">Hapus Semua</button>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table" id="draftTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Serial Number (SN)</th>
                                        <th>Tipe</th>
                                        <th>Model</th>
                                        <th>Kondisi</th>
                                        <th>Rak</th>
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
                        
                        <!-- Paginasi Draft Device -->
                        <div id="devicePaginationWrap" style="display:none; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border-color);">
                            <span id="devicePageInfo" style="font-size:12px; color:var(--text-muted);"></span>
                            <div style="display:flex; gap:6px;">
                                <button type="button" id="devicePrevBtn" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">‹ Prev</button>
                                <button type="button" id="deviceNextBtn" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">Next ›</button>
                            </div>
                        </div>
                    </div>

                    <!-- Scan Lokasi Penyimpanan (Rak) - Tab Device -->
                    <div class="card" style="border-color: rgba(16,185,129,0.3);">
                        <div class="card-header" style="background: rgba(16,185,129,0.06);">
                            <div class="card-title" style="color: var(--accent-emerald);"><i class="fa-solid fa-boxes-stacked"></i> Scan Lokasi Penyimpanan (Rak)</div>
                        </div>
                        <div style="padding: 16px;">
                            <div style="position: relative; margin-bottom: 10px;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="rack_barcode_input" class="form-control" placeholder="Scan barcode rak tujuan lalu Enter..." style="padding-left: 42px; font-size: 16px; font-weight: 600; height: 50px; border-color: rgba(16,185,129,0.4);" autocomplete="off">
                            </div>
                            <div id="rack_badge" style="display:none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; background: rgba(16,185,129,0.15); color: var(--accent-emerald); border: 1px solid rgba(16,185,129,0.3); margin-bottom: 8px;">
                                <i class="fa-solid fa-location-dot"></i> <span id="rack_badge_text"></span>
                                <button type="button" onclick="clearRack()" style="background:none;border:none;color:inherit;cursor:pointer;float:right;opacity:0.7;padding:0;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <input type="hidden" name="rack_barcode" id="rack_barcode_hidden" value="">
                            <small style="color: var(--text-muted); font-size: 11px;"><i class="fa-solid fa-circle-info"></i> Opsional — semua device yang di-scan akan disimpan ke rak ini.</small>
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
                                    <span>Crosscheck Data Penerimaan Alat (Device)</span>
                                </div>
                            </div>

                            <x-warehouse-select
                                name="warehouse"
                                id="warehouse_select"
                                label="Gudang Tujuan (Destination Warehouse)"
                                :warehouses="$warehouses"
                                :show-empty-option="false"
                            />

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
            <input type="hidden" name="warehouse" id="acc_warehouse_mirror">

            <div class="receiving-split">

                <!-- Left Side: Opsi Input + Preview Draft -->
                <div>
                    <!-- Real-time Waktu Penerimaan -->
                    <div style="display:flex; align-items:center; gap:14px; padding:12px 16px; background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(79,70,229,0.05)); border:1px solid rgba(99,102,241,0.2); border-radius:10px; margin-bottom:16px;">
                        <i class="fa-solid fa-clock" style="font-size:22px; color:var(--accent-indigo);"></i>
                        <div>
                            <div style="font-size:10px; color:var(--text-muted); font-weight:500; text-transform:uppercase; letter-spacing:0.5px;">Waktu Penerimaan Aksesoris</div>
                            <div id="receivingClock_acc" style="font-size:15px; font-weight:700; color:var(--text-primary); letter-spacing:0.3px;"></div>
                        </div>
                    </div>
                    <!-- Opsi Mode Selector -->
                    <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                        <label class="btn btn-outline acc-mode-lbl active" style="flex:1; text-align:center; cursor:pointer; border-color:var(--accent-indigo); background:rgba(99,102,241,0.1);">
                            <input type="radio" name="acc_mode_radio" value="manual" checked style="display:none;">
                            <div style="font-weight:600;"><i class="fa-solid fa-barcode"></i> Opsi 1</div>
                            <div style="font-size:11px; opacity:0.8;">Scan / Input Manual</div>
                        </label>
                        <label class="btn btn-outline acc-mode-lbl" style="flex:1; text-align:center; cursor:pointer;">
                            <input type="radio" name="acc_mode_radio" value="excel" style="display:none;">
                            <div style="font-weight:600;"><i class="fa-solid fa-file-excel"></i> Opsi 2</div>
                            <div style="font-size:11px; opacity:0.8;">Upload Massal Excel</div>
                        </label>
                    </div>

                    <!-- OPSI 1: Input Manual + Scan SN -->
                    <div class="card" id="accCardManual" style="margin-bottom:20px; overflow:visible;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-barcode"></i> <span>Opsi 1 — Scan / Input Manual Aksesoris</span></div>
                        </div>
                        <div style="padding:16px;">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                                <!-- Pilih Jenis Aksesoris -->
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; color:var(--accent-indigo);">Jenis Aksesoris</label>
                                    <select id="acc_type_select" class="form-control">
                                        <option value="">-- Pilih Jenis --</option>
                                        @foreach($accessories ?? [] as $acc)
                                            <option value="{{ $acc['code'] }}" data-name="{{ $acc['name'] }}" data-unit="{{ $acc['unit'] ?? 'pcs' }}">{{ $acc['name'] }} ({{ $acc['code'] }})</option>
                                        @endforeach
                                        <option value="OTHER">--- Lain-lain (Ketik Sendiri) ---</option>
                                    </select>
                                    <!-- Input custom nama jika "Lain-lain" -->
                                    <input type="text" id="acc_custom_name" class="form-control" placeholder="Ketik nama aksesoris..." style="display:none; margin-top:6px;">
                                    <input type="text" id="acc_custom_code" class="form-control" placeholder="Kode aksesoris (opsional)..." style="display:none; margin-top:4px; font-size:12px;">
                                </div>
                                <!-- Qty & Unit -->
                                <div style="display:flex; gap:12px; margin-bottom:0; align-items: flex-end;">
                                    <div class="form-group" style="margin-bottom:0; flex:1;">
                                        <label style="font-weight:600;">Qty (Jumlah Diterima)</label>
                                        <input type="number" id="acc_qty_input" class="form-control" min="1" value="1" placeholder="Jumlah...">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0; display:none; flex:1;" id="acc_unit_group">
                                        <label style="font-weight:600;">Satuan</label>
                                        <select id="acc_unit_input" class="form-control">
                                            <option value="pcs">pcs</option>
                                            <option value="unit">unit</option>
                                            <option value="pack">pack</option>
                                            <option value="box">box</option>
                                            <option value="set">set</option>
                                            <option value="m">m</option>
                                            <option value="roll">roll</option>
                                            <option value="g">g</option>
                                            <option value="pair">pair</option>
                                            <option value="lembar">lembar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Textarea SN (opsional) -->
                            <div class="form-group" style="margin-bottom:12px;">
                                <label style="font-weight:600;">Serial Number / SN <span style="font-weight:400; font-size:12px; color:var(--text-muted);">(Opsional — paste/scan satu SN per baris)</span></label>
                                <textarea id="acc_sn_textarea" class="form-control" rows="3" placeholder="Contoh:&#10;SN-ACC-0001&#10;SN-ACC-0002&#10;SN-ACC-0003" style="resize:vertical; font-family:monospace;"></textarea>
                            </div>
                            <button type="button" id="btnAddAccToDraft" class="btn btn-primary" style="width:100%; justify-content:center;">
                                <i class="fa-solid fa-arrow-down"></i> Tambahkan ke Preview Draft
                            </button>
                        </div>
                    </div>

                    <!-- OPSI 2: Upload Massal Excel -->
                    <div class="card" id="accCardExcel" style="margin-bottom:20px; display:none;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-file-excel"></i> <span>Opsi 2 — Terima Massal Aksesoris via Excel</span></div>
                        </div>
                        <div style="padding:16px;">
                            <p style="font-size:13px; color:var(--text-secondary); margin-bottom:12px;">
                                Download template, isi data, lalu upload kembali. Klik <strong>"Proses File"</strong> untuk memuat data ke preview draft.
                            </p>
                            <div style="margin-bottom:12px;">
                                <a href="data:text/csv;charset=utf-8,code,name,serial_number,qty%0AACC-001,Kabel UTP,SN-001,5%0AACC-002,Konektor RJ45,,10"
                                   download="template_aksesoris_massal.csv"
                                   class="btn btn-outline" style="font-size:12px; padding:8px 14px;">
                                    <i class="fa-solid fa-download"></i> Download Template Excel/CSV
                                </a>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label style="font-weight:600;">Upload File Excel / CSV</label>
                                <input type="file" id="acc_excel_upload" class="form-control" accept=".csv,.xlsx,.xls">
                            </div>
                            <button type="button" id="btnProcessAccExcel" class="btn btn-primary" style="width:100%; justify-content:center;">
                                <i class="fa-solid fa-gears"></i> Proses File & Tambahkan ke Preview
                            </button>
                        </div>
                    </div>

                    <!-- Preview Draft Aksesoris yang Diterima (shared by both options) -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-plug"></i>
                                <span>Preview Draft Aksesoris Diterima (<span id="accDraftCount">0</span> baris)</span>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button type="button" id="btnSaveEditsAcc" class="btn btn-outline" style="padding:6px 12px; font-size:12px; color:var(--accent-indigo); border-color:var(--accent-indigo); display:none;">
                                    <i class="fa-solid fa-floppy-disk"></i> Simpan Hasil Edit
                                </button>
                                <button type="button" id="btnClearAccDraft" class="btn btn-outline" style="padding:6px 12px; font-size:12px; color:var(--danger-color);">
                                    <i class="fa-solid fa-trash"></i> Hapus Semua
                                </button>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table" id="accDraftTable">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">No</th>
                                        <th>Kode</th>
                                        <th>Nama Aksesoris</th>
                                        <th>Serial Number (SN)</th>
                                        <th style="width:100px; text-align:center;">Qty</th>
                                        <th style="width:60px; text-align:center;">Satuan</th>
                                        <th style="width:80px; text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="accDraftTableBody">
                                    <tr id="emptyAccRowPlaceholder">
                                        <td colspan="7" style="text-align:center; color:var(--text-muted); padding:35px;">
                                            Belum ada aksesoris. Gunakan Opsi 1 atau Opsi 2 di atas.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginasi Draft -->
                        <div id="accDraftPaginationWrap" style="display:none; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border-color);">
                            <span id="accDraftPageInfo" style="font-size:12px; color:var(--text-muted);"></span>
                            <div style="display:flex; gap:6px;">
                                <button type="button" id="accDraftPrevBtn" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">‹ Prev</button>
                                <button type="button" id="accDraftNextBtn" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">Next ›</button>
                            </div>
                        </div>

                        <!-- Hidden inputs will be injected here by JS before submit -->
                        <div id="accHiddenInputsContainer"></div>
                    </div>

                    <!-- Scan Lokasi Penyimpanan (Rak) - Tab Aksesoris -->
                    <div class="card" style="border-color: rgba(16,185,129,0.3);">
                        <div class="card-header" style="background: rgba(16,185,129,0.06);">
                            <div class="card-title" style="color: var(--accent-emerald);"><i class="fa-solid fa-boxes-stacked"></i> Scan Lokasi Penyimpanan (Rak)</div>
                        </div>
                        <div style="padding: 16px;">
                            <div style="position: relative; margin-bottom: 10px;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="rack_barcode_input_acc" class="form-control" placeholder="Scan barcode rak tujuan lalu Enter..." style="padding-left: 42px; font-size: 16px; font-weight: 600; height: 50px; border-color: rgba(16,185,129,0.4);" autocomplete="off">
                            </div>
                            <div id="rack_badge_acc" style="display:none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; background: rgba(16,185,129,0.15); color: var(--accent-emerald); border: 1px solid rgba(16,185,129,0.3); margin-bottom: 8px;">
                                <i class="fa-solid fa-location-dot"></i> <span id="rack_badge_text_acc"></span>
                            </div>
                            <input type="hidden" name="rack_barcode" id="rack_barcode_hidden_acc" value="">
                            <small style="color: var(--text-muted); font-size: 11px;"><i class="fa-solid fa-circle-info"></i> Opsional — scan barcode rak tujuan untuk aksesoris ini.</small>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Crosscheck & Submit (sticky) -->
                <div>
                    <div class="receiving-sticky">
                        <!-- Live counter Aksesoris -->
                        <div class="card live-counter-card">
                            <div style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Total Ter-scan</div>
                            <div id="accLiveCounter" class="live-counter-num">0</div>
                            <div style="font-size: 12px; color: var(--text-muted);">item siap difinalisasi ke stok</div>
                        </div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-clipboard-check"></i>
                                <span>Crosscheck Data Aksesoris</span>
                            </div>
                        </div>

                        <x-warehouse-select
                            name="warehouse"
                            id="warehouse_select_acc"
                            label="Gudang Tujuan (Destination Warehouse)"
                            :warehouses="$warehouses"
                            :show-empty-option="false"
                        />

                        <!-- DO Upload untuk komparasi -->
                        <div class="form-group">
                            <label>Unggah Berkas DO (Excel / CSV) — untuk Crosscheck</label>
                            <input type="file" id="acc_do_file_input" accept=".csv,.xlsx,.xls" style="display:none;">
                            <label for="acc_do_file_input" class="file-upload-box" style="cursor:pointer; display:block;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p style="font-size:13px; font-weight:500;" id="accDoFileName">Pilih file Excel / CSV DO</p>
                                <span style="font-size:11px; color:var(--text-muted);">Cocokkan kode & qty dengan draft aksesoris.</span>
                            </label>
                            <div style="margin-top:6px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                <a href="data:text/csv;charset=utf-8,code,qty%0AACC-001,10%0AACC-002,5" download="template_do_aksesoris.csv" style="font-size:11px; color:var(--accent-blue);"><i class="fa-solid fa-download"></i> Template CSV DO</a>
                                <button type="button" id="accDoClearBtn" style="display:none; font-size:11px; background:none; border:none; color:var(--danger-color); cursor:pointer; padding:0;"><i class="fa-solid fa-xmark"></i> Hapus DO</button>
                            </div>
                        </div>

                        <!-- Panel Hasil Crosscheck -->
                        <div id="accDoComparePanel" style="display:none; margin-bottom:16px; border:1px solid var(--border-color); border-radius:8px; padding:12px; background:var(--bg-primary);">
                            <div style="font-size:13px; font-weight:600; margin-bottom:10px;"><i class="fa-solid fa-clipboard-check"></i> Hasil Crosscheck DO vs Draft</div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; margin-bottom:10px;">
                                <div style="font-size:12px;">Total Qty DO: <strong id="accDoTotal">0</strong></div>
                                <div style="font-size:12px;">Total Qty Draft: <strong id="accDoScanTotal">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-emerald);">Kode cocok: <strong id="accDoOk">0</strong></div>
                                <div style="font-size:12px; color:var(--accent-amber);">Kode beda: <strong id="accDoDiff">0</strong></div>
                            </div>
                            <div id="accDoMatchBadge" style="font-size:12px; font-weight:600; padding:8px 10px; border-radius:6px; text-align:center; margin-bottom:10px;"></div>
                            <div class="table-wrapper" style="max-height:200px; overflow:auto;">
                                <table class="table" style="font-size:12px;">
                                    <thead><tr><th>Kode</th><th style="text-align:center;">DO</th><th style="text-align:center;">Draft</th><th style="text-align:center;">Status</th></tr></thead>
                                    <tbody id="accDoCompareBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div style="margin-top: 24px; display:flex; flex-direction:column; gap:10px;">
                            <button type="button" id="btnCrosscheckAcc" class="btn btn-primary" style="width:100%; justify-content:center; padding:14px;" disabled title="Tambahkan aksesoris ke draft terlebih dahulu">
                                <i class="fa-solid fa-magnifying-glass"></i> Crosscheck
                            </button>
                            <button type="button" id="btnSaveAcc" class="btn btn-success" style="width:100%; justify-content:center; padding:14px; display:none;">
                                <i class="fa-solid fa-circle-check"></i> Simpan ke Database
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
                    <!-- Real-time Waktu Penerimaan -->
                    <div style="display:flex; align-items:center; gap:14px; padding:12px 16px; background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(99,102,241,0.08)); border:1px solid rgba(59,130,246,0.2); border-radius:10px; margin-bottom:16px;">
                        <i class="fa-solid fa-clock" style="font-size:22px; color:var(--accent-blue);"></i>
                        <div>
                            <div style="font-size:10px; color:var(--text-muted); font-weight:500; text-transform:uppercase; letter-spacing:0.5px;">Waktu Penerimaan Kartu GSM</div>
                            <div id="receivingClock_sim" style="font-size:15px; font-weight:700; color:var(--text-primary); letter-spacing:0.3px;"></div>
                        </div>
                    </div>
                    <!-- Mode Selector -->
                    <div style="display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;">
                        <input type="hidden" name="sim_input_mode" id="sim_input_mode_hidden" value="pool">
                        <label class="btn btn-outline sim-mode-lbl active" style="flex: 1; text-align: center; cursor: pointer; border-color: var(--accent-blue); background: rgba(59, 130, 246, 0.1);">
                            <input type="radio" name="sim_mode_radio" value="pool" checked style="display: none;">
                            <div style="font-weight: 600;"><i class="fa-solid fa-paste"></i> Opsi A</div>
                            <div style="font-size: 11px; opacity: 0.8;">Input Massal SN</div>
                        </label>
                        <label class="btn btn-outline sim-mode-lbl" style="flex: 1; text-align: center; cursor: pointer;">
                            <input type="radio" name="sim_mode_radio" value="manual" style="display: none;">
                            <div style="font-weight: 600;"><i class="fa-solid fa-barcode"></i> Opsi B</div>
                            <div style="font-size: 11px; opacity: 0.8;">Scan Barcode</div>
                        </label>
                        <label class="btn btn-outline sim-mode-lbl" style="flex: 1; text-align: center; cursor: pointer;">
                            <input type="radio" name="sim_mode_radio" value="csv" style="display: none;">
                            <div style="font-weight: 600;"><i class="fa-solid fa-file-csv"></i> Opsi C</div>
                            <div style="font-size: 11px; opacity: 0.8;">Bulk Upload CSV</div>
                        </label>
                    </div>

                    <!-- Mode A: Input Massal -->
                    <div class="card" id="simCardPool" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-paste"></i> <span>A. Input Massal MSISDN / SN</span></div>
                        </div>

                        <div style="padding:16px;">
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; font-size:13px;"><i class="fa-solid fa-tower-cell" style="color:var(--accent-indigo);"></i> Provider GSM</label>
                                    <select id="sim_mass_provider" class="form-control" style="border-color: rgba(99,102,241,0.4); font-weight:600;">
                                        <option value="">-- Pilih Provider --</option>
                                        @foreach($simProviders ?? [] as $prov)
                                            <option value="{{ $prov }}">{{ $prov }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; font-size:13px;"><i class="fa-solid fa-tag" style="color:var(--accent-blue);"></i> Kategori GSM</label>
                                    <select id="sim_mass_category" class="form-control" style="border-color: rgba(59,130,246,0.4); font-weight:600;">
                                        <!-- Diisi via JS berdasarkan provider -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:12px;">
                                <label style="font-weight:600;">MSISDN / SN <span style="font-weight:400; font-size:12px; color:var(--text-muted);">(Paste satu SN per baris)</span></label>
                                <textarea id="sim_mass_textarea" class="form-control" rows="4" placeholder="Contoh:&#10;6281234567890&#10;6281234567891" style="resize:vertical; font-family:monospace;"></textarea>
                            </div>
                            <button type="button" id="btnSimMassToDraft" class="btn btn-primary" style="width:100%; justify-content:center;">
                                <i class="fa-solid fa-arrow-down"></i> Tambahkan ke Preview Draft
                            </button>
                        </div>

                        <div class="card-header" style="border-top: 1px solid var(--border-color); margin-top: 16px;">
                            <div class="card-title">
                                <i class="fa-solid fa-list"></i>
                                <span>Preview Draft MSISDN (<span id="simDraftMassCount">0</span> baris)</span>
                            </div>
                            <button type="button" id="btnClearSimMassDraft" class="btn btn-outline" style="padding:6px 12px; font-size:12px; color:var(--danger-color);">
                                <i class="fa-solid fa-trash"></i> Hapus Semua
                            </button>
                        </div>
                        <div class="table-wrapper" style="max-height: 320px; overflow-y: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">No</th>
                                        <th>MSISDN</th>
                                        <th>Provider</th>
                                        <th>Kategori</th>
                                        <th>Rak</th>
                                        <th style="width:60px; text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="simMassDraftBody">
                                    <tr id="simMassDraftEmpty"><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 24px;">Belum ada MSISDN di draft.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>



                    <!-- Mode B: Manual / scan rows -->
                    <div class="card" id="simCardManual" style="margin-bottom: 24px; display: none; overflow: visible;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-barcode"></i> <span>B. Scan Barcode MSISDN</span></div>
                            <span class="badge badge-info" id="simManualCountBadge">0 ter-scan</span>
                        </div>

                        <div style="padding:16px;">
                            {{-- Provider & Kategori --}}
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; font-size:13px;"><i class="fa-solid fa-tower-cell" style="color:var(--accent-indigo);"></i> Provider GSM</label>
                                    <select id="sim_global_provider" class="form-control" style="border-color: rgba(99,102,241,0.4); font-weight:600;">
                                        <option value="">-- Pilih Provider --</option>
                                        @foreach($simProviders ?? [] as $prov)
                                            <option value="{{ $prov }}">{{ $prov }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label style="font-weight:600; font-size:13px;"><i class="fa-solid fa-tag" style="color:var(--accent-blue);"></i> Kategori GSM</label>
                                    <select id="sim_global_category" class="form-control" style="border-color: rgba(59,130,246,0.4); font-weight:600;">
                                        <!-- Diisi via JS berdasarkan provider -->
                                    </select>
                                </div>
                            </div>

                            {{-- Scan Input --}}
                            <div class="form-group" style="margin-bottom:6px;">
                                <label style="font-weight:600;">MSISDN / Barcode SIM <span style="font-weight:400; font-size:12px; color:var(--text-muted);">(Tembak barcode atau ketik lalu Enter)</span></label>
                                <div style="position:relative;">
                                    <i class="fa-solid fa-barcode" style="position:absolute; left:16px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:16px;"></i>
                                    <input type="text" id="sim_scan_input" class="form-control" placeholder="Tembak barcode atau ketik MSISDN lalu Enter..." autocomplete="off"
                                        style="padding-left:46px; font-size:16px; font-weight:600; height:52px; border-color:rgba(99,102,241,0.4);">
                                </div>
                            </div>
                            <div id="simScanStatusBar" style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted); padding: 4px 2px;">
                                <i class="fa-solid fa-circle-info" style="color:var(--accent-indigo);"></i>
                                <span id="simScanStatusText">Pilih Provider &amp; Kategori, lalu arahkan scanner. Mendukung scan URL panjang — nomor diekstrak otomatis.</span>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table" id="simManualTable">
                                <thead>
                                    <tr><th>No</th><th>MSISDN</th><th style="width: 140px;">Provider</th><th style="width: 120px;">Kategori</th><th style="width: 60px; text-align:right;">Aksi</th></tr>
                                </thead>
                                <tbody id="simManualBody">
                                    <tr id="simManualEmpty"><td colspan="5" style="text-align:center; color: var(--text-muted); padding: 24px;"><i class="fa-solid fa-sim-card" style="font-size:24px; opacity:0.3; display:block; margin-bottom:6px;"></i>Belum ada MSISDN. Pilih Provider &amp; Kategori di atas lalu arahkan scanner.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mode C: Bulk CSV -->
                    <div class="card" id="simCardCsv" style="display: none;">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-file-csv"></i> <span>C. Bulk Upload CSV</span></div>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                            Format kolom: <code>msisdn, provider, category</code> (baris pertama header).
                            <a href="data:text/csv;charset=utf-8,msisdn,provider,category%0A6281200000001,Telkomsel,Data%0A6281200000002,XL,Data" download="template_simcard.csv" style="color: var(--accent-blue);"><i class="fa-solid fa-download"></i> Download template</a>
                        </p>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv">
                    </div>

                    <!-- Scan Lokasi Penyimpanan (Rak) - Tab GSM -->
                    <div class="card" style="border-color: rgba(16,185,129,0.3);">
                        <div class="card-header" style="background: rgba(16,185,129,0.06);">
                            <div class="card-title" style="color: var(--accent-emerald);"><i class="fa-solid fa-boxes-stacked"></i> Scan Lokasi Penyimpanan (Rak)</div>
                        </div>
                        <div style="padding: 16px;">
                            <div style="position: relative; margin-bottom: 10px;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="rack_barcode_input_sim" class="form-control" placeholder="Scan barcode rak tujuan lalu Enter..." style="padding-left: 42px; font-size: 16px; font-weight: 600; height: 50px; border-color: rgba(16,185,129,0.4);" autocomplete="off">
                            </div>
                            <div id="rack_badge_sim" style="display:none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; background: rgba(16,185,129,0.15); color: var(--accent-emerald); border: 1px solid rgba(16,185,129,0.3); margin-bottom: 8px;">
                                <i class="fa-solid fa-location-dot"></i> <span id="rack_badge_text_sim"></span>
                            </div>
                            <input type="hidden" name="rack_barcode" id="rack_barcode_hidden_sim" value="">
                            <small style="color: var(--text-muted); font-size: 11px;"><i class="fa-solid fa-circle-info"></i> Opsional — scan barcode rak tujuan untuk kartu SIM ini.</small>
                        </div>
                    </div>
                </div>

                <!-- Right: config & submit (sticky) -->
                <div>
                    <div class="receiving-sticky">
                        <!-- Live counter SIM/GSM -->
                        <div class="card live-counter-card">
                            <div style="font-size: 13px; color: var(--text-secondary); font-weight: 500;">Total Ter-scan</div>
                            <div id="simLiveCounter" class="live-counter-num">0</div>
                            <div style="font-size: 12px; color: var(--text-muted);">kartu SIM siap difinalisasi ke stok</div>
                        </div>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fa-solid fa-floppy-disk"></i> <span>Simpan Data</span></div>
                        </div>
                        <x-warehouse-select
                            name="warehouse"
                            id="warehouse_select_sim"
                            label="Gudang Tujuan"
                            :warehouses="$warehouses"
                            :show-empty-option="false"
                            hint="Semua SIM yang dipilih/diisi/diunggah akan diterima ke gudang ini."
                        />

                        <div style="margin-top: 12px;">
                            <button type="submit" id="btnSubmitSim" class="btn btn-success" style="width: 100%; justify-content: center; padding: 14px;">
                                <i class="fa-solid fa-check"></i> Simpan data di database
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
    // List of already registered SNs in DB from session to prevent DB duplicate scanning (global - all warehouses)
    const dbSns = @json($existingSns ?? []);

    // List of already registered MSISDNs in DB (global - all warehouses)
    const dbMsisdns = @json($existingMsisdns ?? []);
    const dbMsisdnsSet = new Set(dbMsisdns.map(m => String(m).trim()));
    
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
        tabAccessoryBtn.style.borderBottomColor = 'var(--accent-indigo)';
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
    function focusInput(preventScroll = false) {
        if (activeTab === 'device' && barcodeInput) {
            barcodeInput.focus({ preventScroll: preventScroll });
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
                
                const rawInput = this.value.trim();
                if (rawInput !== '') {
                    // Split by space, comma, tab, or newline to support multiple SNs
                    const sns = rawInput.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
                    sns.forEach(sn => {
                        processScan(sn);
                    });
                }
                
                // Auto-clear and re-focus
                this.value = '';
                focusInput();
            }
        });
    }

    const btnDeviceMassInput = document.getElementById('btnDeviceMassInput');
    const deviceMassInput = document.getElementById('device_mass_input');
    if (btnDeviceMassInput && deviceMassInput) {
        btnDeviceMassInput.addEventListener('click', function() {
            const rawInput = deviceMassInput.value.trim();
            if (rawInput !== '') {
                const sns = rawInput.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                let initialCount = scanCount;
                let draftDupes = [];    // duplikat dalam draft sesi ini
                let crossWhDupes = []; // duplikat lintas gudang (ada di DB)

                sns.forEach(sn => {
                    // split by comma or space if user pasted multiple on same line
                    const subSns = sn.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
                    subSns.forEach(subSn => {
                        const cleanSubSn = subSn.replace(/[^a-zA-Z0-9\-]/g, '').trim().toUpperCase();
                        if (!cleanSubSn) return;

                        // Pre-check untuk pesan yang lebih spesifik
                        if (scanDraftSns.has(cleanSubSn)) {
                            draftDupes.push(cleanSubSn);
                            return;
                        }
                        if (dbSns.includes(cleanSubSn)) {
                            crossWhDupes.push(cleanSubSn);
                            return;
                        }

                        processScan(subSn);
                    });
                });

                let added = scanCount - initialCount;
                deviceMassInput.value = '';

                if (added > 0) {
                    if (window.playBeep) window.playBeep('success');
                    if (window.setScanStatus) window.setScanStatus('success', 'Berhasil menambahkan ' + added + ' SN.');
                }

                // Tampilkan notifikasi duplikat yang terperinci
                let msgs = [];
                if (draftDupes.length > 0) {
                    msgs.push('• ' + draftDupes.length + ' No SN duplikat di draft saat ini (diabaikan):\n  ' + draftDupes.slice(0, 5).join(', ') + (draftDupes.length > 5 ? '...' : ''));
                }
                if (crossWhDupes.length > 0) {
                    msgs.push('• ' + crossWhDupes.length + ' No SN duplikat, tanya tim admin untuk kelanjutannya:\n  ' + crossWhDupes.slice(0, 5).join(', ') + (crossWhDupes.length > 5 ? '...' : ''));
                }

                if (msgs.length > 0) {
                    if (window.playBeep) window.playBeep('error');
                    alert('⚠️ Peringatan Duplikat SN:\n\n' + msgs.join('\n\n'));
                } else if (added === 0) {
                    alert('Tidak ada SN yang ditambahkan. Periksa kembali input Anda.');
                }
            }
        });
    }

    function processScan(sn) {
        // Peta karakter shift jika scanner mengirimkan simbol angka (akibat caps lock / shift aktif)
        const shiftMap = {
            '!': '1', '@': '2', '#': '3', '$': '4', '%': '5',
            '^': '6', '&': '7', '*': '8', '(': '9', ')': '0'
        };
        let mappedSn = '';
        for (let i = 0; i < sn.length; i++) {
            const char = sn[i];
            if (shiftMap[char] !== undefined) {
                mappedSn += shiftMap[char];
            } else {
                mappedSn += char;
            }
        }
        sn = mappedSn;

        // Sanitasi: hanya izinkan huruf, angka, dan tanda hubung
        sn = sn.replace(/[^a-zA-Z0-9\-]/g, '').trim().toUpperCase();

        // Validasi: abaikan jika kosong setelah sanitasi
        if (!sn || sn.length < 1) {
            return; 
        }

        // Hide previous alert
        duplicateAlert.style.display = 'none';

        // 1. Duplicate check in current draft session
        if (scanDraftSns.has(sn)) {
            triggerDuplicateError(sn, false);
            return;
        }

        // 2. Duplicate check in database (global - ALL warehouses, not just active)
        if (dbSns.includes(sn)) {
            triggerDuplicateError(sn, true); // cross-warehouse duplicate
            return;
        }

        // Add to draft list
        scanDraftSns.add(sn);
        scanCount++;

        // Remove empty state placeholder
        if (emptyRowPlaceholder) {
            emptyRowPlaceholder.style.display = 'none';
        }

        // Generate row info
        const deviceType = document.getElementById('scan_type').value || 'N/A';
        const deviceModel = document.getElementById('scan_model').value || 'N/A';
        const deviceCondition = (document.getElementById('scan_condition') || {}).value === 'BEKAS' ? 'BEKAS' : 'BARU';
        const condBadgeClass = deviceCondition === 'BEKAS' ? 'badge-warning' : 'badge-success';

        // Play success beep
        if (window.playBeep) {
            window.playBeep('success');
        }

        // Escape HTML special chars agar SN tidak rusak
        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // Create table row
        const newRow = document.createElement('tr');
        newRow.setAttribute('id', `row-${escHtml(sn)}`);
        newRow.className = 'animate-fade-in';
        newRow.innerHTML = `
            <td>${scanCount}</td>
            <td style="font-weight:600; color:var(--accent-blue);">${escHtml(sn)}
                <input type="hidden" name="sns[]" value="${escHtml(sn)}">
            </td>
            <td><span class="badge badge-info">${escHtml(deviceType)}</span>
                <input type="hidden" name="types[]" value="${escHtml(deviceType)}">
            </td>
            <td>${escHtml(deviceModel)}
                <input type="hidden" name="models[]" value="${escHtml(deviceModel)}">
            </td>
            <td><span class="badge ${condBadgeClass}">${deviceCondition}</span>
                <input type="hidden" name="conditions[]" value="${deviceCondition}">
            </td>
            <td style="font-size:11px; color:var(--accent-emerald); font-weight:600;" class="draft-rack-cell">${currentRack || '<span style="color:var(--text-muted);">—</span>'}
                <input type="hidden" name="rack_barcodes[]" class="rack-barcode-per-sn" value="${escHtml(currentRack)}">
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeScanRow('${escHtml(sn)}')" style="padding:4px 8px; font-size:11px;" title="Hapus SN">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        `;

        draftTableBody.appendChild(newRow);

        // Update count & button status
        draftCountSpan.innerText = scanCount;
        submitBtn.disabled = false;
        
        renderDevicePagination();

        // Status feedback (hijau = sukses)
        if (window.setScanStatus) {
            window.setScanStatus('success', 'Tersimpan: ' + sn + ' — total ' + scanCount + ' unit.');
        }
    }

    function triggerDuplicateError(sn, isCrossWarehouse = false) {
        // Play error warning beep
        if (window.playBeep) {
            window.playBeep('error');
        }

        const alertBox = document.getElementById('duplicateAlert');
        const alertMsg = alertBox ? alertBox.querySelector('.alert-message') : null;

        if (isCrossWarehouse) {
            // Duplikat di gudang LAIN (bukan hanya draft ini)
            if (window.setScanStatus) {
                window.setScanStatus('error', 'Duplikat database: ' + sn + ' sudah terdaftar di sistem (gudang lain).');
            }
            if (alertMsg) {
                alertMsg.innerHTML = '<strong>DUPLIKAT LINTAS GUDANG!</strong> SN <span style="font-weight:700;">' + sn + '</span> sudah terdaftar di sistem (mungkin di gudang lain). Tanyakan ke tim Admin untuk kelanjutannya.';
            }
        } else {
            // Duplikat di draft sesi ini
            if (window.setScanStatus) {
                window.setScanStatus('error', 'Duplikat draft: ' + sn + ' sudah ada di daftar scan ini.');
            }
            if (alertMsg) {
                alertMsg.innerHTML = '<strong>DUPLIKAT DETEKSI!</strong> Serial Number <span style="font-weight:700;">' + sn + '</span> sudah ada di draft scan saat ini.';
            }
        }

        if (duplicateSnText) duplicateSnText.innerText = '';
        if (duplicateAlert) {
            duplicateAlert.style.display = 'flex';
            duplicateAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
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
            
            renderDevicePagination();
        }
        focusInput(true); // prevent scroll jump
        if (window.setScanStatus) {
            window.setScanStatus('idle', 'SN berhasil dihapus: ' + sn);
        }
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
        
        renderDevicePagination();
        
        if (window.setScanStatus) window.setScanStatus('idle', 'Draft dibersihkan — siap menerima scan baru.');
        focusInput(true); // prevent scroll jump
    });

    // Device Pagination Logic
    let deviceCurrentPage = 1;
    let devicePerPage = 10;
    
    document.getElementById('devicePerPage').addEventListener('change', function() {
        devicePerPage = parseInt(this.value);
        deviceCurrentPage = 1;
        renderDevicePagination();
    });

    document.getElementById('devicePrevBtn').addEventListener('click', function() {
        if(deviceCurrentPage > 1) {
            deviceCurrentPage--;
            renderDevicePagination();
        }
    });

    document.getElementById('deviceNextBtn').addEventListener('click', function() {
        const rows = draftTableBody.querySelectorAll('tr:not(#emptyRowPlaceholder)');
        const maxPage = Math.ceil(rows.length / devicePerPage);
        if(deviceCurrentPage < maxPage) {
            deviceCurrentPage++;
            renderDevicePagination();
        }
    });

    function renderDevicePagination() {
        const rows = draftTableBody.querySelectorAll('tr:not(#emptyRowPlaceholder)');
        const totalItems = rows.length;
        const wrap = document.getElementById('devicePaginationWrap');
        
        if (totalItems === 0) {
            if(wrap) wrap.style.display = 'none';
            return;
        }

        const maxPage = Math.ceil(totalItems / devicePerPage);
        if (deviceCurrentPage > maxPage) deviceCurrentPage = maxPage;
        if (deviceCurrentPage < 1) deviceCurrentPage = 1;

        const startIdx = (deviceCurrentPage - 1) * devicePerPage;
        const endIdx = startIdx + devicePerPage;

        rows.forEach((row, idx) => {
            if (idx >= startIdx && idx < endIdx) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });

        if (wrap) {
            wrap.style.display = totalItems > devicePerPage ? 'flex' : 'none';
        }
        
        const info = document.getElementById('devicePageInfo');
        if (info) info.textContent = `Halaman ${deviceCurrentPage} dari ${maxPage} (${totalItems} item)`;
        
        const prev = document.getElementById('devicePrevBtn');
        if (prev) prev.disabled = deviceCurrentPage <= 1;
        
        const next = document.getElementById('deviceNextBtn');
        if (next) next.disabled = deviceCurrentPage >= maxPage;
    }

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

    // Live counter mirrors #draftCount into the sticky panel (Device tab)
    (function mirrorDraftCount() {
        const dc = document.getElementById('draftCount');
        const lc = document.getElementById('liveCounter');
        if (dc && lc) {
            new MutationObserver(() => { lc.textContent = dc.textContent; })
                .observe(dc, { childList: true, characterData: true, subtree: true });
        }
    })();

    // Live counter untuk tab Aksesoris — mirror dari #accDraftCount
    (function mirrorAccDraftCount() {
        const acc = document.getElementById('accDraftCount');
        const lc  = document.getElementById('accLiveCounter');
        if (!acc || !lc) return;
        function extract() {
            // accDraftCount format: "N baris, M qty" — ambil nilai qty (M)
            const m = acc.textContent.match(/(\d+)\s*qty/);
            lc.textContent = m ? m[1] : '0';
        }
        new MutationObserver(extract).observe(acc, { childList: true, characterData: true, subtree: true });
        extract();
    })();

    // Live counter untuk tab SIM/GSM — menghitung pool checkbox + manual rows
    (function mirrorSimCount() {
        const lc = document.getElementById('simLiveCounter');
        if (!lc) return;

        function countSim() {
            const mode = (document.getElementById('sim_input_mode_hidden') || {}).value || 'pool';
            let count = 0;
            if (mode === 'pool') {
                const massDraftBody = document.getElementById('simMassDraftBody');
                if (massDraftBody) {
                    count = massDraftBody.querySelectorAll('tr:not(#simMassDraftEmpty)').length;
                }
            } else if (mode === 'manual') {
                const body = document.getElementById('simManualBody');
                if (body) {
                    count = body.querySelectorAll('tr:not(#simManualEmpty)').length;
                }
            }
            lc.textContent = count;
        }

        // Observe mass draft body DOM changes
        const massDraftBody = document.getElementById('simMassDraftBody');
        if (massDraftBody) {
            new MutationObserver(countSim).observe(massDraftBody, { childList: true, subtree: true });
        }
        // Observe manual body DOM changes
        const manualBody = document.getElementById('simManualBody');
        if (manualBody) {
            new MutationObserver(countSim).observe(manualBody, { childList: true });
        }
        // Also update when mode radio changes
        document.querySelectorAll('input[name="sim_mode_radio"]').forEach(r => {
            r.addEventListener('change', countSim);
        });
        countSim();
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
    // ACCESSORIES — New Logic (Opsi 1 & 2, Draft, Crosscheck, Save)
    // ============================================
    (function initAccessoryReceiving() {
        // References
        const accDraftTableBody   = document.getElementById('accDraftTableBody');
        const emptyAccRow         = document.getElementById('emptyAccRowPlaceholder');
        const accDraftCountEl     = document.getElementById('accDraftCount');
        const btnAddToDraft       = document.getElementById('btnAddAccToDraft');
        const btnProcessExcel     = document.getElementById('btnProcessAccExcel');
        const btnClearDraft       = document.getElementById('btnClearAccDraft');
        const btnCrosscheckAcc    = document.getElementById('btnCrosscheckAcc');
        const btnSaveAcc          = document.getElementById('btnSaveAcc');
        const btnSaveEditsAcc     = document.getElementById('btnSaveEditsAcc');
        const accTypeSelect       = document.getElementById('acc_type_select');
        const accCustomName       = document.getElementById('acc_custom_name');
        const accCustomCode       = document.getElementById('acc_custom_code');
        const accQtyInput         = document.getElementById('acc_qty_input');
        const accSnTextarea       = document.getElementById('acc_sn_textarea');
        const accExcelUpload      = document.getElementById('acc_excel_upload');
        const accWarehouseMirror  = document.getElementById('acc_warehouse_mirror');
        const accWarehouseSelect  = document.getElementById('warehouse_select_acc');
        const accHiddenContainer  = document.getElementById('accHiddenInputsContainer');

        // Draft data store: [{code, name, sns:[], qty}]
        let accDraftItems = [];
        const ACC_PAGE_SIZE = 10;
        let accCurrentPage = 1;

        // --- UTILITIES ---
        function updateDraftCount() {
            const total = accDraftItems.reduce((s, i) => s + i.qty, 0);
            if (accDraftCountEl) accDraftCountEl.textContent = accDraftItems.length + ' baris, ' + total + ' qty';
            if (btnCrosscheckAcc) {
                btnCrosscheckAcc.disabled = accDraftItems.length === 0;
                btnCrosscheckAcc.title = accDraftItems.length === 0 ? 'Tambahkan aksesoris ke draft terlebih dahulu' : '';
            }
            if (btnSaveAcc) btnSaveAcc.style.display = 'none'; // reset save on draft change
        }

        function renderDraftTable() {
            if (!accDraftTableBody) return;
            accDraftTableBody.innerHTML = '';
            if (accDraftItems.length === 0) {
                if (emptyAccRow) {
                    const emptyClone = emptyAccRow.cloneNode(true);
                    emptyClone.style.display = '';
                    accDraftTableBody.appendChild(emptyClone);
                }
                const paginWrap = document.getElementById('accDraftPaginationWrap');
                if (paginWrap) paginWrap.style.display = 'none';
                updateDraftCount();
                return;
            }

            const totalPages = Math.ceil(accDraftItems.length / ACC_PAGE_SIZE);
            if (accCurrentPage > totalPages) accCurrentPage = totalPages;
            const start = (accCurrentPage - 1) * ACC_PAGE_SIZE;
            const pageItems = accDraftItems.slice(start, start + ACC_PAGE_SIZE);

            pageItems.forEach((item, localIdx) => {
                const globalIdx = start + localIdx;
                const snDisplay = item.sns.length > 0 ? item.sns.join('<br>') : '<span style="color:var(--text-muted);font-size:11px;">—</span>';
                const tr = document.createElement('tr');
                tr.className = 'animate-fade-in';
                tr.innerHTML = `
                    <td style="font-size:12px;">${globalIdx + 1}</td>
                    <td style="font-weight:600; color:var(--accent-indigo); font-size:12px;">${item.code}</td>
                    <td style="font-size:12px;">${item.name}</td>
                    <td style="font-size:11px; line-height:1.6;">${snDisplay}</td>
                    <td style="text-align:center;">
                        <input type="number" class="form-control acc-draft-qty" data-idx="${globalIdx}" min="1" value="${item.qty}" style="width:70px; text-align:center; margin:0 auto; font-size:12px;">
                    </td>
                    <td style="text-align:center; font-size:11px; color:var(--text-muted);">${item.unit || 'pcs'}</td>
                    <td style="text-align:right;">
                        <button type="button" class="btn btn-danger btn-icon-sm acc-delete-btn" data-idx="${globalIdx}" style="padding:4px 8px; font-size:11px;">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                `;
                accDraftTableBody.appendChild(tr);
            });

            // Bind qty & delete events on this page
            accDraftTableBody.querySelectorAll('.acc-draft-qty').forEach(inp => {
                inp.addEventListener('input', function() {
                    const idx = parseInt(this.dataset.idx);
                    accDraftItems[idx].qty = parseInt(this.value) || 1;
                    updateDraftCount();
                    if (btnSaveEditsAcc) btnSaveEditsAcc.style.display = 'inline-block';
                    if (btnSaveAcc) btnSaveAcc.style.display = 'none';
                });
                inp.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.idx);
                    accDraftItems[idx].qty = parseInt(this.value) || 1;
                    updateDraftCount();
                    if (btnSaveEditsAcc) btnSaveEditsAcc.style.display = 'inline-block';
                    if (btnSaveAcc) btnSaveAcc.style.display = 'none';
                });
            });
            accDraftTableBody.querySelectorAll('.acc-delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.dataset.idx);
                    accDraftItems.splice(idx, 1);
                    if (accCurrentPage > Math.ceil(accDraftItems.length / ACC_PAGE_SIZE) && accCurrentPage > 1) accCurrentPage--;
                    renderDraftTable();
                    if (window.playBeep) window.playBeep('error');
                });
            });

            // Pagination
            const paginWrap = document.getElementById('accDraftPaginationWrap');
            const pageInfo  = document.getElementById('accDraftPageInfo');
            const prevBtn   = document.getElementById('accDraftPrevBtn');
            const nextBtn   = document.getElementById('accDraftNextBtn');
            if (paginWrap) {
                paginWrap.style.display = accDraftItems.length > ACC_PAGE_SIZE ? 'flex' : 'none';
                if (pageInfo) pageInfo.textContent = `Halaman ${accCurrentPage} dari ${totalPages} (${accDraftItems.length} item)`;
                if (prevBtn) prevBtn.disabled = accCurrentPage <= 1;
                if (nextBtn) nextBtn.disabled = accCurrentPage >= totalPages;
            }

            updateDraftCount();
        }

        // Pagination buttons
        const prevBtn = document.getElementById('accDraftPrevBtn');
        const nextBtn = document.getElementById('accDraftNextBtn');
        if (prevBtn) prevBtn.addEventListener('click', () => { if (accCurrentPage > 1) { accCurrentPage--; renderDraftTable(); } });
        if (nextBtn) nextBtn.addEventListener('click', () => { accCurrentPage++; renderDraftTable(); });

        // Clear draft
        if (btnClearDraft) {
            btnClearDraft.addEventListener('click', () => {
                if (!accDraftItems.length || confirm('Hapus semua data draft aksesoris?')) {
                    accDraftItems = [];
                    accCurrentPage = 1;
                    renderDraftTable();
                    if (btnSaveAcc) btnSaveAcc.style.display = 'none';
                }
            });
        }

        // Show/hide custom name & unit field based on selection
        const accUnitGroup = document.getElementById('acc_unit_group');
        const accUnitInput = document.getElementById('acc_unit_input');
        if (accTypeSelect) {
            accTypeSelect.addEventListener('change', function() {
                const isOther = this.value === 'OTHER';
                if (accCustomName) accCustomName.style.display = isOther ? 'block' : 'none';
                if (accCustomCode) accCustomCode.style.display = isOther ? 'block' : 'none';
                // Show unit selector only for OTHER; for known accessories auto-set from data-unit
                if (accUnitGroup) accUnitGroup.style.display = isOther ? 'block' : 'none';
                if (!isOther && this.value) {
                    const selOpt = this.options[this.selectedIndex];
                    const unit = (selOpt && selOpt.dataset.unit) ? selOpt.dataset.unit : 'pcs';
                    if (accUnitInput) accUnitInput.value = unit;
                }
            });
        }

        // --- OPSI 1: Add to Draft ---
        if (btnAddToDraft) {
            btnAddToDraft.addEventListener('click', function() {
                const typeVal  = accTypeSelect ? accTypeSelect.value : '';
                const qty      = parseInt(accQtyInput ? accQtyInput.value : 1) || 1;

                let code, name;
                if (typeVal === 'OTHER') {
                    name = accCustomName ? accCustomName.value.trim() : '';
                    code = accCustomCode && accCustomCode.value.trim()
                        ? accCustomCode.value.trim().toUpperCase()
                        : 'OTHER-' + Date.now();
                    if (!name) { alert('Masukkan nama aksesoris untuk pilihan Lain-lain.'); return; }
                } else if (typeVal) {
                    const selOpt = accTypeSelect.options[accTypeSelect.selectedIndex];
                    code = typeVal;
                    name = selOpt ? selOpt.dataset.name || selOpt.text : typeVal;
                } else {
                    alert('Pilih jenis aksesoris terlebih dahulu.'); return;
                }

                // Parse SNs from textarea
                const snLines = accSnTextarea ? accSnTextarea.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean) : [];

                // Determine unit
                let unit = 'pcs';
                if (typeVal === 'OTHER') {
                    unit = accUnitInput ? accUnitInput.value || 'pcs' : 'pcs';
                } else {
                    const selOpt = accTypeSelect ? accTypeSelect.options[accTypeSelect.selectedIndex] : null;
                    unit = (selOpt && selOpt.dataset.unit) ? selOpt.dataset.unit : 'pcs';
                }

                // Check if code already in draft → merge
                const existing = accDraftItems.find(i => i.code === code);
                if (existing) {
                    existing.qty += qty;
                    existing.sns = [...new Set([...existing.sns, ...snLines])];
                } else {
                    accDraftItems.push({ code, name, sns: snLines, qty, unit });
                }

                renderDraftTable();
                if (window.playBeep) window.playBeep('success');

                // Reset form
                if (accTypeSelect) accTypeSelect.value = '';
                if (accCustomName) { accCustomName.value = ''; accCustomName.style.display = 'none'; }
                if (accCustomCode) { accCustomCode.value = ''; accCustomCode.style.display = 'none'; }
                if (accUnitGroup) accUnitGroup.style.display = 'none';
                if (accQtyInput) accQtyInput.value = 1;
                if (accSnTextarea) accSnTextarea.value = '';
            });
        }

        // --- OPSI 2: Process Excel ---
        if (btnProcessExcel && accExcelUpload) {
            btnProcessExcel.addEventListener('click', function() {
                if (!accExcelUpload.files.length) { alert('Pilih file Excel / CSV terlebih dahulu.'); return; }
                if (typeof XLSX === 'undefined') { alert('Pustaka XLSX belum termuat.'); return; }
                const reader = new FileReader();
                reader.onload = function(ev) {
                    try {
                        const wb   = XLSX.read(ev.target.result, { type: 'array' });
                        const ws   = wb.Sheets[wb.SheetNames[0]];
                        const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false });
                        if (!rows.length) { alert('File kosong atau tidak bisa dibaca.'); return; }

                        const headers = rows[0].map(h => String(h||'').trim().toLowerCase());
                        const colCode = headers.findIndex(h => h.includes('code') || h.includes('kode'));
                        const colName = headers.findIndex(h => h.includes('name') || h.includes('nama'));
                        const colSn   = headers.findIndex(h => h.includes('sn') || h.includes('serial'));
                        const colQty  = headers.findIndex(h => h.includes('qty') || h.includes('jumlah'));

                        let added = 0;
                        rows.slice(1).forEach(row => {
                            const code = String(row[colCode] || '').trim();
                            const name = String(row[colName] || code).trim();
                            const sn   = String(row[colSn] || '').trim();
                            const qty  = parseInt(row[colQty] || 1) || 1;
                            if (!code) return;

                            const existing = accDraftItems.find(i => i.code === code);
                            if (existing) {
                                existing.qty += qty;
                                if (sn) existing.sns = [...new Set([...existing.sns, sn])];
                            } else {
                                accDraftItems.push({ code, name, sns: sn ? [sn] : [], qty });
                            }
                            added++;
                        });

                        renderDraftTable();
                        if (window.playBeep) window.playBeep('success');
                        alert(`${added} baris berhasil ditambahkan ke draft.`);
                        accExcelUpload.value = '';
                    } catch(err) {
                        console.error(err);
                        alert('Gagal membaca file: ' + err.message);
                    }
                };
                reader.readAsArrayBuffer(accExcelUpload.files[0]);
            });
        }

        // Mirror warehouse selection
        if (accWarehouseSelect) {
            accWarehouseSelect.addEventListener('change', function() {
                if (accWarehouseMirror) accWarehouseMirror.value = this.value;
            });
            if (accWarehouseMirror) accWarehouseMirror.value = accWarehouseSelect.value;
        }

        // Mode toggle (Opsi 1 / Opsi 2)
        document.querySelectorAll('input[name="acc_mode_radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.acc-mode-lbl').forEach(lbl => {
                    lbl.classList.remove('active');
                    lbl.style.borderColor = 'var(--border-color)';
                    lbl.style.background  = 'none';
                });
                const parent = this.closest('.acc-mode-lbl');
                if (parent) {
                    parent.classList.add('active');
                    parent.style.borderColor = 'var(--accent-indigo)';
                    parent.style.background  = 'rgba(99,102,241,0.1)';
                }
                const accCardManual = document.getElementById('accCardManual');
                const accCardExcel  = document.getElementById('accCardExcel');
                if (this.value === 'manual') {
                    if (accCardManual) accCardManual.style.display = 'block';
                    if (accCardExcel)  accCardExcel.style.display  = 'none';
                } else {
                    if (accCardManual) accCardManual.style.display = 'none';
                    if (accCardExcel)  accCardExcel.style.display  = 'block';
                }
            });
        });

        // --- DO FILE for Crosscheck ---
        let doAccMap = {}; // { code: qty }
        const accDoFileInput = document.getElementById('acc_do_file_input');
        const accDoFileName  = document.getElementById('accDoFileName');
        const accDoClearBtn  = document.getElementById('accDoClearBtn');

        if (accDoFileInput) {
            accDoFileInput.addEventListener('change', function(e) {
                const file = e.target.files && e.target.files[0];
                if (!file) return;
                if (typeof XLSX === 'undefined') { alert('Pustaka XLSX belum termuat.'); return; }
                const reader = new FileReader();
                reader.onload = function(ev) {
                    try {
                        const wb   = XLSX.read(ev.target.result, { type: 'array' });
                        const ws   = wb.Sheets[wb.SheetNames[0]];
                        const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false });
                        const headers = (rows[0] || []).map(h => String(h||'').trim().toLowerCase());
                        const colCode = headers.findIndex(h => h.includes('code') || h.includes('kode'));
                        const colQty  = headers.findIndex(h => h.includes('qty') || h.includes('jumlah'));
                        doAccMap = {};
                        rows.slice(1).forEach(row => {
                            const code = String(row[colCode >= 0 ? colCode : 0] || '').trim();
                            const qty  = parseInt(row[colQty >= 0 ? colQty : 1] || 0);
                            if (code) doAccMap[code] = (doAccMap[code] || 0) + qty;
                        });
                        const total = Object.values(doAccMap).reduce((s,v) => s+v, 0);
                        if (accDoFileName) accDoFileName.textContent = file.name + ' (' + Object.keys(doAccMap).length + ' kode, ' + total + ' qty)';
                        if (accDoClearBtn) accDoClearBtn.style.display = 'inline';
                        if (window.playBeep) window.playBeep('success');
                    } catch(err) { alert('Gagal membaca DO: ' + err.message); }
                };
                reader.readAsArrayBuffer(file);
            });
        }
        if (accDoClearBtn) {
            accDoClearBtn.addEventListener('click', function() {
                doAccMap = {};
                if (accDoFileInput) accDoFileInput.value = '';
                if (accDoFileName) accDoFileName.textContent = 'Pilih file Excel / CSV DO';
                accDoClearBtn.style.display = 'none';
                const panel = document.getElementById('accDoComparePanel');
                if (panel) panel.style.display = 'none';
                if (btnSaveAcc) btnSaveAcc.style.display = 'none';
            });
        }

        // --- CROSSCHECK BUTTON ---
        if (btnCrosscheckAcc) {
            btnCrosscheckAcc.addEventListener('click', function() {
                if (!accDraftItems.length) { alert('Draft masih kosong.'); return; }

                const panel = document.getElementById('accDoComparePanel');

                // If no DO file: just show save button directly
                if (!Object.keys(doAccMap).length) {
                    if (panel) panel.style.display = 'none';
                    if (btnSaveAcc) {
                        btnSaveAcc.style.display = 'flex';
                        btnSaveAcc.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    if (window.playBeep) window.playBeep('success');
                    return;
                }

                // Build draft map
                const draftMap = {};
                accDraftItems.forEach(i => { draftMap[i.code] = (draftMap[i.code] || 0) + i.qty; });

                const allCodes = new Set([...Object.keys(doAccMap), ...Object.keys(draftMap)]);
                let doTotal = 0, draftTotal = 0, okCount = 0, diffCount = 0;
                let rows = '';
                allCodes.forEach(code => {
                    const doQty    = doAccMap[code] || 0;
                    const draftQty = draftMap[code] || 0;
                    doTotal    += doQty;
                    draftTotal += draftQty;
                    const match = doQty === draftQty;
                    if (match) okCount++; else diffCount++;
                    rows += `<tr>
                        <td>${code}</td>
                        <td style="text-align:center;">${doQty}</td>
                        <td style="text-align:center;">${draftQty}</td>
                        <td style="text-align:center;">
                            ${match ? '<span style="color:var(--accent-emerald);font-weight:600;">✓ Cocok</span>' : '<span style="color:var(--danger-color);font-weight:600;">✗ Beda</span>'}
                        </td>
                    </tr>`;
                });

                document.getElementById('accDoTotal').textContent    = doTotal;
                document.getElementById('accDoScanTotal').textContent = draftTotal;
                document.getElementById('accDoOk').textContent       = okCount;
                document.getElementById('accDoDiff').textContent     = diffCount;
                document.getElementById('accDoCompareBody').innerHTML = rows;

                const badge = document.getElementById('accDoMatchBadge');
                const isMatch = diffCount === 0;
                if (badge) {
                    badge.style.background = isMatch ? 'rgba(16,185,129,0.15)' : 'rgba(245,158,11,0.15)';
                    badge.style.color      = isMatch ? 'var(--accent-emerald)' : 'var(--accent-amber)';
                    badge.innerHTML = isMatch
                        ? '<i class="fa-solid fa-circle-check"></i> Cocok 100% — semua kode & qty sesuai DO'
                        : '<i class="fa-solid fa-triangle-exclamation"></i> Ada selisih pada ' + diffCount + ' kode — edit draft lalu crosscheck kembali';
                }

                if (panel) panel.style.display = 'block';

                // Show save only if matched
                if (btnSaveAcc) {
                    btnSaveAcc.style.display = isMatch ? 'flex' : 'none';
                    if (isMatch) btnSaveAcc.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                if (window.playBeep) window.playBeep(isMatch ? 'success' : 'error');
            });
        }

        // --- SAVE EDITS BUTTON: trigger crosscheck & hide self ---
        if (btnSaveEditsAcc) {
            btnSaveEditsAcc.addEventListener('click', function() {
                if (btnCrosscheckAcc) btnCrosscheckAcc.click();
                this.style.display = 'none';
            });
        }

        // --- SAVE BUTTON: inject hidden inputs then submit ---
        if (btnSaveAcc) {
            btnSaveAcc.addEventListener('click', function() {
                // Mirror warehouse
                const wh = accWarehouseSelect ? accWarehouseSelect.value : '';
                if (!wh) { alert('Pilih gudang tujuan terlebih dahulu.'); return; }
                if (accWarehouseMirror) accWarehouseMirror.value = wh;

                // Inject hidden inputs for acc_types, acc_qtys, acc_names, acc_sns
                if (accHiddenContainer) {
                    accHiddenContainer.innerHTML = '';
                    accDraftItems.forEach(item => {
                        const addHidden = (name, val) => {
                            const inp = document.createElement('input');
                            inp.type  = 'hidden';
                            inp.name  = name;
                            inp.value = val;
                            accHiddenContainer.appendChild(inp);
                        };
                        addHidden('acc_types[]', item.code);
                        addHidden('acc_qtys[]',  item.qty);
                        addHidden('acc_names[]', item.name);
                        addHidden('acc_units[]', item.unit || 'pcs');
                        item.sns.forEach(sn => addHidden('acc_sns[' + item.code + '][]', sn));
                    });
                }

                const form = document.getElementById('receivingAccessoryForm');
                if (form) form.submit();
            });
        }

        // Initial render
        renderDraftTable();
    })();




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

    // -- Global Provider & Kategori selector untuk Opsi B --
    const simGlobalProvider  = document.getElementById('sim_global_provider');
    const simGlobalCategory  = document.getElementById('sim_global_category');

    // Data kategori dari Master Data
    const simProviderCategoriesData = @json($simProviderCategories ?? []);

    function updateGlobalCategoryOptions() {
        if (!simGlobalProvider || !simGlobalCategory) return;
        const provider = simGlobalProvider.value;
        const currentVal = simGlobalCategory.value;
        simGlobalCategory.innerHTML = '<option value="">-- Pilih Kategori --</option>';
        if (provider && simProviderCategoriesData[provider]) {
            const categories = simProviderCategoriesData[provider];
            categories.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                if (cat === currentVal) opt.selected = true;
                simGlobalCategory.appendChild(opt);
            });
        }
        updateGlobalSelectionBadge();
    }
    
    // Update Mass Category based on provider as well
    const simMassProvider = document.getElementById('sim_mass_provider');
    const simMassCategory = document.getElementById('sim_mass_category');
    function updateMassCategoryOptions() {
        if (!simMassProvider || !simMassCategory) return;
        const provider = simMassProvider.value;
        const currentVal = simMassCategory.value;
        simMassCategory.innerHTML = '<option value="">-- Pilih Kategori --</option>';
        if (provider && simProviderCategoriesData[provider]) {
            const categories = simProviderCategoriesData[provider];
            categories.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                if (cat === currentVal) opt.selected = true;
                simMassCategory.appendChild(opt);
            });
        }
    }
    if (simMassProvider) {
        simMassProvider.addEventListener('change', updateMassCategoryOptions);
        updateMassCategoryOptions();
    }

    function updateGlobalSelectionBadge() { /* badge removed */ }

    if (simGlobalProvider) {
        simGlobalProvider.addEventListener('change', updateGlobalCategoryOptions);
        updateGlobalCategoryOptions(); // init on load
    }

    function providerOptions(selected) {
        let html = '';
        simProvidersData.forEach(p => {
            html += `<option value="${p}" ${p === selected ? 'selected' : ''}>${p}</option>`;
        });
        return html;
    }

    function updateSimManualBadge() {
        const rows = simManualBody ? simManualBody.querySelectorAll('tr:not(#simManualEmpty)') : [];
        const badge = document.getElementById('simManualCountBadge');
        if (badge) badge.textContent = rows.length + ' ter-scan';
        // Update nomor urut
        rows.forEach((r, i) => {
            const noCell = r.cells[0];
            if (noCell && noCell.classList.contains('sim-no-cell')) noCell.textContent = i + 1;
        });
    }

    function setSimScanStatus(state, msg) {
        const txt = document.getElementById('simScanStatusText');
        const bar = document.getElementById('simScanStatusBar');
        if (txt) txt.textContent = msg;
        if (bar) {
            bar.style.background = state === 'success'
                ? 'linear-gradient(90deg, rgba(16,185,129,0.15), rgba(16,185,129,0.06))'
                : state === 'error'
                    ? 'linear-gradient(90deg, rgba(244,63,94,0.12), rgba(244,63,94,0.06))'
                    : 'linear-gradient(90deg, rgba(99,102,241,0.08), rgba(59,130,246,0.06))';
        }
    }

    // Set untuk track MSISDN yang sudah ada di draft Mode B sesi ini
    const simManualDraftSet = new Set();

    window.addSimRow = function (msisdn = '') {
        // Jika ada MSISDN yang diberikan, cek duplikat dulu
        if (msisdn) {
            const cleanMsisdn = String(msisdn).trim().toUpperCase();

            // Cek duplikat dalam draft Mode B sesi ini
            if (simManualDraftSet.has(cleanMsisdn)) {
                setSimScanStatus('error', 'Duplikat draft: ' + cleanMsisdn + ' sudah ada di daftar scan ini.');
                if (window.playBeep) window.playBeep('error');
                return false;
            }

            // Cek duplikat global (lintas gudang, dari DB)
            if (dbMsisdnsSet.has(cleanMsisdn)) {
                setSimScanStatus('error', 'Duplikat DB: ' + cleanMsisdn + ' — tanya tim admin.');
                if (window.playBeep) window.playBeep('error');
                // Tampilkan alert khusus cross-warehouse
                if (!window._simDupeCrossWhShown) {
                    window._simDupeCrossWhShown = true;
                    setTimeout(() => {
                        alert('⚠️ No GSM duplikat lintas gudang!\n\nMSISDN: ' + cleanMsisdn + '\n\nNo GSM ini sudah terdaftar di sistem (mungkin di gudang lain). Tanyakan ke tim Admin untuk kelanjutannya.');
                        window._simDupeCrossWhShown = false;
                    }, 100);
                }
                return false;
            }

            simManualDraftSet.add(cleanMsisdn);
        }

        if (simManualEmpty) simManualEmpty.style.display = 'none';
        simRowSeq++;
        const id = 'sim-row-' + simRowSeq;
        const tr = document.createElement('tr');
        tr.id = id;
        tr.dataset.msisdn = String(msisdn).trim().toUpperCase();
        const rowNum = simManualBody ? simManualBody.querySelectorAll('tr:not(#simManualEmpty)').length + 1 : 1;

        // Ambil provider & kategori dari selector global
        const selectedProvider = simGlobalProvider ? simGlobalProvider.value : 'Telkomsel';
        const selectedCategory = simGlobalCategory ? simGlobalCategory.value : 'B2B';

        tr.innerHTML = `
            <td class="sim-no-cell" style="font-weight:600; color:var(--text-muted); font-size:13px;">${rowNum}</td>
            <td><input type="text" name="sim_msisdns[]" class="form-control" value="${msisdn}" placeholder="MSISDN" required style="font-weight:600; color:var(--accent-indigo);"></td>
            <td style="font-size:13px; font-weight:600; color:var(--accent-indigo);">
                ${selectedProvider}
                <input type="hidden" name="sim_providers[]" value="${selectedProvider}">
            </td>
            <td>
                <span class="badge badge-info" style="font-size:12px; padding:4px 10px;">${selectedCategory}</span>
                <input type="hidden" name="sim_categories[]" value="${selectedCategory}">
            </td>
            <td style="text-align:right;">
                <button type="button" class="btn btn-danger btn-icon-sm" style="padding:4px 8px; font-size:11px;" onclick="removeSimRow('${id}')"><i class="fa-solid fa-trash"></i></button>
            </td>`;
        simManualBody.appendChild(tr);

        updateSimManualBadge();

        if (msisdn) setSimScanStatus('success', 'Tersimpan: ' + msisdn + ' [' + selectedProvider + ' - ' + selectedCategory + '] — total ' + simManualBody.querySelectorAll('tr:not(#simManualEmpty)').length + ' MSISDN.');

        return tr;
    };

    window.removeSimRow = function (id) {
        const row = document.getElementById(id);
        if (row) {
            // Hapus dari set tracking agar bisa di-input ulang
            const msisdn = row.dataset.msisdn;
            if (msisdn) simManualDraftSet.delete(msisdn);
            row.remove();
        }
        if (simManualBody && simManualBody.querySelectorAll('tr:not(#simManualEmpty)').length === 0 && simManualEmpty) {
            simManualEmpty.style.display = 'table-row';
        }
        updateSimManualBadge();
    };


    // Tambah tombol "Tambah Baris" ke card header Opsi B secara dinamis
    (function addManualSimBtn() {
        const header = document.querySelector('#simCardManual .card-header');
        if (!header) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline';
        btn.style.cssText = 'padding:6px 12px; font-size:12px;';
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Baris';
        btn.addEventListener('click', () => { addSimRow(); if(simScanInput) simScanInput.focus(); });
        header.appendChild(btn);
    })();

    if (simScanInput) {
        function processMassalSim() {
            const text = simScanInput.value.trim();
            if (text === '') return;

            // Validasi: pastikan provider & kategori sudah dipilih (Opsi B)
            const globalProv = simGlobalProvider ? simGlobalProvider.value : '';
            const globalCat  = simGlobalCategory ? simGlobalCategory.value : '';
            if (!globalProv) {
                setSimScanStatus('error', 'Pilih Provider GSM di Langkah 1 sebelum scan.');
                if (window.playBeep) window.playBeep('error');
                if (simGlobalProvider) simGlobalProvider.focus();
                return;
            }
            if (!globalCat) {
                setSimScanStatus('error', 'Pilih Kategori GSM di Langkah 1 sebelum scan.');
                if (window.playBeep) window.playBeep('error');
                if (simGlobalCategory) simGlobalCategory.focus();
                return;
            }
            
            // Pisahkan berdasarkan baris baru, spasi, atau koma (mendukung scanner Enter + paste massal)
            const lines = text.split(/[\s,;]+/);
            let added = 0;
            let draftDupes = [];
            let crossWhDupes = [];

            lines.forEach(line => {
                let v = line.trim();
                if (v !== '') {
                    // Ekstrak MSISDN dari URL panjang (ambil 10-14 digit berawalan 08 atau 628 di akhir)
                    const match = v.match(/(?:08|628)\d{8,12}$/);
                    if (match) v = match[0];

                    const cleanV = String(v).trim().toUpperCase();

                    // Cek duplikat dalam draft sesi ini dulu
                    if (simManualDraftSet.has(cleanV)) {
                        draftDupes.push(cleanV);
                        return;
                    }
                    // Cek duplikat global (lintas gudang DB)
                    if (dbMsisdnsSet.has(cleanV)) {
                        crossWhDupes.push(cleanV);
                        return;
                    }

                    const result = addSimRow(v);
                    if (result !== false) added++;
                }
            });
            
            if (added > 0 && window.playBeep) window.playBeep('success');
            simScanInput.value = '';
            simScanInput.focus();

            // Tampilkan notifikasi duplikat Mode B
            let msgs = [];
            if (draftDupes.length > 0) {
                msgs.push(draftDupes.length + ' No GSM duplikat di draft saat ini: ' + draftDupes.slice(0, 5).join(', ') + (draftDupes.length > 5 ? '...' : ''));
            }
            if (crossWhDupes.length > 0) {
                if (window.playBeep) window.playBeep('error');
                msgs.push(crossWhDupes.length + ' No GSM duplikat, tanya tim admin untuk kelanjutannya: ' + crossWhDupes.slice(0, 5).join(', ') + (crossWhDupes.length > 5 ? '...' : ''));
            }
            if (msgs.length > 0) {
                alert('⚠️ Peringatan Duplikat GSM:\n\n' + msgs.join('\n\n'));
            }
        }

        const btnProcess = document.getElementById('process_massal_sim');
        if (btnProcess) btnProcess.addEventListener('click', processMassalSim);
        
        // Scanner: otomatis proses saat tekan Enter (seperti tab Device)
        simScanInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processMassalSim();
            }
        });

        // Auto-focus input SIM saat mode B aktif
        document.querySelectorAll('input[name="sim_mode_radio"]').forEach(r => {
            r.addEventListener('change', function() {
                if (this.value === 'manual') {
                    setTimeout(() => simScanInput && simScanInput.focus(), 100);
                }
            });
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

        // Export render to window so it can be called manually
        window.renderSimDoComparison = render;
    })();

    // ============================================
    // SIM GSM Mode Toggling & 2-Step Submit
    // ============================================
    (function initSimModeToggling() {
        const simModeRadios = document.querySelectorAll('input[name="sim_mode_radio"]');
        const simModeLabels = document.querySelectorAll('.sim-mode-lbl');
        const simCardPool = document.getElementById('simCardPool');
        const simCardManual = document.getElementById('simCardManual');
        const simCardCsv = document.getElementById('simCardCsv');
        const simInputModeHidden = document.getElementById('sim_input_mode_hidden');

        const btnCrosscheckSim = document.getElementById('btnCrosscheckSim');
        const btnSubmitSim = document.getElementById('btnSubmitSim');
        const simDoComparePanel = document.getElementById('simDoComparePanel');

        simModeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Update labels styling
                simModeLabels.forEach(lbl => {
                    lbl.classList.remove('active');
                    lbl.style.borderColor = 'var(--border-color)';
                    lbl.style.background = 'none';
                });
                const parent = this.closest('.sim-mode-lbl');
                if (parent) {
                    parent.classList.add('active');
                    parent.style.borderColor = 'var(--accent-blue)';
                    parent.style.background = 'rgba(59, 130, 246, 0.1)';
                }

                // Hide all cards
                if (simCardPool) simCardPool.style.display = 'none';
                if (simCardManual) simCardManual.style.display = 'none';
                if (simCardCsv) simCardCsv.style.display = 'none';

                // Show selected card and update hidden input
                if (simInputModeHidden) simInputModeHidden.value = this.value;
                if (this.value === 'pool' && simCardPool) simCardPool.style.display = 'block';
                if (this.value === 'manual' && simCardManual) simCardManual.style.display = 'block';
                if (this.value === 'csv' && simCardCsv) simCardCsv.style.display = 'block';

                // We removed the crosscheck, so submit button is always visible
                if (btnSubmitSim) {
                    btnSubmitSim.style.display = 'flex';
                }

                // Enable the Crosscheck button now that a mode has been selected
                if (btnCrosscheckSim) {
                    btnCrosscheckSim.disabled = false;
                    btnCrosscheckSim.title = '';
                }
            });
        });

        if (btnSubmitSim) {
            btnSubmitSim.addEventListener('click', function(e) {
                const mode = simInputModeHidden ? simInputModeHidden.value : 'manual';
                let hasData = false;
                
                if (mode === 'pool') {
                    hasData = document.querySelectorAll('#simMassDraftBody tr:not(#simMassDraftEmpty)').length > 0;
                    if (!hasData) {
                        e.preventDefault();
                        alert('Preview draft massal masih kosong. Tambahkan MSISDN terlebih dahulu.');
                    }
                } else if (mode === 'manual') {
                    hasData = document.querySelectorAll('input[name="sim_msisdns[]"]').length > 0;
                    if (!hasData) {
                        e.preventDefault();
                        alert('Input minimal satu MSISDN manual.');
                    }
                } else if (mode === 'csv') {
                    const csvInput = document.querySelector('input[name="csv_file"]');
                    hasData = csvInput && csvInput.files.length > 0;
                    if (!hasData) {
                        e.preventDefault();
                        alert('Pilih file CSV untuk diupload.');
                    }
                }
            });
        }
    })();

    // ============================================
    // SIM GSM Mass Input (Opsi A) Logic
    // ============================================
    (function initSimMassInput() {
        const btnToDraft = document.getElementById('btnSimMassToDraft');
        const btnClear = document.getElementById('btnClearSimMassDraft');
        const textarea = document.getElementById('sim_mass_textarea');
        const tbody = document.getElementById('simMassDraftBody');
        const emptyRow = document.getElementById('simMassDraftEmpty');
        const countSpan = document.getElementById('simDraftMassCount');
        
        let simMassSeq = 0;

        function updateMassCount() {
            if (countSpan && tbody) {
                countSpan.textContent = tbody.querySelectorAll('tr:not(#simMassDraftEmpty)').length;
            }
        }

        window.removeSimMassRow = function(id) {
            const row = document.getElementById(id);
            if (row) row.remove();
            if (tbody && tbody.querySelectorAll('tr:not(#simMassDraftEmpty)').length === 0 && emptyRow) {
                emptyRow.style.display = 'table-row';
            }
            updateMassCount();
        };

        // Track MSISDNs already in this mass draft (to prevent same-session duplicates)
        const simMassDraftSet = new Set();

        if (btnToDraft && textarea && tbody) {
            btnToDraft.addEventListener('click', function() {
                const text = textarea.value.trim();
                if (!text) return;

                const provider = document.getElementById('sim_mass_provider').value;
                const category = document.getElementById('sim_mass_category').value;
                const rack = document.getElementById('rack_barcode_hidden_sim') ? document.getElementById('rack_barcode_hidden_sim').value : '';

                // Validasi provider & kategori
                if (!provider) {
                    alert('⚠️ Pilih Provider GSM terlebih dahulu sebelum menambahkan ke draft.');
                    document.getElementById('sim_mass_provider').focus();
                    return;
                }
                if (!category) {
                    alert('⚠️ Pilih Kategori GSM terlebih dahulu sebelum menambahkan ke draft.');
                    document.getElementById('sim_mass_category').focus();
                    return;
                }

                const sns = text.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
                let added = 0;
                let draftDupes = [];      // duplikat di draft ini
                let crossWhDupes = [];    // duplikat di DB (gudang lain)

                sns.forEach(sn => {
                    const cleanSn = sn.replace(/[^a-zA-Z0-9\-]/g, '').trim().toUpperCase();
                    if (!cleanSn) return;

                    // Cek duplikat dalam draft sesi ini
                    if (simMassDraftSet.has(cleanSn)) {
                        draftDupes.push(cleanSn);
                        return;
                    }

                    // Cek duplikat global (lintas gudang, dari DB)
                    if (dbMsisdnsSet.has(cleanSn)) {
                        crossWhDupes.push(cleanSn);
                        return;
                    }

                    simMassDraftSet.add(cleanSn);
                    if (emptyRow) emptyRow.style.display = 'none';
                    simMassSeq++;
                    const id = 'sim-mass-row-' + simMassSeq;
                    const tr = document.createElement('tr');
                    tr.id = id;
                    
                    tr.innerHTML = `
                        <td style="font-weight:600; color:var(--text-muted); font-size:13px;">${simMassSeq}</td>
                        <td>
                            <input type="text" name="sim_msisdns[]" class="form-control" value="${cleanSn}" readonly style="font-weight:600; color:var(--accent-indigo); background:transparent; border:none; padding:0;">
                        </td>
                        <td style="font-size:13px; font-weight:600;">
                            ${provider}
                            <input type="hidden" name="sim_providers[]" value="${provider}">
                        </td>
                        <td>
                            <span class="badge badge-info" style="font-size:12px; padding:4px 10px;">${category}</span>
                            <input type="hidden" name="sim_categories[]" value="${category}">
                        </td>
                        <td class="draft-rack-cell-sim">
                            ${rack ? rack : '<span style="color:var(--text-muted);">—</span>'}
                            <input type="hidden" name="rack_barcodes[]" class="rack-barcode-per-sim" value="${rack}">
                        </td>
                        <td style="text-align:right;">
                            <button type="button" class="btn btn-danger btn-icon-sm" style="padding:4px 8px; font-size:11px;" onclick="removeSimMassRow('${id}')"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                    added++;
                });

                if (added > 0) {
                    textarea.value = '';
                    updateMassCount();
                    if (window.playBeep) window.playBeep('success');
                }

                // Tampilkan notifikasi duplikat
                let msgs = [];
                if (draftDupes.length > 0) {
                    msgs.push(draftDupes.length + ' No GSM duplikat di draft saat ini (diabaikan): ' + draftDupes.slice(0, 5).join(', ') + (draftDupes.length > 5 ? '...' : ''));
                }
                if (crossWhDupes.length > 0) {
                    if (window.playBeep) window.playBeep('error');
                    msgs.push(crossWhDupes.length + ' No GSM duplikat, tanya tim admin untuk kelanjutannya: ' + crossWhDupes.slice(0, 5).join(', ') + (crossWhDupes.length > 5 ? '...' : ''));
                }
                if (msgs.length > 0) {
                    alert('⚠️ Peringatan Duplikat GSM:\n\n' + msgs.join('\n\n'));
                }
            });
        }

        if (btnClear && tbody) {
            btnClear.addEventListener('click', function() {
                tbody.querySelectorAll('tr:not(#simMassDraftEmpty)').forEach(r => r.remove());
                if (emptyRow) emptyRow.style.display = 'table-row';
                updateMassCount();
                simMassSeq = 0;
            });
        }

    })();

    // ============================================
    // RACK BARCODE SCAN (Lokasi Penyimpanan Rak)
    // ============================================
    let currentRack = '';

    function applyRackToDraft(rack) {
        const hidden = document.getElementById('rack_barcode_hidden');
        if (hidden) hidden.value = rack;
        const badge = document.getElementById('rack_badge');
        const badgeText = document.getElementById('rack_badge_text');
        if (badge) badge.style.display = rack ? 'block' : 'none';
        if (badgeText) badgeText.textContent = rack;
        document.querySelectorAll('.draft-rack-cell').forEach(cell => {
            cell.childNodes[0].textContent = rack || '';
            if (!rack) cell.innerHTML = '<span style="color:var(--text-muted);">—</span><input type="hidden" name="rack_barcodes[]" class="rack-barcode-per-sn" value="">';
        });
        document.querySelectorAll('input.rack-barcode-per-sn').forEach(inp => { inp.value = rack; });
    }

    window.clearRack = function() {
        currentRack = '';
        applyRackToDraft('');
    };

    const rackInputDev = document.getElementById('rack_barcode_input');
    if (rackInputDev) {
        rackInputDev.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) {
                    currentRack = val;
                    applyRackToDraft(val);
                    if (window.playBeep) window.playBeep('success');
                    if (window.setScanStatus) window.setScanStatus('success', 'Rak tujuan: ' + val + ' — siap scan device.');
                }
                this.value = '';
                if (barcodeInput) barcodeInput.focus();
            }
        });
    }

    // Aksesoris rack
    const rackInputAcc = document.getElementById('rack_barcode_input_acc');
    if (rackInputAcc) {
        rackInputAcc.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) {
                    const hidden = document.getElementById('rack_barcode_hidden_acc');
                    if (hidden) hidden.value = val;
                    const badge = document.getElementById('rack_badge_acc');
                    const badgeText = document.getElementById('rack_badge_text_acc');
                    if (badge) badge.style.display = 'block';
                    if (badgeText) badgeText.textContent = val;
                    if (window.playBeep) window.playBeep('success');
                }
                this.value = '';
            }
        });
    }

    // GSM rack
    const rackInputSim = document.getElementById('rack_barcode_input_sim');
    if (rackInputSim) {
        rackInputSim.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) {
                    const hidden = document.getElementById('rack_barcode_hidden_sim');
                    if (hidden) hidden.value = val;
                    const badge = document.getElementById('rack_badge_sim');
                    const badgeText = document.getElementById('rack_badge_text_sim');
                    if (badge) badge.style.display = 'block';
                    if (badgeText) badgeText.textContent = val;
                    
                    // Update any existing SIM mass draft rows
                    document.querySelectorAll('.draft-rack-cell-sim').forEach(cell => {
                        cell.childNodes[0].textContent = val || '';
                        if (!val) cell.innerHTML = '<span style="color:var(--text-muted);">—</span><input type="hidden" name="rack_barcodes[]" class="rack-barcode-per-sim" value="">';
                    });
                    document.querySelectorAll('input.rack-barcode-per-sim').forEach(inp => { inp.value = val; });

                    if (window.playBeep) window.playBeep('success');
                }
                this.value = '';
            }
        });
    }
    // ============================================
    // REAL-TIME CLOCK (Waktu Penerimaan)
    // ============================================
    (function initReceivingClocks() {
        const DAYS    = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const MONTHS  = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

        function tick() {
            const now = new Date();
            const day   = DAYS[now.getDay()];
            const date  = now.getDate().toString().padStart(2, '0');
            const month = MONTHS[now.getMonth()];
            const year  = now.getFullYear();
            const h     = now.getHours().toString().padStart(2, '0');
            const m     = now.getMinutes().toString().padStart(2, '0');
            const s     = now.getSeconds().toString().padStart(2, '0');
            const str   = `${day}, ${date} ${month} ${year}  •  ${h}:${m}:${s} WIB`;

            const ids = ['receivingClock_dev', 'receivingClock_acc', 'receivingClock_sim'];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = str;
            });
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>
@endsection
