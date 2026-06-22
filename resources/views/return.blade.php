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

                    <div class="table-wrapper" style="margin-top: 8px;">
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
                                <i class="fa-solid fa-wand-magic-sparkles"></i> AI Suggestion:
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

                        <div class="form-group">
                            <label class="form-label">Gudang Penerima (Lokasi Saat Ini)</label>
                            <select name="warehouse" class="form-control" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach(\App\Models\Warehouse::all() as $wh)
                                    <option value="{{ $wh->code }}" {{ $wh->code == session('active_warehouse_code') ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->type }})</option>
                                @endforeach
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Gudang tempat barang ini diterima.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dikembalikan Oleh <span style="color: var(--text-muted); font-weight: 400;">(opsional)</span></label>
                            <select name="return_from_type" id="return_from_type" class="form-control" style="margin-bottom: 8px;" onchange="toggleReturnHolder()">
                                <option value="">— Tidak ditentukan —</option>
                                <option value="technician">Teknisi</option>
                                <option value="customer">Customer</option>
                            </select>
                            <select name="return_technician" id="return_technician" class="form-control" style="display: none; margin-bottom: 8px;">
                                <option value="">-- Pilih Teknisi --</option>
                                @foreach($technicians as $t)
                                    <option value="{{ $t->code }}">{{ $t->name }} ({{ $t->code }})@if(!empty($t->area)) — {{ $t->area }}@endif</option>
                                @endforeach
                            </select>
                            <select name="return_customer" id="return_customer" class="form-control" style="display: none;">
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Pilih pemegang asal agar saldo aksesoris di teknisi/customer berkurang otomatis di laporan.</small>
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
    </form>
</div>

<script>
    let snCount = 0;

    function toggleReturnHolder() {
        const type = document.getElementById('return_from_type').value;
        const techSel = document.getElementById('return_technician');
        const custSel = document.getElementById('return_customer');

        techSel.style.display = (type === 'technician') ? 'block' : 'none';
        custSel.style.display = (type === 'customer') ? 'block' : 'none';

        if (type !== 'technician') techSel.value = '';
        if (type !== 'customer') custSel.value = '';
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
        
        snCount++;
        const tbody = document.querySelector('#scanned_table tbody');
        const tr = document.createElement('tr');
        const rowId = `row-sn-${sn}`;
        tr.setAttribute('id', rowId);
        tr.className = 'animate-fade-in row-added';

        tr.innerHTML = `
            <td>${snCount}</td>
            <td style="font-weight: 600; color: var(--accent-blue);">
                <i class="fa-solid fa-circle-check" style="color:var(--accent-emerald); margin-right:6px;" title="Sudah masuk daftar return"></i>${sn}
                <input type="hidden" name="sns[]" value="${sn}">
            </td>
            <td style="text-align: right;">
                <button type="button" class="btn btn-outline" style="color: var(--danger-color); padding: 4px 8px;" onclick="document.getElementById('${rowId}').remove(); if(window.playBeep) window.playBeep('error'); checkSubmitBtn();">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        input.value = '';
        input.focus();
        if (window.playBeep) window.playBeep('success');
        checkSubmitBtn();
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
            if (btn && !btn.disabled) {
                e.preventDefault();
                document.getElementById('returnForm').requestSubmit();
            }
        }
    });

    // Auto-focus area scan saat halaman dimuat
    const snInputEl = document.getElementById('sn_input');
    if (snInputEl) snInputEl.focus();
</script>
@endsection
