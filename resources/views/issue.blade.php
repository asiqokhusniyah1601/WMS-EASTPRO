@extends('layouts.app')

<!--@yield('title', 'Issue Device to Technician | DLMS')-->

@section('styles')
<style>
    /* ====== Issue / Serah Terima — Focus Layout ====== */
    .issue-split { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; }
    @media (max-width: 1100px) { .issue-split { grid-template-columns: 1fr; } }
    .issue-sticky { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 20px; }

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

    /* Ringkasan serah terima di panel sticky */
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
        icon="fa-user-gear"
        title="Issue Device to Technician (Pengambilan Barang)"
        subtitle="Serahkan perangkat GPS/MDVR dan Aksesoris pendukung kepada teknisi lapangan untuk dipasang." />

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

    <!-- Tab navigation -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <button class="btn btn-outline active-tab-btn" id="tabAdminBtn" style="border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-primary);">
            <i class="fa-solid fa-user-check" style="color: var(--accent-blue);"></i> Admin: Serah Terima Perangkat
        </button>
        <button class="btn btn-outline" id="tabTechBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none;">
            <i class="fa-solid fa-mobile-screen-button" style="color: var(--accent-indigo);"></i> Teknisi: Digital Acceptance (Mobile View)
        </button>
    </div>

    <div id="issueAlert" class="alert-box alert-danger animate-fade-in" style="display: none;">
        <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="alert-message">
            <strong>PERINGATAN!</strong> <span id="issueAlertText"></span>
        </div>
    </div>

    <!-- PANEL 1: ADMIN ISSUE TO TECHNICIAN -->
    <div id="panelAdminIssue">
        <form action="{{ route('issue.post') }}" method="POST" id="issueForm">
            @csrf
            <div class="issue-split">
                <div>
                    <!-- Scan Area (fokus utama) -->
                    <div class="card scan-hero-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-barcode"></i>
                                <span>Scan Serial Number Perangkat</span>
                            </div>
                            <span class="badge badge-info">Langkah utama</span>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="issue_scan_input" style="font-weight: 600; color: var(--accent-blue);">SCAN BARCODE PERANGKAT (AUTO-FOCUS)</label>
                            <div style="position: relative;">
                                <i class="fa-solid fa-barcode" style="position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                <input type="text" id="issue_scan_input" class="form-control scan-target-input scan-hero" placeholder="Tembak barcode GPS/MDVR/Dashcam..." style="padding-left: 52px; font-size: 17px; font-weight: 600; border-color: rgba(59, 130, 246, 0.4); height: 54px;">
                            </div>
                            <small style="color: var(--text-muted); margin-top: 6px; display: block;">Status perangkat otomatis menjadi <strong>ISSUED</strong> (ke teknisi) atau <strong>INSTALLED</strong> (ke customer) setelah disubmit.</small>
                        </div>
                    </div>

                    <!-- Draft Scan List -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-list-ul"></i>
                                <span>Daftar Perangkat yang Diserahkan (<span id="issueCount">0</span> Item)</span>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Serial Number (SN)</th>
                                        <th>Tipe</th>
                                        <th>Status Saat Ini</th>
                                        <th>Plat Kendaraan (Opsional)</th>
                                        <th style="text-align: right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="issueTableBody">
                                    <tr id="issueEmptyPlaceholder">
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada perangkat di-scan. Silakan scan barcode di atas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SIM Card Pairing Section -->
                    <div class="card" id="simPairingCard" style="display: none;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-sim-card" style="color: var(--accent-rose);"></i>
                                <span>Pasangkan Kartu SIM (SIM Card Pairing)</span>
                            </div>
                            <span class="badge badge-warning">Opsional</span>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">Pilih Kartu SIM yang akan dipasangkan ke masing-masing perangkat GPS/MDVR. Hanya Kartu SIM dengan status IN_STOCK yang tersedia.</p>
                        <div id="simPairingRows"></div>
                    </div>

                    <!-- Accessories Manual Quantity Input (opsional, collapsible) -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-box-open"></i>
                                <span>Input Manual Aksesoris Pendukung</span>
                            </div>
                            <button type="button" class="opt-add-btn" id="toggleAccSection"><i class="fa-solid fa-plus"></i> Tambah Aksesoris</button>
                        </div>

                        <div id="accSectionBody" style="display: none;">
                        <!-- AI Suggestion Pills for Accessories -->
                        @if(isset($suggestedAccessories) && count($suggestedAccessories) > 0)
                        <div class="ai-suggestion-container" style="margin-bottom: 16px;">
                            <span class="ai-suggestion-title">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> AI Suggestion:
                            </span>
                            @foreach($suggestedAccessories as $sAcc)
                                <button type="button" class="ai-pill-btn quick-acc-btn"
                                    data-code="{{ $sAcc['code'] }}"
                                    onclick="quickSetAccQty('{{ $sAcc['code'] }}', 1)">
                                    <i class="fa-solid fa-bolt" style="font-size: 9px;"></i>
                                    {{ $sAcc['name'] ?? $sAcc['code'] }}
                                </button>
                            @endforeach
                        </div>
                        @endif
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            @foreach($accessories as $key => $acc)
                                <div style="background-color: var(--bg-primary); padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between;" data-acc-card="{{ $key }}">
                                    <div>
                                        <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">{{ $acc['name'] }}</div>
                                        <div style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;">Stok Gudang: <span class="acc-stock-val" data-acc="{{ $key }}">0</span> pcs</div>
                                    </div>
                                    <div style="margin-top: 12px; display: flex; align-items: center; gap: 8px;">
                                        <input type="hidden" name="acc_types[]" value="{{ $key }}">
                                        <label style="font-size: 12px; color: var(--text-secondary);">Kuantitas:</label>
                                        <input type="number" name="acc_qtys[]" id="issue_acc_{{ $key }}" data-acc="{{ $key }}" min="0" max="0" value="0" class="form-control acc-qty-issue" style="width: 70px; padding: 6px 8px; font-size: 12px; text-align: center;">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        </div><!-- /accSectionBody -->
                    </div>

                    <!-- Serah Terima Kartu GSM (standalone, opsional, collapsible) -->
                    <div class="card" id="issueSimCard">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-sim-card" style="color: var(--accent-indigo);"></i>
                                <span>Serahkan Kartu GSM (Opsional)</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="badge badge-info" id="issueSimSelectedBadge">0 dipilih</span>
                                <button type="button" class="opt-add-btn" id="toggleSimSection"><i class="fa-solid fa-plus"></i> Tambah Kartu GSM</button>
                            </div>
                        </div>
                        <div id="simSectionBody" style="display: none;">
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">Serahkan kartu GSM langsung ke teknisi/customer tanpa harus dipasang ke perangkat. Hanya SIM <strong>IN_STOCK</strong> di gudang asal yang tampil.</p>

                        <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
                            <div style="position:relative; flex:1; min-width:220px;">
                                <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:16px; top:13px; color:var(--text-muted);"></i>
                                <input type="text" id="issue_sim_search" class="form-control" placeholder="Cari MSISDN / provider..." style="padding-left:44px;" autocomplete="off">
                            </div>
                        </div>
                        <small style="color:var(--text-muted); display:block; margin-bottom:8px;"><strong id="issueSimAvail">0</strong> kartu GSM tersedia di gudang asal.</small>

                        <div class="table-wrapper" style="max-height: 240px; overflow-y: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" id="issue_sim_all"></th>
                                        <th>MSISDN</th>
                                        <th>Provider</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody id="issueSimBody">
                                    @forelse($simcards as $sim)
                                        <tr class="issue-sim-row" data-warehouse="{{ $sim['warehouse_code'] }}" data-search="{{ strtolower($sim['msisdn'] . ' ' . $sim['provider']) }}">
                                            <td><input type="checkbox" class="issue-sim-check" name="issue_sim_ids[]" value="{{ $sim['id'] }}"></td>
                                            <td style="font-weight:600; color:var(--accent-indigo);">{{ $sim['msisdn'] }}</td>
                                            <td>{{ $sim['provider'] }}</td>
                                            <td>{{ $sim['category'] ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr id="issueSimEmpty"><td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">Belum ada SIM di gudang manapun. Terima dulu via Penerimaan Kartu GSM.</td></tr>
                                    @endforelse
                                    <tr id="issueSimNone" style="display:none;"><td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">Tidak ada SIM IN_STOCK di gudang asal ini.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        </div><!-- /simSectionBody -->
                    </div>
                </div>

                <!-- Assignment details side panel (sticky) -->
                <div>
                    <div class="issue-sticky">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fa-solid fa-circle-user"></i>
                                <span>Detail Penerima</span>
                            </div>
                        </div>

                        <!-- Ringkasan barang yang diserahkan -->
                        <div class="ship-summary">
                            <div class="ss-box"><div class="ss-num" id="sumDevices">0</div><div class="ss-lbl">Perangkat</div></div>
                            <div class="ss-box"><div class="ss-num" id="sumAcc">0</div><div class="ss-lbl">Aksesoris</div></div>
                            <div class="ss-box"><div class="ss-num" id="sumSim">0</div><div class="ss-lbl">Kartu GSM</div></div>
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label class="form-label">Tujuan Penyerahan</label>
                            <div style="display: flex; gap: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="target_type" value="technician" checked onchange="toggleTargetType('technician')">
                                    <span style="font-weight: 500;">Kepada Teknisi</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="radio" name="target_type" value="customer" onchange="toggleTargetType('customer')">
                                    <span style="font-weight: 500;">Kepada Pelanggan (Self-Install)</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="techSelectWrapper">
                            <label for="technician_select">Nama Teknisi Lapangan</label>
                            <select name="technician" id="technician_select" class="form-control">
                                @foreach($technicians as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}@if(!empty($technicianAreas[$key])) — {{ $technicianAreas[$key] }}@endif</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" id="custSelectWrapper" style="display: none;">
                            <label for="customer_select">Nama Pelanggan / Customer</label>
                            <select name="customer" id="customer_select" class="form-control">
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Tambahkan customer di menu Master Data jika belum ada.</small>
                        </div>

                        <div class="form-group" id="warehouseSelectWrapper" style="margin-top: 16px;">
                            <label for="warehouse_select">Gudang Asal (Sumber Stok) <span style="color: var(--accent-rose);">*</span></label>
                            <select name="warehouse" id="warehouse_select" class="form-control" required>
                                <option value="">-- Pilih Gudang Asal --</option>
                                @foreach(\App\Models\Warehouse::all() as $wh)
                                    <option value="{{ $wh->code }}" {{ $wh->code == session('active_warehouse_code') ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->type }})</option>
                                @endforeach
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Daftar device, kartu SIM, dan stok aksesoris difilter berdasarkan gudang ini.</small>
                        </div>

                        <div style="margin-top: 24px;">
                            <button type="submit" id="btnAssignTech" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px;" disabled>
                                <i class="fa-solid fa-user-plus"></i> Serahkan Perangkat
                            </button>
                            <div class="shortcut-hint">Pintasan: <kbd>Ctrl</kbd> + <kbd>Enter</kbd> untuk Serahkan Perangkat</div>
                        </div>
                    </div>
                    </div><!-- /issue-sticky -->
                </div>
            </div>
        </form>
    </div>

    <!-- PANEL 2: TECHNICIAN MOBILE ACCEPTANCE VIEW -->
    <div id="panelTechIssue" style="display: none; justify-content: center; align-items: center; padding-top: 20px;">
        <!-- Smartphone Container Frame -->
        <div style="width: 360px; min-height: 600px; background-color: #0f172a; border: 8px solid #334155; border-radius: 36px; padding: 20px; box-shadow: var(--shadow-xl); position: relative;">
            
            <!-- Phone Notch -->
            <div style="width: 110px; height: 18px; background-color: #334155; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; position: absolute; top: 0; left: 50%; transform: translateX(-50%); z-index: 10;"></div>
            
            <div style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                
                <!-- Phone Header -->
                <div style="margin-top: 10px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-size: 11px; font-weight: 600; color: var(--text-secondary);">DLMS Mobile</div>
                    <div style="font-size: 11px; color: var(--accent-indigo); font-weight: 600;"><i class="fa-solid fa-signal"></i> 4G LTE</div>
                </div>

                <!-- Phone Body -->
                <div style="flex-grow: 1; padding: 16px 0; overflow-y: auto;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fa-solid fa-signature" style="font-size: 32px; color: var(--accent-indigo);"></i>
                        <h4 style="font-size: 16px; font-weight: 700; margin-top: 8px;">Serah Terima Digital</h4>
                        <p style="font-size: 11px; color: var(--text-secondary);">Konfirmasi serah terima fisik barang oleh Teknisi Lapangan.</p>
                    </div>

                    <!-- Selected Technician state -->
                    <div style="background-color: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: var(--text-muted); display: block;">Akun Login Aktif:</span>
                        <strong id="mobileTechName" style="font-size: 14px; color: var(--text-primary);">Budi Santoso</strong>
                    </div>

                    <!-- Pending Acceptance Items -->
                    <h5 style="font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--text-secondary);">Perangkat Pending Terima:</h5>
                    <div id="mobilePendingItems" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px;">
                        <!-- Mock Dynamic rows loaded from JS -->
                    </div>
                </div>

                <!-- Phone Footer / Action -->
                <div style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px;">
                    <button type="button" id="btnMobileAccept" class="btn btn-success" style="width: 100%; justify-content: center; font-size: 13px; padding: 10px;">
                        <i class="fa-solid fa-signature"></i> Tanda Tangan & Terima Fisik
                    </button>
                    <p style="text-align: center; font-size: 9px; color: var(--text-muted); margin-top: 6px;">Dengan mengklik Terima, Anda menyatakan secara hukum telah menerima barang-barang di atas dalam kondisi baik.</p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const inStockDevices = @json($devices);
    const techniciansList = @json($technicians);
    const availableSimcards = @json($simcards ?? []);
    const warehouseAccessories = @json($warehouseAccessories ?? []);
    const warehouseSelect = document.getElementById('warehouse_select');

    const tabAdminBtn = document.getElementById('tabAdminBtn');
    const tabTechBtn = document.getElementById('tabTechBtn');
    const panelAdminIssue = document.getElementById('panelAdminIssue');
    const panelTechIssue = document.getElementById('panelTechIssue');
    const issueScanInput = document.getElementById('issue_scan_input');
    const emulatorTarget = document.getElementById('emulatorTarget');

    let activeTab = 'admin';

    function toggleTargetType(type) {
        const techSelect = document.getElementById('techSelectWrapper');
        const custSelect = document.getElementById('custSelectWrapper');
        if (type === 'technician') {
            techSelect.style.display = 'block';
            document.getElementById('technician_select').setAttribute('required', 'required');
            custSelect.style.display = 'none';
            document.getElementById('customer_select').removeAttribute('required');
        } else {
            techSelect.style.display = 'none';
            document.getElementById('technician_select').removeAttribute('required');
            custSelect.style.display = 'block';
            document.getElementById('customer_select').setAttribute('required', 'required');
        }
    }

    // Tabs navigation
    tabAdminBtn.addEventListener('click', () => {
        activeTab = 'admin';
        tabAdminBtn.style.borderBottomColor = 'var(--accent-blue)';
        tabAdminBtn.style.color = 'var(--text-primary)';
        tabTechBtn.style.borderBottomColor = 'transparent';
        tabTechBtn.style.color = 'var(--text-secondary)';
        panelAdminIssue.style.display = 'block';
        panelTechIssue.style.display = 'none';
        if (emulatorTarget) {
            emulatorTarget.value = '.scan-target-input';
        }
        issueScanInput.focus();
    });

    tabTechBtn.addEventListener('click', () => {
        activeTab = 'tech';
        tabTechBtn.style.borderBottomColor = 'var(--accent-indigo)';
        tabTechBtn.style.color = 'var(--text-primary)';
        tabAdminBtn.style.borderBottomColor = 'transparent';
        tabAdminBtn.style.color = 'var(--text-secondary)';
        panelAdminIssue.style.display = 'none';
        panelTechIssue.style.display = 'flex';
        if (emulatorTarget) {
            emulatorTarget.value = '#manual_sn_input'; // fallback
        }
        loadMobileAcceptance();
    });

    // Enforce focus
    document.addEventListener('click', (e) => {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'OPTION' && !e.target.closest('.scanner-emulator') && !e.target.closest('#panelTechIssue')) {
            if (activeTab === 'admin') {
                issueScanInput.focus();
            }
        }
    });

    // ==========================================
    // ADMIN ISSUE TO TECHNICIAN LOGIC
    // ==========================================
    const issueDraftSns = new Set();
    const issueTableBody = document.getElementById('issueTableBody');
    const issueEmptyPlaceholder = document.getElementById('issueEmptyPlaceholder');
    const issueCountSpan = document.getElementById('issueCount');
    const btnAssignTech = document.getElementById('btnAssignTech');
    const issueAlert = document.getElementById('issueAlert');
    const issueAlertText = document.getElementById('issueAlertText');

    issueScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const sn = this.value.trim();
            if (sn) {
                processIssueScan(sn);
            }
            this.value = '';
            issueScanInput.focus();
        }
    });

    function processIssueScan(sn) {
        issueAlert.style.display = 'none';

        if (issueDraftSns.has(sn)) {
            triggerAlert("Device SN " + sn + " sudah dimasukkan ke daftar issue.");
            return;
        }

        const matchDev = inStockDevices.find(d => d.serial_number === sn);
        if (!matchDev) {
            triggerAlert("Device SN " + sn + " TIDAK DITEMUKAN di stock gudang.");
            return;
        }

        const wh = warehouseSelect ? warehouseSelect.value : '';
        if (!wh) {
            triggerAlert("Pilih Gudang Asal terlebih dahulu sebelum scan device.");
            return;
        }
        if (matchDev.warehouse_code !== wh) {
            triggerAlert("Device SN " + sn + " bukan milik gudang asal terpilih. Pilih gudang yang sesuai atau transfer dahulu.");
            return;
        }

        issueDraftSns.add(sn);
        if (issueEmptyPlaceholder) issueEmptyPlaceholder.style.display = 'none';
        if (window.playBeep) window.playBeep('success');

        const cond = (matchDev.unit_condition === 'BEKAS') ? 'BEKAS' : 'BARU';
        const condCls = cond === 'BEKAS' ? 'badge-warning' : 'badge-success';

        const newRow = document.createElement('tr');
        newRow.id = `issue-row-${sn}`;
        newRow.className = 'animate-fade-in row-added';
        newRow.innerHTML = `
            <td>${issueDraftSns.size}</td>
            <td style="font-weight:600; color:var(--accent-blue);">
                <i class="fa-solid fa-circle-check" style="color:var(--accent-emerald); margin-right:6px;" title="Sudah masuk daftar serah terima"></i>${sn}
                <input type="hidden" name="sns[]" value="${sn}">
            </td>
            <td><span class="badge badge-info">${matchDev.type}</span> <span class="badge ${condCls}">${cond}</span></td>
            <td><span class="badge badge-success">${matchDev.status}</span></td>
            <td>
                <input type="text" name="vehicle_plates[${sn}]" class="form-control" placeholder="B 1234 CD" style="font-size: 12px; height: 30px; width: 120px;">
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon-sm" onclick="removeIssueRow('${sn}')" style="padding:4px 8px; font-size:11px;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        issueTableBody.appendChild(newRow);
        issueCountSpan.innerText = issueDraftSns.size;
        updateSubmitState();
        buildSimPairing();
    }

    function removeIssueRow(sn) {
        const row = document.getElementById(`issue-row-${sn}`);
        if (row) row.remove();
        issueDraftSns.delete(sn);
        issueCountSpan.innerText = issueDraftSns.size;
        if (issueDraftSns.size === 0) {
            if (issueEmptyPlaceholder) issueEmptyPlaceholder.style.display = 'table-row';
        }
        // Re-index remaining rows
        Array.from(issueTableBody.querySelectorAll('tr:not(#issueEmptyPlaceholder)')).forEach((tr, i) => {
            tr.cells[0].innerText = i + 1;
        });
        updateSubmitState();
        buildSimPairing();
    }

    // Check if any accessory has qty > 0
    function hasAccessoryInput() {
        const qtyInputs = document.querySelectorAll('input[name="acc_qtys[]"]');
        for (const input of qtyInputs) {
            if (parseInt(input.value) > 0) return true;
        }
        return false;
    }

    // Unified submit button state: enable if device OR accessory OR GSM exists
    function updateSubmitState() {
        const hasDevices = issueDraftSns.size > 0;
        const hasAcc = hasAccessoryInput();
        const simCount = document.querySelectorAll('.issue-sim-check:checked').length;
        const hasSim = simCount > 0;
        btnAssignTech.disabled = !(hasDevices || hasAcc || hasSim);

        // Ringkasan serah terima di panel sticky
        let accUnits = 0;
        document.querySelectorAll('input[name="acc_qtys[]"]').forEach(i => { accUnits += parseInt(i.value || 0) || 0; });
        const sumD = document.getElementById('sumDevices');
        const sumA = document.getElementById('sumAcc');
        const sumS = document.getElementById('sumSim');
        if (sumD) sumD.innerText = issueDraftSns.size;
        if (sumA) sumA.innerText = accUnits;
        if (sumS) sumS.innerText = simCount;
    }

    // Listen for accessory qty changes
    document.querySelectorAll('input[name="acc_qtys[]"]').forEach(input => {
        input.addEventListener('input', updateSubmitState);
        input.addEventListener('change', updateSubmitState);
    });

    // Validate before submit: if only accessories, warehouse must be selected
    document.getElementById('issueForm').addEventListener('submit', function(e) {
        const hasDevices = issueDraftSns.size > 0;
        const hasAcc = hasAccessoryInput();
        const hasSim = document.querySelectorAll('.issue-sim-check:checked').length > 0;
        const warehouseSelect = document.getElementById('warehouse_select');

        if (!warehouseSelect || !warehouseSelect.value) {
            e.preventDefault();
            triggerAlert('Gudang Asal wajib dipilih.');
            if (warehouseSelect) warehouseSelect.focus();
            return false;
        }

        if (!hasDevices && !hasAcc && !hasSim) {
            e.preventDefault();
            triggerAlert('Harus ada minimal 1 perangkat, aksesoris, atau kartu GSM yang diserahkan.');
            return false;
        }
    });

    // Quick set accessory qty from AI suggestion pills
    function quickSetAccQty(accCode, qty) {
        const input = document.getElementById('issue_acc_' + accCode);
        if (input) {
            input.value = parseInt(input.value || 0) + qty;
            if (window.playBeep) window.playBeep('success');
            updateSubmitState();
        }
    }

    function triggerAlert(msg) {
        if (window.playBeep) window.playBeep('error');
        issueAlertText.innerText = msg;
        issueAlert.style.display = 'flex';
        issueAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ==========================================
    // SIM PAIRING + STOK AKSESORIS PER GUDANG
    // ==========================================
    function currentWarehouse() {
        return warehouseSelect ? warehouseSelect.value : '';
    }

    // Bangun baris pairing SIM per device (SIM difilter sesuai gudang asal).
    function buildSimPairing() {
        const card = document.getElementById('simPairingCard');
        const container = document.getElementById('simPairingRows');
        if (!card || !container) return;

        if (issueDraftSns.size === 0) {
            card.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const wh = currentWarehouse();
        const sims = availableSimcards.filter(s => s.warehouse_code === wh);

        card.style.display = 'block';
        container.innerHTML = '';

        issueDraftSns.forEach(sn => {
            const dev = inStockDevices.find(d => d.serial_number === sn);
            let options = '<option value="">-- Tanpa SIM --</option>';
            sims.forEach(s => {
                options += `<option value="${s.msisdn}">${s.msisdn} (${s.provider})</option>`;
            });
            const row = document.createElement('div');
            row.style.cssText = 'display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--border-color);';
            row.innerHTML = `
                <div style="flex:1;">
                    <span style="font-size:12px; font-weight:600; color:var(--accent-blue);">${sn}</span>
                    <span style="font-size:11px; color:var(--text-secondary); display:block;">${dev ? dev.type : 'Device'}</span>
                </div>
                <select name="sim_pairings[${sn}]" class="form-control" style="max-width:240px; font-size:12px;">${options}</select>
            `;
            container.appendChild(row);
        });

        if (sims.length === 0) {
            const note = document.createElement('div');
            note.style.cssText = 'font-size:11px; color:var(--text-muted); padding-top:8px;';
            note.innerText = 'Tidak ada kartu SIM IN_STOCK di gudang ini.';
            container.appendChild(note);
        }
    }

    // Perbarui tampilan stok + batas qty aksesoris sesuai gudang asal.
    function updateAccessoryStocks() {
        const stockMap = warehouseAccessories[currentWarehouse()] || {};
        document.querySelectorAll('.acc-stock-val').forEach(el => {
            el.innerText = stockMap[el.dataset.acc] || 0;
        });
        document.querySelectorAll('.acc-qty-issue').forEach(input => {
            const max = parseInt(stockMap[input.dataset.acc] || 0);
            input.max = max;
            if (parseInt(input.value || 0) > max) input.value = max;
        });
        updateSubmitState();
    }

    // Filter daftar "Serahkan Kartu GSM" (standalone) sesuai gudang asal.
    function filterIssueSim() {
        const wh = currentWarehouse();
        const search = (document.getElementById('issue_sim_search')?.value || '').trim().toLowerCase();
        let avail = 0;
        document.querySelectorAll('.issue-sim-row').forEach(row => {
            const matchWh = row.dataset.warehouse === wh;
            const matchSearch = !search || (row.dataset.search || '').includes(search);
            const show = matchWh && matchSearch;
            row.style.display = show ? '' : 'none';
            if (!show) {
                const cb = row.querySelector('.issue-sim-check');
                if (cb) cb.checked = false;
            }
            if (matchWh) avail++;
        });
        const noneRow = document.getElementById('issueSimNone');
        if (noneRow) noneRow.style.display = (avail === 0) ? 'table-row' : 'none';
        const availEl = document.getElementById('issueSimAvail');
        if (availEl) availEl.innerText = avail;
        updateIssueSimBadge();
    }

    function updateIssueSimBadge() {
        const badge = document.getElementById('issueSimSelectedBadge');
        if (badge) badge.innerText = document.querySelectorAll('.issue-sim-check:checked').length + ' dipilih';
        updateSubmitState();
    }

    const issueSimSearch = document.getElementById('issue_sim_search');
    const issueSimAll = document.getElementById('issue_sim_all');
    if (issueSimSearch) issueSimSearch.addEventListener('input', filterIssueSim);
    if (issueSimAll) {
        issueSimAll.addEventListener('change', function () {
            document.querySelectorAll('.issue-sim-row').forEach(row => {
                if (row.style.display === 'none') return;
                const cb = row.querySelector('.issue-sim-check');
                if (cb) cb.checked = this.checked;
            });
            updateIssueSimBadge();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('issue-sim-check')) updateIssueSimBadge();
    });

    if (warehouseSelect) {
        warehouseSelect.addEventListener('change', () => {
            // Gudang berganti: bersihkan draft device karena stok berbeda antar gudang.
            Array.from(issueDraftSns).forEach(sn => removeIssueRow(sn));
            updateAccessoryStocks();
            buildSimPairing();
            filterIssueSim();
        });
    }

    // Inisialisasi sesuai gudang aktif saat halaman dimuat.
    updateAccessoryStocks();
    filterIssueSim();

    // ==========================================
    // MOBILE VIEW EMULATION LOGIC
    // ==========================================
    const mobileTechName = document.getElementById('mobileTechName');
    const mobilePendingItems = document.getElementById('mobilePendingItems');
    const btnMobileAccept = document.getElementById('btnMobileAccept');

    function loadMobileAcceptance() {
        // Find technician
        const selectedTechKey = document.getElementById('technician_select').value;
        const techName = techniciansList[selectedTechKey] || 'Budi Santoso';
        mobileTechName.innerText = techName;

        mobilePendingItems.innerHTML = '';
        
        if (issueDraftSns.size === 0) {
            mobilePendingItems.innerHTML = `
                <div style="text-align: center; color: var(--text-muted); font-size: 11px; padding: 20px;">
                    Belum ada barang ditunjuk untuk teknisi ini. Silakan scan di panel Admin terlebih dahulu.
                </div>
            `;
            btnMobileAccept.disabled = true;
            return;
        }

        btnMobileAccept.disabled = false;
        
        issueDraftSns.forEach(sn => {
            const matchDev = inStockDevices.find(d => d.serial_number === sn);
            const devType = matchDev ? matchDev.type : 'Device';
            
            const card = document.createElement('div');
            card.style.backgroundColor = 'rgba(255,255,255,0.05)';
            card.style.border = '1px solid rgba(255,255,255,0.08)';
            card.style.borderRadius = '6px';
            card.style.padding = '8px 12px';
            card.style.display = 'flex';
            card.style.justifyContent = 'space-between';
            card.style.alignItems = 'center';
            card.innerHTML = `
                <div>
                    <span style="font-size: 12px; font-weight: 600; color: var(--accent-blue); display: block;">${sn}</span>
                    <span style="font-size: 10px; color: var(--text-secondary);">${devType}</span>
                </div>
                <div>
                    <span class="badge badge-warning" style="font-size: 8px;">PENDING</span>
                </div>
            `;
            mobilePendingItems.appendChild(card);
        });
    }

    btnMobileAccept.addEventListener('click', () => {
        // Trigger click on actual submit form button
        const form = document.getElementById('issueForm');
        if (form && issueDraftSns.size > 0) {
            form.submit();
        }
    });

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

    // Keyboard shortcut: Ctrl/Cmd + Enter untuk Serahkan Perangkat (tanpa lepas scanner)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && activeTab === 'admin') {
            e.preventDefault();
            if (!btnAssignTech.disabled) {
                document.getElementById('issueForm').requestSubmit();
            } else {
                triggerAlert('Belum ada barang untuk diserahkan. Scan perangkat, isi aksesoris, atau pilih kartu GSM dahulu.');
            }
        }
    });
</script>
@endsection
