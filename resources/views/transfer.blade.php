@extends('layouts.app')

<!--@yield('title', 'Warehouse Transfer | DLMS')-->

@section('styles')
<style>
    /* ====== Warehouse Transfer — Focus Layout ====== */
    .transfer-split { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; }
    @media (max-width: 1100px) { .transfer-split { grid-template-columns: 1fr; } }
    .transfer-sticky { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 20px; }

    /* Scan area sebagai "bintang" halaman */
    .scan-hero {
        transition: box-shadow .2s ease, border-color .2s ease;
    }
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

    /* Ringkasan kiriman di panel sticky */
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
        icon="fa-truck-ramp-box"
        title="Warehouse Transfer (Mutasi Gudang)"
        subtitle="Kirim barang antar gudang (Pusat, Regional, Area) dan verifikasi saat barang sampai." />

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

    <!-- Two-Step Workflow Banner -->
    <div class="alert-box" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(16,185,129,0.1)); border: 1px solid rgba(59,130,246,0.3); color: var(--text-primary); margin-bottom: 24px; display: flex; align-items: flex-start; gap: 16px; padding: 16px 20px; border-radius: 10px;">
        <i class="fa-solid fa-route" style="font-size: 22px; color: var(--accent-blue); flex-shrink: 0; margin-top: 2px;"></i>
        <div>
            <strong>Alur Mutasi 2-Langkah (Two-Step Transfer)</strong>
            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px;">
                <span style="color: var(--accent-blue); font-weight: 600;">1. Gudang A: Kirim Barang</span> — Scan perangkat, lalu klik <em>Release Shipment</em>. Status perangkat menjadi <strong>IN_TRANSIT</strong>.
                &nbsp;<i class="fa-solid fa-arrow-right"></i>&nbsp;
                <span style="color: var(--accent-emerald); font-weight: 600;">2. Gudang B: Terima Barang Masuk</span> — Klik tab <em>Terima Barang Masuk</em>, pilih Surat Jalan, scan verifikasi, lalu klik <em>Approve & Put in Stock</em>. Status perangkat menjadi <strong>IN_STOCK</strong> di gudang tujuan.
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <button class="btn btn-outline active-tab-btn" id="tabCreateBtn" style="border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-primary);">
            <i class="fa-solid fa-paper-plane" style="color: var(--accent-blue);"></i>Kirim Barang (Create Transfer)
        </button>
        <button class="btn btn-outline" id="tabReceiveBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
            <i class="fa-solid fa-truck-ramp-box" style="color: var(--accent-emerald);"></i>Terima Barang Masuk (Incoming Transfer)
        </button>
        <button class="btn btn-outline" id="tabRackBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
            <i class="fa-solid fa-bars-staggered" style="color: var(--accent-indigo);"></i>Transfer Antar Rak
        </button>
    </div>

    <!-- Alert duplicate -->
    <div id="transferAlert" class="alert-box alert-danger animate-fade-in" style="display: none;">
        <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="alert-message">
            <strong>PERINGATAN!</strong> <span id="transferAlertText"></span>
        </div>
    </div>

    <div id="transferNotice" class="alert-box animate-fade-in" style="display: none; background-color: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.25); color: var(--accent-blue);">
        <div class="alert-icon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="alert-message">
            <span id="transferNoticeText"></span>
        </div>
    </div>

    <!-- TAB 1: CREATE TRANSFER -->
    <div id="panelCreateTransfer">
        <form action="{{ route('transfer.create') }}" method="POST" id="createTransferForm">
            @csrf
            <div class="transfer-split">
                <div>
                    <!-- TAHAP 1: Scan Barang (fokus utama) -->
                    <div class="card scan-hero-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-barcode"></i>
                                <span>Transfer Barang-Barang yang Akan Dikirim</span>
                            </div>
                            
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="create_scan_input" style="font-weight: 600; color: var(--accent-blue);">SCAN BARCODE BARANG (AUTO-FOCUS)</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" id="create_scan_input" class="form-control scan-target-input scan-hero" placeholder="Tembak barcode perangkat untuk ditambahkan ke daftar kirim..." style="padding-left: 52px; font-size: 17px; font-weight: 600; border-color: rgba(59, 130, 246, 0.4); height: 54px;">
                            </div>
                            <small style="color: var(--text-muted); margin-top: 6px; display: block;">Perangkat yang bisa dimutasi harus memiliki status <strong>IN_STOCK</strong> di gudang asal terpilih.</small>
                        </div>
                    </div>

                    <!-- Stok tersedia di gudang asal (klik untuk menambah tanpa scan) -->
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-warehouse"></i>
                                <span>Stok Tersedia di Gudang Asal (<span id="availCount">0</span>)</span>
                            </div>
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:12px;"></i>
                                <input type="text" id="availSearch" class="form-control" placeholder="Cari SN / tipe (semua gudang)..." style="width: 260px; height: 36px; padding-left: 32px; font-size: 13px;" autocomplete="off">
                            </div>
                        </div>
                        <div style="padding: 0 4px 10px; font-size: 12px; color: var(--text-muted);">
                            <i class="fa-solid fa-circle-info"></i> Tanpa pencarian: hanya menampilkan gudang asal terpilih. Saat mencari SN/tipe: mencari di <strong>semua gudang</strong>.
                        </div>
                        <div class="table-wrapper" style="max-height: 260px; overflow-y: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Serial Number (SN)</th>
                                        <th>Tipe</th>
                                        <th>Kondisi</th>
                                        <th>Lokasi</th>
                                        <th style="text-align: right; width: 140px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="availStockBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Draft Table -->
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-list-check"></i>
                                <span>Daftar Barang Transfer (<span id="createCount">0</span> Item)</span>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Serial Number (SN)</th>
                                        <th>Tipe</th>
                                        <th>Status Terakhir</th>
                                        <th style="text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="createTableBody">
                                    <tr id="createEmptyPlaceholder">
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada barang di-scan. Silakan scan barcode di atas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Accessories Transfer Section (opsional, collapsible) -->
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-plug"></i>
                                <span>Mutasi Aksesoris (Opsional)</span>
                            </div>
                            <button type="button" class="opt-add-btn" id="toggleAccSection"><i class="fa-solid fa-plus"></i> Tambah Aksesoris</button>
                        </div>

                        <div id="accSectionBody" style="display: none;">
                        <div class="form-group" style="position: relative; margin-bottom: 12px;">
                            <label for="acc_search_input" style="font-weight: 600; color: var(--accent-indigo);">CARI AKSESORIS UNTUK DIMUTASI</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 15px; color: var(--text-muted);"></i>
                                <input type="text" id="acc_search_input" class="form-control" placeholder="Ketik nama atau kode aksesoris..." style="padding-left: 48px; height: 48px; border-color: rgba(99, 102, 241, 0.4);" autocomplete="off">
                            </div>
                            <!-- Autocomplete results dropdown -->
                            <div id="acc_autocomplete_list" style="position: absolute; left: 0; right: 0; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); z-index: 1000; display: none; max-height: 250px; overflow-y: auto; margin-top: 4px;">
                            </div>
                        </div>

                        <!-- Quick Suggestions (AI-Driven) -->
                        @if(!empty($suggestedAccessories))
                            <div class="ai-suggestion-container">
                                <span class="ai-suggestion-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Suggestion:</span>
                                @foreach($suggestedAccessories as $acc)
                                    <button type="button" class="ai-pill-btn quick-acc-btn" data-code="{{ $acc['code'] }}" data-name="{{ $acc['name'] }}">
                                        <i class="fa-solid fa-plus"></i> {{ $acc['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="table-wrapper" style="margin-top: 16px;">
                            <table class="table" id="accDraftTable">
                                <thead>
                                    <tr>
                                        <th>Kode Aksesoris</th>
                                        <th>Nama Aksesoris</th>
                                        <th>Stok Asal</th>
                                        <th style="width: 150px; text-align: center;">Jumlah Mutasi (Qty)</th>
                                        <th style="text-align: right; width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="accDraftTableBody">
                                    <tr id="emptyAccRowPlaceholder">
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 25px;">Belum ada aksesoris yang dipilih. Cari aksesoris di atas atau gunakan tombol Saran Cepat.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </div><!-- /accSectionBody -->
                    </div>

                    <!-- SIM/GSM Transfer Card (opsional, collapsible) -->
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-sim-card"></i>
                                <span>Mutasi Kartu GSM (Opsional)</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="badge badge-info" id="simSelectedBadge">0 dipilih</span>
                                <button type="button" class="opt-add-btn" id="toggleSimSection"><i class="fa-solid fa-plus"></i> Tambah SIM Card</button>
                            </div>
                        </div>

                        <div id="simSectionBody" style="display: none;">
                        <!-- Quick-add (scan MSISDN) + aksi massal -->
                        <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
                            <div style="position:relative; flex:1; min-width:220px;">
                                <i class="fa-solid fa-barcode" style="position:absolute; left:14px; top:13px; color:var(--text-muted);"></i>
                                <input type="text" id="sim_scan_add" class="form-control" placeholder="Scan / ketik MSISDN lalu Enter untuk pilih cepat..." style="padding-left:40px; border-color: rgba(99,102,241,0.4);" autocomplete="off">
                            </div>
                            <button type="button" class="btn btn-outline" id="simSelectAllBtn" style="white-space:nowrap;"><i class="fa-solid fa-check-double"></i> Pilih Semua</button>
                            <button type="button" class="btn btn-outline" id="simClearBtn" style="white-space:nowrap;"><i class="fa-solid fa-eraser"></i> Bersihkan</button>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <div style="position: relative;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 13px; color: var(--text-muted);"></i>
                                <input type="text" id="sim_search_input" class="form-control" placeholder="Cari MSISDN / provider..." style="padding-left: 44px;" autocomplete="off">
                            </div>
                            <small style="color: var(--text-muted); display:block; margin-top:6px;"><strong id="simAvailCount">0</strong> SIM IN_STOCK tersedia di gudang asal terpilih.</small>
                        </div>

                        <div class="table-wrapper" style="max-height: 280px; overflow-y: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" id="sim_check_all"></th>
                                        <th>MSISDN</th>
                                        <th>Provider</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody id="simTransferBody">
                                    @forelse($simcards as $sim)
                                        <tr class="sim-transfer-row" data-warehouse="{{ $sim['warehouse_code'] }}" data-search="{{ strtolower($sim['msisdn'] . ' ' . $sim['provider']) }}">
                                            <td><input type="checkbox" class="sim-transfer-check" name="sim_ids[]" value="{{ $sim['id'] }}"></td>
                                            <td style="font-weight: 600; color: var(--accent-indigo);">{{ $sim['msisdn'] }}</td>
                                            <td>{{ $sim['provider'] }}</td>
                                            <td>{{ $sim['category'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr id="simTransferEmpty"><td colspan="4" style="text-align:center; color: var(--text-muted); padding: 24px;">Tidak ada SIM IN_STOCK di gudang.</td></tr>
                                    @endforelse
                                    <tr id="simNoneForWarehouse" style="display:none;"><td colspan="4" style="text-align:center; color: var(--text-muted); padding: 24px;">Tidak ada SIM di gudang asal ini.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        </div><!-- /simSectionBody -->
                    </div>
                </div>

                <!-- Config side panel (sticky) -->
                <div>
                    <div class="transfer-sticky">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fa-solid fa-route"></i>
                                    <span>Rute Pengiriman</span>
                                </div>
                            </div>

                            <!-- Ringkasan isi kiriman -->
                            <div class="ship-summary">
                                <div class="ss-box"><div class="ss-num" id="sumDevices">0</div><div class="ss-lbl">Perangkat</div></div>
                                <div class="ss-box"><div class="ss-num" id="sumAcc">0</div><div class="ss-lbl">Aksesoris</div></div>
                                <div class="ss-box"><div class="ss-num" id="sumSim">0</div><div class="ss-lbl">Kartu GSM</div></div>
                            </div>

                            <x-warehouse-select
                                name="from_warehouse"
                                id="from_warehouse"
                                label="Gudang Pengirim (Asal)"
                                :warehouses="$warehouses"
                                :show-empty-option="false"
                                :readonly="true"
                            />

                            <div class="form-group">
                                <label for="to_warehouse">Gudang Tujuan</label>
                                <select name="to_warehouse" id="to_warehouse" class="form-control">
                                    @foreach($warehouses as $key => $name)
                                        <option value="{{ $key }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="margin-top: 24px;">
                                <button type="submit" id="btnReleaseShipment" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;" disabled>
                                    <i class="fa-solid fa-truck-fast"></i> Release Shipment (Surat Jalan)
                                </button>
                                <div class="shortcut-hint">Pintasan: <kbd>Ctrl</kbd> + <kbd>Enter</kbd> untuk Release Shipment</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 2: INCOMING TRANSFER -->
    <div id="panelReceiveTransfer" style="display: none;">
        <form action="{{ route('transfer.approve') }}" method="POST" id="approveTransferForm">
            @csrf
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                <div>
                    <!-- Select Surat Jalan -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-file-invoice"></i>
                                <span>Pilih Surat Jalan Masuk</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sj_select">Nomor Surat Jalan (Digital DO)</label>
                            <select name="sj_id" id="sj_select" class="form-control" onchange="loadSjDetails(this.value)">
                                <option value="">-- Pilih Surat Jalan --</option>
                                @foreach($delivery_orders as $id => $do)
                                    @if($do['status'] === 'IN_TRANSIT')
                                        <option value="{{ $id }}">{{ $id }} ({{ $do['from_warehouse_code'] }} → {{ $do['to_warehouse_code'] }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Verification Scan Area -->
                    <div class="card" id="verificationCard" style="opacity: 0.5; pointer-events: none;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-barcode"></i>
                                <span>Scan Verifikasi (Cocokkan Kuantitas)</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="verify_scan_input" style="font-weight: 600; color: var(--accent-emerald);">SCAN BARCODE BARANG VERIFIKASI (AUTO-FOCUS)</label>
                            <input type="text" id="verify_scan_input" class="form-control" placeholder="Scan ulang barang fisik untuk mencocokkan..." style="font-size: 15px; font-weight: 600; border-color: rgba(16, 185, 129, 0.4); height: 48px;">
                            <small style="color: var(--text-muted); margin-top: 6px; display: block;">Operator wajib menscan semua item untuk memastikan jumlah fisik cocok dengan Surat Jalan.</small>
                        </div>
                    </div>

                    <!-- Verification Table -->
                    <div class="card" id="verificationTableCard" style="display: none;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-list-check"></i>
                                <span>Detail Pencocokan Barang (<span id="verifiedCount">0</span>/ <span id="totalVerifyCount">0</span> Cocok)</span>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Serial Number (SN)</th>
                                        <th>Jenis Device</th>
                                        <th>Nama Device</th>
                                        <th style="text-align: center;">Jumlah</th>
                                        <th>Gudang Asal</th>
                                        <th>Status Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody id="verifyTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Incoming Accessories Table -->
                    <div class="card" id="incomingAccessoriesCard" style="display: none; margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-box-open"></i>
                                <span>Manifes Aksesoris Masuk (Otomatis Diterima)</span>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kode Aksesoris</th>
                                        <th>Nama Aksesoris</th>
                                        <th style="text-align: center; width: 150px;">Kuantitas Dikirim</th>
                                    </tr>
                                </thead>
                                <tbody id="incomingAccessoriesBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Incoming SIM Table -->
                    <div class="card" id="incomingSimCard" style="display: none; margin-top: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-sim-card"></i>
                                <span>Manifes Kartu GSM Masuk (Otomatis Diterima)</span>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>MSISDN</th>
                                        <th>Provider</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody id="incomingSimBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Action Panel (sticky) -->
                <div>
                    <div class="transfer-sticky">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>Persetujuan Penerimaan</span>
                                </div>
                            </div>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">Silakan scan seluruh barang dalam manifes. Tombol konfirmasi akan terbuka setelah semua item diverifikasi.</p>

                            <button type="submit" id="btnApproveTransfer" class="btn btn-success" style="width: 100%; justify-content: center; padding: 12px;" disabled>
                                <i class="fa-solid fa-thumbs-up"></i> Approve & Put in Stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 3: RACK TRANSFER -->
    <div id="panelRackTransfer" style="display: none;">
        <form action="{{ route('transfer.rack.post') }}" method="POST" id="rackTransferForm">
            @csrf
            <div class="card mb-4" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-warehouse"></i>
                        <span>Pilih Gudang (Sumber Rak)</span>
                    </div>
                </div>
                <div class="form-group mb-0">
                    @php
                        $isSuperAdmin = request()->user()->hasRole('super_admin');
                        $activeWarehouse = session('active_warehouse_code') ?: request()->user()->warehouse_code;
                    @endphp
                    <select id="rt_warehouse" name="warehouse" class="form-control" {{ !$isSuperAdmin ? 'disabled' : '' }}>
                        <option value="">-- Pilih Gudang --</option>
                        @foreach($warehouses as $key => $name)
                            <option value="{{ $key }}" {{ $activeWarehouse === $key ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 24px; align-items: start;">
                <!-- RAK ASAL -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-bars-staggered" style="color: var(--accent-blue);"></i>
                            <span>Rak Asal</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <select id="rt_source_rack" name="source_rack" class="form-control" required disabled>
                            <option value="">-- Pilih Rak Asal --</option>
                        </select>
                    </div>
                    <div class="table-wrapper" style="min-height: 250px; max-height: 400px; overflow-y: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="rt_check_all"></th>
                                    <th>SN / Model</th>
                                </tr>
                            </thead>
                            <tbody id="rt_source_body">
                                <tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak asal terlebih dahulu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TOMBOL SIMPAN -->
                <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; align-self: stretch; padding-top: 100px;">
                    <button type="submit" id="btnTransferRack" class="btn btn-primary" style="padding: 12px 24px; font-weight: bold; border-radius: 8px;" disabled>
                        Simpan <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                    </button>
                    <small id="rt_selected_count" style="margin-top: 8px; color: var(--text-muted); font-weight: bold;">0 dipilih</small>
                </div>

                <!-- RAK TUJUAN -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-bars-staggered" style="color: var(--accent-emerald);"></i>
                            <span>Rak Tujuan</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <select id="rt_dest_rack" name="destination_rack" class="form-control" required disabled>
                            <option value="">-- Pilih Rak Tujuan --</option>
                        </select>
                    </div>
                    <div class="table-wrapper" style="min-height: 250px; max-height: 400px; overflow-y: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>SN / Model</th>
                                </tr>
                            </thead>
                            <tbody id="rt_dest_body">
                                <tr><td style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak tujuan terlebih dahulu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Database stock check (only active devices in stock can be transferred)
    const inStockDevices = @json($devices);
    // Lookup SEMUA device (SN → {status, warehouse_code, type, unit_condition}) utk diagnosa scan.
    const deviceLookup = @json($deviceLookup ?? (object) []);
    // Peta kode gudang → nama, utk pesan yang ramah dibaca.
    const warehouseNames = @json($warehouses ?? (object) []);
    const deliveryOrders = @json($delivery_orders);
    const accessoriesListData = @json(array_values($accessories));
    const warehouseAccessories = @json($warehouseAccessories);

    // UI Tab toggle
    const tabCreateBtn = document.getElementById('tabCreateBtn');
    const tabReceiveBtn = document.getElementById('tabReceiveBtn');
    const tabRackBtn = document.getElementById('tabRackBtn');
    const panelCreateTransfer = document.getElementById('panelCreateTransfer');
    const panelReceiveTransfer = document.getElementById('panelReceiveTransfer');
    const panelRackTransfer = document.getElementById('panelRackTransfer');
    
    // Scanner input fields
    const createScanInput = document.getElementById('create_scan_input');
    const verifyScanInput = document.getElementById('verify_scan_input');
    
    // Set active tab scanner target selector mapping for Emulator
    const emulatorTarget = document.getElementById('emulatorTarget');

    let activeTab = 'create';

    tabCreateBtn.addEventListener('click', () => {
        activeTab = 'create';
        tabCreateBtn.style.borderBottomColor = 'var(--accent-blue)';
        tabCreateBtn.style.color = 'var(--text-primary)';
        tabReceiveBtn.style.borderBottomColor = 'transparent';
        tabReceiveBtn.style.color = 'var(--text-secondary)';
        tabRackBtn.style.borderBottomColor = 'transparent';
        tabRackBtn.style.color = 'var(--text-secondary)';
        panelCreateTransfer.style.display = 'block';
        panelReceiveTransfer.style.display = 'none';
        panelRackTransfer.style.display = 'none';
        if (emulatorTarget) {
            emulatorTarget.value = '.scan-target-input'; // default selector on create
        }
        createScanInput.focus();
    });

    tabReceiveBtn.addEventListener('click', () => {
        activeTab = 'receive';
        tabReceiveBtn.style.borderBottomColor = 'var(--accent-emerald)';
        tabReceiveBtn.style.color = 'var(--text-primary)';
        tabCreateBtn.style.borderBottomColor = 'transparent';
        tabCreateBtn.style.color = 'var(--text-secondary)';
        tabRackBtn.style.borderBottomColor = 'transparent';
        tabRackBtn.style.color = 'var(--text-secondary)';
        panelCreateTransfer.style.display = 'none';
        panelReceiveTransfer.style.display = 'block';
        panelRackTransfer.style.display = 'none';
        if (emulatorTarget) {
            emulatorTarget.value = '#verify_scan_input';
        }
        verifyScanInput.focus();
    });

    tabRackBtn.addEventListener('click', () => {
        activeTab = 'rack';
        tabRackBtn.style.borderBottomColor = 'var(--accent-indigo)';
        tabRackBtn.style.color = 'var(--text-primary)';
        tabCreateBtn.style.borderBottomColor = 'transparent';
        tabCreateBtn.style.color = 'var(--text-secondary)';
        tabReceiveBtn.style.borderBottomColor = 'transparent';
        tabReceiveBtn.style.color = 'var(--text-secondary)';
        panelCreateTransfer.style.display = 'none';
        panelReceiveTransfer.style.display = 'none';
        panelRackTransfer.style.display = 'block';
    });

    // Custom autofocus listener
    document.addEventListener('click', (e) => {
        if (e.target.closest('#panelCreateTransfer') && e.target.closest('.card') && (e.target.closest('.card').innerHTML.includes('Mutasi Aksesoris') || e.target.closest('#accDraftTable'))) {
            return; // Don't steal focus when operator clicks inside accessory card or search input
        }
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'OPTION' && !e.target.closest('.scanner-emulator')) {
            if (activeTab === 'create') {
                createScanInput.focus();
            } else if (activeTab === 'receive') {
                verifyScanInput.focus();
            }
        }
    });

    // ==========================================
    // TAB 1: CREATE TRANSFER LOGIC
    // ==========================================
    const createDraftSns = new Set();
    const selectedAccessoryCodes = new Set();
    const createTableBody = document.getElementById('createTableBody');
    const createEmptyPlaceholder = document.getElementById('createEmptyPlaceholder');
    const createCountSpan = document.getElementById('createCount');
    const btnReleaseShipment = document.getElementById('btnReleaseShipment');
    const transferAlert = document.getElementById('transferAlert');
    const transferAlertText = document.getElementById('transferAlertText');

    // Elemen DOM aksesoris — HARUS dideklarasikan sebelum filterToWarehouseOptions()
    // dipanggil saat load, karena checkCreateFormValidity() (dipicu via filterSimByWarehouse
    // → updateSimBadge) mengakses accDraftTableBody. Jika di-declare belakangan → TDZ error.
    const accSearchInput = document.getElementById('acc_search_input');
    const accAutocompleteList = document.getElementById('acc_autocomplete_list');
    const accDraftTableBody = document.getElementById('accDraftTableBody');
    const emptyAccRowPlaceholder = document.getElementById('emptyAccRowPlaceholder');

    // Validate from and to warehouses are not the same
    const fromWarehouseSelect = document.getElementById('from_warehouse');
    const toWarehouseSelect = document.getElementById('to_warehouse');

    function filterToWarehouseOptions() {
        const fromVal = fromWarehouseSelect.value;
        Array.from(toWarehouseSelect.options).forEach(opt => {
            opt.disabled = (opt.value === fromVal);
            if (opt.selected && opt.disabled) {
                // Select first non-disabled option
                const firstEnabled = Array.from(toWarehouseSelect.options).find(o => !o.disabled);
                if (firstEnabled) toWarehouseSelect.value = firstEnabled.value;
            }
        });
        updateSourceWarehouseStocks();
        filterSimByWarehouse();
    }

    fromWarehouseSelect.addEventListener('change', filterToWarehouseOptions);
    filterToWarehouseOptions(); // Run on page load

    // Gudang asal berubah → device yang sudah discan (milik gudang lama) dibersihkan
    // agar tidak terkirim diam-diam (server menolak device lintas gudang asal).
    fromWarehouseSelect.addEventListener('change', function () {
        if (createDraftSns.size === 0) return;
        createTableBody.querySelectorAll('tr:not(#createEmptyPlaceholder)').forEach(r => r.remove());
        createDraftSns.clear();
        createCountSpan.innerText = 0;
        if (createEmptyPlaceholder) createEmptyPlaceholder.style.display = 'table-row';
        checkCreateFormValidity();
        triggerAlert('Gudang asal berubah — daftar device yang sudah discan dibersihkan. Silakan scan ulang dari gudang asal baru.');
    });

    // ==========================================
    // SIM / GSM TRANSFER (Create panel)
    // ==========================================
    // Self-contained (function declaration → hoisted): aman dipanggil dari filterToWarehouseOptions.
    function filterSimByWarehouse() {
        const fromWh = document.getElementById('from_warehouse').value;
        const search = (document.getElementById('sim_search_input')?.value || '').trim().toLowerCase();
        let availInWh = 0; // total SIM di gudang asal (abaikan filter pencarian)
        document.querySelectorAll('.sim-transfer-row').forEach(row => {
            const matchWh = row.dataset.warehouse === fromWh;
            const matchSearch = !search || (row.dataset.search || '').includes(search);
            const show = matchWh && matchSearch;
            row.style.display = show ? '' : 'none';
            if (!show) {
                const cb = row.querySelector('.sim-transfer-check');
                if (cb) cb.checked = false; // jangan kirim SIM dari gudang lain
            }
            if (matchWh) availInWh++;
        });
        const noneRow = document.getElementById('simNoneForWarehouse');
        if (noneRow) noneRow.style.display = (availInWh === 0) ? 'table-row' : 'none';
        const availEl = document.getElementById('simAvailCount');
        if (availEl) availEl.innerText = availInWh;
        updateSimBadge();
    }

    function updateSimBadge() {
        const badge = document.getElementById('simSelectedBadge');
        if (!badge) return;
        const n = document.querySelectorAll('.sim-transfer-check:checked').length;
        badge.innerText = n + ' dipilih';
        if (typeof checkCreateFormValidity === 'function') checkCreateFormValidity();
    }

    // Pilih/lepas semua baris SIM yang sedang tampil (sesuai gudang + pencarian).
    function setAllVisibleSim(checked) {
        document.querySelectorAll('.sim-transfer-row').forEach(row => {
            if (row.style.display === 'none') return;
            const cb = row.querySelector('.sim-transfer-check');
            if (cb) cb.checked = checked;
        });
        updateSimBadge();
    }

    const simSearchInput = document.getElementById('sim_search_input');
    const simCheckAll = document.getElementById('sim_check_all');
    const simSelectAllBtn = document.getElementById('simSelectAllBtn');
    const simClearBtn = document.getElementById('simClearBtn');
    const simScanAdd = document.getElementById('sim_scan_add');

    if (simSearchInput) simSearchInput.addEventListener('input', filterSimByWarehouse);
    if (simCheckAll) simCheckAll.addEventListener('change', function () { setAllVisibleSim(this.checked); });
    if (simSelectAllBtn) simSelectAllBtn.addEventListener('click', () => setAllVisibleSim(true));
    if (simClearBtn) simClearBtn.addEventListener('click', () => {
        setAllVisibleSim(false);
        if (simCheckAll) simCheckAll.checked = false;
    });

    // Quick-add: scan/ketik MSISDN → centang baris yang cocok di gudang asal.
    if (simScanAdd) {
        simScanAdd.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const val = this.value.trim().toLowerCase();
            this.value = '';
            if (!val) return;

            const fromWh = document.getElementById('from_warehouse').value;
            let matched = null;
            document.querySelectorAll('.sim-transfer-row').forEach(row => {
                if (matched) return;
                const msisdn = (row.children[1]?.innerText || '').trim().toLowerCase();
                if (row.dataset.warehouse === fromWh && msisdn === val) matched = row;
            });

            if (!matched) {
                if (typeof triggerAlert === 'function') {
                    triggerAlert('MSISDN ' + val + ' tidak ditemukan sebagai SIM IN_STOCK di gudang asal terpilih.');
                } else if (window.playBeep) { window.playBeep('error'); }
                return;
            }

            const cb = matched.querySelector('.sim-transfer-check');
            if (cb) { cb.checked = true; updateSimBadge(); }
            matched.style.background = 'rgba(99,102,241,0.12)';
            matched.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => { matched.style.background = ''; }, 800);
            if (window.playBeep) window.playBeep('success');
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('sim-transfer-check')) updateSimBadge();
    });

    createScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const sn = this.value.trim();
            if (sn) {
                processCreateScan(sn);
            }
            this.value = '';
            createScanInput.focus();
        }
    });

    function processCreateScan(sn) {
        transferAlert.style.display = 'none';

        // Check if already in draft list
        if (createDraftSns.has(sn)) {
            triggerAlert("Device SN " + sn + " sudah ada di daftar transfer.");
            return;
        }

        const fromWh = fromWarehouseSelect.value;
        const fromWhName = fromWarehouseSelect.options ? (fromWarehouseSelect.options[fromWarehouseSelect.selectedIndex]?.text || fromWh) : (warehouseNames[fromWh] || fromWh);

        // Cari di seluruh device untuk bisa memberi alasan SPESIFIK bila tak bisa dimutasi.
        const dev = deviceLookup[sn];

        if (!dev) {
            triggerAlert("Device SN " + sn + " TIDAK DITEMUKAN di sistem. Pastikan barang sudah diterima (Receiving) & lolos QC.");
            return;
        }

        // Hanya unit IN_STOCK yang bisa dimutasi.
        if (dev.status !== 'IN_STOCK') {
            let extra = '';
            if (dev.status === 'PENDING_QC') extra = ' Unit masih menunggu QC — selesaikan di menu Quality Control dulu.';
            else if (dev.status === 'IN_TRANSIT') extra = ' Unit sedang dalam perjalanan transfer lain.';
            else if (dev.status === 'ISSUED' || dev.status === 'INSTALLED') extra = ' Unit sedang dipegang teknisi/customer.';
            triggerAlert("Device SN " + sn + " tidak bisa dimutasi (status saat ini: " + dev.status + ")." + extra);
            return;
        }

        // Integritas: device harus berada di gudang asal (pengirim) yang dipilih.
        if (dev.warehouse_code !== fromWh) {
            const locName = (warehouseNames[dev.warehouse_code] || dev.warehouse_code || 'gudang lain');
            // Draft masih kosong → pindahkan otomatis gudang asal ke lokasi device
            // sehingga scan langsung berhasil tanpa operator harus memilih gudang manual.
            if (createDraftSns.size === 0 && dev.warehouse_code) {
                setOriginWarehouse(dev.warehouse_code);
                triggerNotice('Gudang asal otomatis diset ke ' + locName + ' (lokasi device ' + sn + ').');
                // lanjut tambahkan device di bawah
            } else {
                triggerAlert("Device SN " + sn + " berada di " + locName + ", bukan gudang asal terpilih (" + fromWhName + "). Kosongkan daftar saat ini atau ubah \"Gudang Pengirim\" ke " + locName + " untuk memutasikannya.");
                return;
            }
        }

        const matchDev = dev;

        // Add
        createDraftSns.add(sn);
        
        if (createEmptyPlaceholder) {
            createEmptyPlaceholder.style.display = 'none';
        }

        if (window.playBeep) window.playBeep('success');

        const cond = (matchDev.unit_condition === 'BEKAS') ? 'BEKAS' : 'BARU';
        const condCls = cond === 'BEKAS' ? 'badge-warning' : 'badge-success';

        const newRow = document.createElement('tr');
        newRow.id = `create-row-${sn}`;
        newRow.className = 'animate-fade-in row-added';
        newRow.innerHTML = `
            <td>${createDraftSns.size}</td>
            <td style="font-weight:600; color:var(--accent-blue);">
                <i class="fa-solid fa-circle-check" style="color:var(--accent-emerald); margin-right:6px;" title="Sudah masuk daftar kirim"></i>${sn}
                <input type="hidden" name="sns[]" value="${sn}">
            </td>
            <td><span class="badge badge-info">${matchDev.type}</span> <span class="badge ${condCls}">${cond}</span></td>
            <td><span class="badge badge-success">${matchDev.status}</span></td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeCreateRow('${sn}')" style="padding:4px 8px; font-size:11px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        createTableBody.appendChild(newRow);

        createCountSpan.innerText = createDraftSns.size;
        checkCreateFormValidity();
        renderAvailableStock();
    }

    // ==========================================
    // STOK TERSEDIA DI GUDANG ASAL (klik tambah tanpa scan)
    // ==========================================
    const availStockBody = document.getElementById('availStockBody');
    const availCountSpan = document.getElementById('availCount');
    const availSearch = document.getElementById('availSearch');

    // Hitung jumlah device IN_STOCK per gudang (abaikan yang sudah masuk draft).
    function stockCountByWarehouse() {
        const map = {};
        inStockDevices.forEach(d => {
            if (createDraftSns.has(d.serial_number)) return;
            const wh = d.warehouse_code || '';
            map[wh] = (map[wh] || 0) + 1;
        });
        return map;
    }

    // Pindahkan gudang asal secara terprogram (TIDAK memicu listener 'change' yang
    // membersihkan draft) lalu sinkronkan tujuan/aksesoris/SIM/stok.
    function setOriginWarehouse(code) {
        if (!code || fromWarehouseSelect.value === code) return;
        if (!fromWarehouseSelect.options) return; // Prevent changing if locked (hidden input)
        fromWarehouseSelect.value = code;
        filterToWarehouseOptions();
        renderAvailableStock();
    }

    function renderAvailableStock() {
        if (!availStockBody) return;
        const fromWh = fromWarehouseSelect.value;
        const q = (availSearch?.value || '').trim().toLowerCase();
        const searching = q.length > 0;

        // Saat MENCARI → telusuri SEMUA gudang (global) agar SN/tipe pasti ketemu di
        // gudang manapun. Tanpa pencarian → hanya stok di gudang asal terpilih.
        const rows = inStockDevices.filter(d => {
            if (createDraftSns.has(d.serial_number)) return false;
            if (searching) {
                return (d.serial_number || '').toLowerCase().includes(q)
                    || (d.type || '').toLowerCase().includes(q);
            }
            return d.warehouse_code === fromWh;
        });

        // #region agent log
        // H1/H3/H5: apa yang sebenarnya difilter saat render (termasuk saat user mengetik).
        // fetch('http://127.0.0.1:8080/ingest/11284be5-3d92-4d1e-9bdd-50a403b93ba4', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'e0c926' }, body: JSON.stringify({ sessionId: 'e0c926', runId: 'run1', hypothesisId: 'H1', location: 'transfer.blade.php:renderAvailableStock', message: 'render result', data: { fromWh: fromWh, q: q, searching: searching, totalInStock: (inStockDevices || []).length, matched: rows.length, draftSize: createDraftSns.size }, timestamp: Date.now() }) }).catch(() => {});
        // // #endregion

        if (availCountSpan) availCountSpan.innerText = rows.length;

        if (!rows.length) {
            // Tidak ada hasil → bantu operator: tunjukkan gudang lain yang punya stok.
            const counts = stockCountByWarehouse();
            const others = Object.keys(counts)
                .filter(wh => wh && wh !== fromWh && counts[wh] > 0)
                .sort((a, b) => counts[b] - counts[a]);

            let hint = '';
            if (others.length && !searching) {
                hint = '<div style="margin-top:14px; display:flex; flex-wrap:wrap; gap:8px; justify-content:center;">'
                    + '<span style="font-size:12px; color:var(--text-muted); width:100%;">Stok tersedia di gudang lain — klik untuk pindah gudang asal:</span>'
                    + others.map(wh => '<button type="button" class="btn btn-outline btn-icon-sm avail-switch-btn" data-wh="' + wh + '" style="padding:5px 12px; font-size:12px;">'
                        + '<i class="fa-solid fa-warehouse"></i> ' + (warehouseNames[wh] || wh) + ' <span class="badge badge-info" style="margin-left:4px;">' + counts[wh] + '</span></button>').join('')
                    + '</div>';
            }

            availStockBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:22px;">'
                + (searching
                    ? 'SN/tipe "' + q + '" tidak ditemukan sebagai stok <strong>IN_STOCK</strong> di gudang manapun. Pastikan barang sudah lolos QC.'
                    : 'Tidak ada stok IN_STOCK di gudang asal ini.')
                + hint + '</td></tr>';
            return;
        }

        availStockBody.innerHTML = rows.map(d => {
            const cond = (d.unit_condition === 'BEKAS') ? 'BEKAS' : 'BARU';
            const condCls = cond === 'BEKAS' ? 'badge-warning' : 'badge-success';
            const isHere = d.warehouse_code === fromWh;
            const locName = warehouseNames[d.warehouse_code] || d.warehouse_code || '-';
            const locBadge = isHere
                ? `<span class="badge badge-success">${locName}</span>`
                : `<span class="badge badge-warning" title="Beda gudang dari asal terpilih">${locName}</span>`;
            const btn = isHere
                ? `<button type="button" class="btn btn-primary btn-icon-sm avail-add-btn" data-sn="${d.serial_number}" style="padding:4px 10px; font-size:11px;"><i class="fa-solid fa-plus"></i> Tambah</button>`
                : `<button type="button" class="btn btn-outline btn-icon-sm avail-add-btn" data-sn="${d.serial_number}" style="padding:4px 10px; font-size:11px;" title="Pindahkan gudang asal ke ${locName} lalu tambahkan"><i class="fa-solid fa-arrow-right-arrow-left"></i> Pindah &amp; Tambah</button>`;
            return `<tr>
                <td style="font-weight:600; color:var(--accent-blue);">${d.serial_number}</td>
                <td><span class="badge badge-info">${d.type}</span></td>
                <td><span class="badge ${condCls}">${cond}</span></td>
                <td>${locBadge}</td>
                <td style="text-align:right;">${btn}</td>
            </tr>`;
        }).join('');
    }

    if (availStockBody) {
        availStockBody.addEventListener('click', function (e) {
            const addBtn = e.target.closest('.avail-add-btn');
            if (addBtn) { processCreateScan(addBtn.getAttribute('data-sn')); return; }

            const switchBtn = e.target.closest('.avail-switch-btn');
            if (switchBtn) {
                const wh = switchBtn.getAttribute('data-wh');
                setOriginWarehouse(wh);
                triggerNotice('Gudang asal dipindah ke ' + (warehouseNames[wh] || wh) + '. Silakan pilih device untuk dimutasi.');
            }
        });
    }
    if (availSearch) availSearch.addEventListener('input', function () {
        // #region agent log
        // // H5: konfirmasi listener input search benar-benar terpicu + nilai elemen.
        // fetch('http://127.0.0.1:8080/ingest/11284be5-3d92-4d1e-9bdd-50a403b93ba4', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'e0c926' }, body: JSON.stringify({ sessionId: 'e0c926', runId: 'run1', hypothesisId: 'H5', location: 'transfer.blade.php:availSearch.input', message: 'search input fired', data: { value: availSearch.value }, timestamp: Date.now() }) }).catch(() => {});
        // // #endregion
        renderAvailableStock();
    });
    // #region agent log
    // H5: konfirmasi elemen availSearch ditemukan saat binding listener.
    // fetch('http://127.0.0.1:8080/ingest/11284be5-3d92-4d1e-9bdd-50a403b93ba4', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'e0c926' }, body: JSON.stringify({ sessionId: 'e0c926', runId: 'run1', hypothesisId: 'H5', location: 'transfer.blade.php:bindSearch', message: 'availSearch binding', data: { found: !!availSearch }, timestamp: Date.now() }) }).catch(() => {});
    // #endregion

    window.removeCreateRow = function(sn) {
        const row = document.getElementById(`create-row-${sn}`);
        if (row) {
            row.remove();
            createDraftSns.delete(sn);
            createCountSpan.innerText = createDraftSns.size;
            if (createDraftSns.size === 0) {
                if (createEmptyPlaceholder) createEmptyPlaceholder.style.display = 'table-row';
            }
            if (window.playBeep) window.playBeep('error');
            
            // Re-index
            const rows = createTableBody.querySelectorAll('tr:not(#createEmptyPlaceholder)');
            rows.forEach((r, idx) => {
                r.cells[0].innerText = idx + 1;
            });
            checkCreateFormValidity();
            renderAvailableStock();
        }
        createScanInput.focus();
    }

    function triggerAlert(msg) {
        if (window.playBeep) window.playBeep('error');
        transferAlertText.innerText = msg;
        transferAlert.style.display = 'flex';
        transferAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Notifikasi informatif (bukan error) — mis. auto-switch gudang asal.
    const transferNotice = document.getElementById('transferNotice');
    const transferNoticeText = document.getElementById('transferNoticeText');
    let transferNoticeTimer = null;
    function triggerNotice(msg) {
        if (!transferNotice) return;
        transferAlert.style.display = 'none';
        transferNoticeText.innerText = msg;
        transferNotice.style.display = 'flex';
        clearTimeout(transferNoticeTimer);
        transferNoticeTimer = setTimeout(() => { transferNotice.style.display = 'none'; }, 4000);
    }

    // ==========================================
    // ACCESSORY TRANSFER FORM LOGIC
    // (elemen DOM aksesoris dideklarasikan lebih awal — lihat blok di atas)
    // ==========================================
    function checkCreateFormValidity() {
        let totalQty = 0;
        const inputs = accDraftTableBody.querySelectorAll('.acc-qty-input');
        inputs.forEach(input => {
            totalQty += parseInt(input.value || 0);
        });
        const hasDevices = createDraftSns.size > 0;
        const hasAccessories = totalQty > 0 && selectedAccessoryCodes.size > 0;
        const simCount = document.querySelectorAll('.sim-transfer-check:checked').length;
        const hasSim = simCount > 0;
        btnReleaseShipment.disabled = !(hasDevices || hasAccessories || hasSim);

        // Ringkasan isi kiriman di panel sticky
        const sumD = document.getElementById('sumDevices');
        const sumA = document.getElementById('sumAcc');
        const sumS = document.getElementById('sumSim');
        if (sumD) sumD.innerText = createDraftSns.size;
        if (sumA) sumA.innerText = selectedAccessoryCodes.size;
        if (sumS) sumS.innerText = simCount;
    }

    function addAccessoryToDraft(code, name) {
        if (selectedAccessoryCodes.has(code)) {
            const qtyInput = document.getElementById(`acc-qty-${code}`);
            if (qtyInput) {
                qtyInput.value = parseInt(qtyInput.value || 0) + 1;
                qtyInput.focus();
                qtyInput.select();
            }
            if (window.playBeep) window.playBeep('success');
            checkCreateFormValidity();
            return;
        }

        selectedAccessoryCodes.add(code);

        if (emptyAccRowPlaceholder) {
            emptyAccRowPlaceholder.style.display = 'none';
        }

        const fromWh = fromWarehouseSelect.value;
        const currentStock = (warehouseAccessories[fromWh] && warehouseAccessories[fromWh][code]) ? warehouseAccessories[fromWh][code] : 0;

        const row = document.createElement('tr');
        row.setAttribute('id', `acc-row-${code}`);
        row.className = 'animate-fade-in';
        row.innerHTML = `
            <td style="font-weight: 600; color: var(--text-primary);">${code}</td>
            <td>${name}</td>
            <td><span id="source-stock-${code}" style="font-weight: 600; color: var(--accent-indigo);">${currentStock} pcs</span></td>
            <td style="text-align: center;">
                <input type="hidden" name="acc_types[]" value="${code}">
                <input type="number" id="acc-qty-${code}" name="acc_qtys[]" class="form-control acc-qty-input" min="1" max="${currentStock}" value="1" style="width: 100px; text-align: center; margin: 0 auto;" required>
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeAccRow('${code}')" style="padding:4px 8px; font-size:11px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;

        accDraftTableBody.appendChild(row);

        const newQtyInput = document.getElementById(`acc-qty-${code}`);
        if (newQtyInput) {
            newQtyInput.focus();
            newQtyInput.select();
            newQtyInput.addEventListener('input', checkCreateFormValidity);
            newQtyInput.addEventListener('change', checkCreateFormValidity);
        }

        if (window.playBeep) window.playBeep('success');
        checkCreateFormValidity();
    }

    window.removeAccRow = function(code) {
        const row = document.getElementById(`acc-row-${code}`);
        if (row) {
            row.remove();
            selectedAccessoryCodes.delete(code);
            if (selectedAccessoryCodes.size === 0) {
                if (emptyAccRowPlaceholder) emptyAccRowPlaceholder.style.display = 'table-row';
            }
            if (window.playBeep) window.playBeep('error');
            checkCreateFormValidity();
        }
        accSearchInput.focus();
    }

    function updateSourceWarehouseStocks() {
        const fromWh = fromWarehouseSelect.value;
        const rows = accDraftTableBody.querySelectorAll('tr:not(#emptyAccRowPlaceholder)');
        rows.forEach(row => {
            const code = row.id.replace('acc-row-', '');
            const qtySpan = document.getElementById(`source-stock-${code}`);
            if (qtySpan) {
                const stock = (warehouseAccessories[fromWh] && warehouseAccessories[fromWh][code]) ? warehouseAccessories[fromWh][code] : 0;
                qtySpan.innerText = stock + ' pcs';
                const qtyInput = document.getElementById(`acc-qty-${code}`);
                if (qtyInput) {
                    qtyInput.max = stock;
                }
            }
        });
    }

    // Autocomplete keyboard navigation variables
    let activeIndex = -1;

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
                    item.style.background = 'rgba(99, 102, 241, 0.15)';
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
                    
                    const highlightedName = item.name.replace(new RegExp(`(${query})`, 'gi'), '<strong>$1</strong>');
                    const highlightedCode = item.code.replace(new RegExp(`(${query})`, 'gi'), '<strong>$1</strong>');

                    btn.innerHTML = `
                        <div style="font-weight: 600; color: var(--accent-indigo);">${highlightedCode}</div>
                        <div style="color: var(--text-primary); font-size: 13px;">${highlightedName}</div>
                    `;

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

        document.addEventListener('click', function(e) {
            if (accSearchInput && !accSearchInput.contains(e.target) && !accAutocompleteList.contains(e.target)) {
                accAutocompleteList.style.display = 'none';
            }
        });
    }

    const quickAccBtns = document.querySelectorAll('.quick-acc-btn');
    quickAccBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            const name = this.getAttribute('data-name');
            addAccessoryToDraft(code, name);
        });
    });

    // ==========================================
    // TAB 2: INCOMING TRANSFER VERIFICATION LOGIC
    // ==========================================
    let currentSjData = null;
    let verifiedSns = new Set();
    const verificationCard = document.getElementById('verificationCard');
    const verificationTableCard = document.getElementById('verificationTableCard');
    const verifyTableBody = document.getElementById('verifyTableBody');
    const verifiedCountSpan = document.getElementById('verifiedCount');
    const totalVerifyCountSpan = document.getElementById('totalVerifyCount');
    const btnApproveTransfer = document.getElementById('btnApproveTransfer');
    const incomingAccCard = document.getElementById('incomingAccessoriesCard');
    const incomingAccBody = document.getElementById('incomingAccessoriesBody');

    window.loadSjDetails = function(sjId) {
        verifyTableBody.innerHTML = '';
        verifiedSns.clear();
        btnApproveTransfer.disabled = true;
        transferAlert.style.display = 'none';
        incomingAccBody.innerHTML = '';

        const incomingSimCard = document.getElementById('incomingSimCard');
        const incomingSimBody = document.getElementById('incomingSimBody');
        if (incomingSimBody) incomingSimBody.innerHTML = '';

        if (!sjId) {
            verificationCard.style.opacity = '0.5';
            verificationCard.style.pointerEvents = 'none';
            verificationTableCard.style.display = 'none';
            incomingAccCard.style.display = 'none';
            if (incomingSimCard) incomingSimCard.style.display = 'none';
            currentSjData = null;
            return;
        }

        currentSjData = deliveryOrders[sjId];

        // Show SIM manifest if included
        let hasSimManifest = false;
        if (incomingSimCard && incomingSimBody && currentSjData.simcards && currentSjData.simcards.length > 0) {
            hasSimManifest = true;
            currentSjData.simcards.forEach(sim => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="font-weight:600; color:var(--accent-indigo);">${sim.msisdn}</td>
                    <td>${sim.provider ?? '-'}</td>
                    <td>${sim.category ?? '-'}</td>
                `;
                incomingSimBody.appendChild(row);
            });
            incomingSimCard.style.display = 'block';
        } else if (incomingSimCard) {
            incomingSimCard.style.display = 'none';
        }

        // Show accessories manifest if included
        if (currentSjData.accessories && currentSjData.accessories.length > 0) {
            currentSjData.accessories.forEach(acc => {
                const qty = acc.pivot ? acc.pivot.qty : 0;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="font-weight:600; color:var(--text-primary);">${acc.code}</td>
                    <td>${acc.name}</td>
                    <td style="text-align:center; font-weight:600; color:var(--accent-indigo);">${qty} pcs</td>
                `;
                incomingAccBody.appendChild(row);
            });
            incomingAccCard.style.display = 'block';
        } else {
            incomingAccCard.style.display = 'none';
        }

        // Enable scan area (only if there are devices, or allow approval directly if DO has ONLY accessories)
        if (currentSjData.devices && currentSjData.devices.length > 0) {
            verificationCard.style.opacity = '1';
            verificationCard.style.pointerEvents = 'auto';
            verificationTableCard.style.display = 'block';

            totalVerifyCountSpan.innerText = currentSjData.devices.length;
            verifiedCountSpan.innerText = 0;

            // Fill verification table in pending state
            currentSjData.devices.forEach(sn => {
                const dev = deviceLookup[sn] || {};
                const type = dev.type || '-';
                const name = dev.model || '-';
                const origin = dev.warehouse_code ? (warehouseNames[dev.warehouse_code] || dev.warehouse_code) : '-';
                
                const row = document.createElement('tr');
                row.id = `verify-row-${sn}`;
                row.innerHTML = `
                    <td style="font-weight:600; color:var(--text-secondary);">${sn}</td>
                    <td><span class="badge badge-info">${type}</span></td>
                    <td style="font-size: 13px;">${name}</td>
                    <td style="text-align: center; font-weight: 600;">1 unit</td>
                    <td style="font-size: 13px;">${origin}</td>
                    <td><span class="badge badge-warning" id="verify-badge-${sn}">BELUM COCOK</span></td>
                `;
                verifyTableBody.appendChild(row);
            });

            setTimeout(() => verifyScanInput.focus(), 100);
        } else {
            verificationCard.style.opacity = '0.5';
            verificationCard.style.pointerEvents = 'none';
            verificationTableCard.style.display = 'none';
            
            // Auto-enable approval if there are only accessories to approve
            btnApproveTransfer.disabled = false;
        }
    }

    verifyScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const rawInput = this.value.trim();
            if (rawInput !== '') {
                // Split by space, comma, tab, or newline to support multiple SNs
                const sns = rawInput.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
                sns.forEach(sn => {
                    processVerifyScan(sn);
                });
            }
            this.value = '';
            verifyScanInput.focus();
        }
    });

    function processVerifyScan(sn) {
        transferAlert.style.display = 'none';

        if (!currentSjData) {
            triggerAlert("Pilih Surat Jalan terlebih dahulu.");
            return;
        }

        if (!currentSjData.devices.includes(sn)) {
            triggerAlert("Device SN " + sn + " TIDAK DITEMUKAN dalam manifes Surat Jalan ini!");
            return;
        }

        if (verifiedSns.has(sn)) {
            triggerAlert("Device SN " + sn + " sudah diverifikasi.");
            return;
        }

        // Verified success
        verifiedSns.add(sn);
        if (window.playBeep) window.playBeep('success');

        // Update Row UI
        const row = document.getElementById(`verify-row-${sn}`);
        if (row) {
            row.cells[0].style.color = 'var(--accent-emerald)';
            const badge = document.getElementById(`verify-badge-${sn}`);
            if (badge) {
                badge.className = 'badge badge-success';
                badge.innerText = 'SUDAH COCOK';
            }
        }

        verifiedCountSpan.innerText = verifiedSns.size;

        // Enable approval button if all matched
        if (verifiedSns.size === currentSjData.devices.length) {
            btnApproveTransfer.disabled = false;
        }
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
    makeSectionToggle('toggleSimSection', 'simSectionBody', 'Tambah SIM Card', 'Sembunyikan');

    // Stok tersedia: render awal + perbarui saat gudang asal berubah.
    fromWarehouseSelect.addEventListener('change', renderAvailableStock);

    // Default cerdas: bila gudang asal terpilih TIDAK punya stok IN_STOCK tetapi
    // gudang lain punya, otomatis pilih gudang dengan stok terbanyak agar panel
    // tidak kosong saat halaman dibuka (mis. active warehouse belum diset).
    (function smartDefaultOrigin() {
        const counts = stockCountByWarehouse();
        const current = fromWarehouseSelect.value;
        if ((counts[current] || 0) > 0) return;
        const best = Object.keys(counts)
            .filter(wh => wh && counts[wh] > 0)
            .sort((a, b) => counts[b] - counts[a])[0];
        if (best) setOriginWarehouse(best);
    })();

    renderAvailableStock();

    // Keyboard shortcut: Ctrl/Cmd + Enter untuk Release Shipment (tanpa lepas scanner)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && activeTab === 'create') {
            e.preventDefault();
            if (!btnReleaseShipment.disabled) {
                document.getElementById('createTransferForm').requestSubmit();
            } else if (typeof triggerAlert === 'function') {
                triggerAlert('Belum ada barang untuk dikirim. Scan perangkat atau tambahkan aksesoris/SIM dahulu.');
            }
        }
    });
    // ==========================================
    // RACK TRANSFER LOGIC
    // ==========================================
    const rtWarehouse = document.getElementById('rt_warehouse');
    const rtSourceRack = document.getElementById('rt_source_rack');
    const rtDestRack = document.getElementById('rt_dest_rack');
    const rtSourceBody = document.getElementById('rt_source_body');
    const rtDestBody = document.getElementById('rt_dest_body');
    const rtCheckAll = document.getElementById('rt_check_all');
    const btnTransferRack = document.getElementById('btnTransferRack');
    const rtSelectedCount = document.getElementById('rt_selected_count');
    const rackTransferForm = document.getElementById('rackTransferForm');

    function fetchRacks() {
        const warehouse = rtWarehouse.value;
        rtSourceRack.innerHTML = '<option value="">-- Pilih Rak Asal --</option>';
        rtDestRack.innerHTML = '<option value="">-- Pilih Rak Tujuan --</option>';
        rtSourceRack.disabled = true;
        rtDestRack.disabled = true;
        rtSourceBody.innerHTML = '<tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak asal terlebih dahulu</td></tr>';
        rtDestBody.innerHTML = '<tr><td style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak tujuan terlebih dahulu</td></tr>';
        updateRackTransferBtn();

        if (!warehouse) return;

        fetch(`/transfer/api/racks?warehouse=${warehouse}`)
            .then(res => res.json())
            .then(data => {
                if(data.length > 0) {
                    data.forEach(r => {
                        const opt = new Option(`${r.rack_code} - ${r.description || r.barcode}`, r.barcode);
                        rtSourceRack.add(opt.cloneNode(true));
                        rtDestRack.add(opt);
                    });
                    rtSourceRack.disabled = false;
                    rtDestRack.disabled = false;
                }
            })
            .catch(err => console.error(err));
    }

    rtWarehouse.addEventListener('change', fetchRacks);

    rtSourceRack.addEventListener('change', function() {
        const rack = this.value;
        if (!rack) {
            rtSourceBody.innerHTML = '<tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak asal terlebih dahulu</td></tr>';
            updateRackTransferBtn();
            return;
        }

        rtSourceBody.innerHTML = '<tr><td colspan="2" style="text-align: center;"><i class="fa-solid fa-spinner fa-spin"></i> Memuat...</td></tr>';
        
        fetch(`/transfer/api/rack-devices?rack=${rack}`)
            .then(res => res.json())
            .then(data => {
                rtSourceBody.innerHTML = '';
                if(data.length === 0) {
                    rtSourceBody.innerHTML = '<tr><td colspan="2" style="text-align: center; color: var(--text-muted); padding: 40px;">Rak ini kosong (tidak ada IN_STOCK)</td></tr>';
                } else {
                    data.forEach(d => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type="checkbox" class="rt-checkbox" name="device_ids[]" value="${d.id}"></td>
                            <td style="font-weight:600; color:var(--accent-blue);">
                                ${d.serial_number} <br>
                                <small style="color:var(--text-muted); font-weight:normal;">${d.model || 'Model Lain'} - ${d.unit_condition === 'BEKAS' ? '<span class="badge badge-warning" style="font-size:9px;">BEKAS</span>' : '<span class="badge badge-success" style="font-size:9px;">BARU</span>'}</small>
                            </td>
                        `;
                        rtSourceBody.appendChild(tr);
                    });
                }
                rtCheckAll.checked = false;
                updateRackTransferBtn();
            });
    });

    rtDestRack.addEventListener('change', function() {
        const rack = this.value;
        if (!rack) {
            rtDestBody.innerHTML = '<tr><td style="text-align: center; color: var(--text-muted); padding: 40px;">Pilih rak tujuan terlebih dahulu</td></tr>';
            updateRackTransferBtn();
            return;
        }

        rtDestBody.innerHTML = '<tr><td style="text-align: center;"><i class="fa-solid fa-spinner fa-spin"></i> Memuat...</td></tr>';
        
        fetch(`/transfer/api/rack-devices?rack=${rack}`)
            .then(res => res.json())
            .then(data => {
                rtDestBody.innerHTML = '';
                if(data.length === 0) {
                    rtDestBody.innerHTML = '<tr><td style="text-align: center; color: var(--text-muted); padding: 40px;">Rak ini kosong</td></tr>';
                } else {
                    data.forEach(d => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="font-weight:600; color:var(--text-secondary);">
                                ${d.serial_number} <br>
                                <small style="color:var(--text-muted); font-weight:normal;">${d.model || 'Model Lain'} - ${d.unit_condition === 'BEKAS' ? 'BEKAS' : 'BARU'}</small>
                            </td>
                        `;
                        rtDestBody.appendChild(tr);
                    });
                }
                updateRackTransferBtn();
            });
    });

    rtCheckAll.addEventListener('change', function() {
        document.querySelectorAll('.rt-checkbox').forEach(cb => cb.checked = this.checked);
        updateRackTransferBtn();
    });

    document.addEventListener('change', function(e) {
        if(e.target && e.target.classList.contains('rt-checkbox')) {
            updateRackTransferBtn();
        }
    });

    function updateRackTransferBtn() {
        const selected = document.querySelectorAll('.rt-checkbox:checked').length;
        rtSelectedCount.innerText = selected + ' dipilih';
        
        const hasDest = rtDestRack.value !== '';
        const sameRack = rtSourceRack.value === rtDestRack.value && rtSourceRack.value !== '';

        if (selected > 0 && hasDest && !sameRack) {
            btnTransferRack.disabled = false;
        } else {
            btnTransferRack.disabled = true;
        }
    }

    // Initialize rack if warehouse is already selected (e.g. warehouse-bound users)
    if (rtWarehouse.value) {
        fetchRacks();
    }

    rackTransferForm.addEventListener('submit', function(e) {
        if (rtSourceRack.value === rtDestRack.value) {
            e.preventDefault();
            alert('Rak Asal dan Rak Tujuan tidak boleh sama!');
        }
    });
</script>
@endsection
