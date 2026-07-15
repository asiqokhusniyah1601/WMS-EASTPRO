@extends('layouts.app')

@section('title', 'Sesi Stock Opname | DLMS')

@section('styles')
<style>
    .scan-area { background: var(--bg-secondary); padding: 24px; border-radius: 8px; border: 1px solid var(--border-color); }
    .result-panel { margin-top: 24px; }
    .status-badge { font-size: 14px; padding: 6px 12px; }
    .qty-controls { display: flex; align-items: center; gap: 8px; }
    .qty-btn { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); cursor: pointer; }
    .qty-btn:hover { background: var(--bg-hover); }
    .list-scanned { max-height: 500px; overflow-y: auto; }
    .item-scanned { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color); }
    .item-scanned:last-child { border-bottom: none; }
    .pagination { display: flex; list-style: none; padding: 0; margin: 0; gap: 4px; align-items: center; justify-content: flex-end; margin-top: 16px; }
    .pagination li a, .pagination li span { display: flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-size: 13px; text-decoration: none; transition: all 0.2s; }
    .pagination li a:hover { background: rgba(59,130,246,0.12); border-color: var(--accent-blue); color: var(--accent-blue); }
    .pagination li.active span { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); font-weight: 600; }
    .pagination li.disabled span { opacity: 0.5; cursor: not-allowed; }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <a href="{{ route('stock.opname', ['warehouse' => $session->warehouse_code]) }}" class="btn btn-sm btn-outline" style="margin-bottom: 8px;">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
            <h2 style="margin: 0;">Sesi Opname #{{ $session->id }}</h2>
            <div style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;">
                Gudang: <strong>{{ $session->warehouse->name }}</strong> | 
                Operator: <strong>{{ $session->startedBy->name }}</strong> |
                Mulai: {{ $session->created_at->format('d M Y H:i') }}
            </div>
        </div>
        <div>
            @if($session->isOpen())
                <span class="badge badge-warning status-badge" style="margin-right: 8px;">Sedang Berjalan</span>
                @if(auth()->user()->hasRole('super_admin', 'admin', 'pic'))
                <form action="{{ route('stock.opname.session.cancel', $session->id) }}" method="POST" style="display: inline-block; margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger);" onclick="return confirm('PERINGATAN: Membatalkan sesi akan MENGHAPUS seluruh data scan pada sesi ini dan tidak dapat dikembalikan.\n\nApakah Anda yakin ingin membatalkan sesi ini?')">
                        <i class="fa-solid fa-times"></i> Batalkan Sesi
                    </button>
                </form>
                @endif
            @else
                <span class="badge badge-success status-badge">Selesai</span>
            @endif
        </div>
    </div>

    @if($session->isOpen())
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
            <!-- Scan Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-barcode"></i> Scan Barcode
                    </div>
                </div>
                <div class="card-body scan-area">
                    <div class="form-group">
                        <label>1. Lokasi Rak/Row (RAK-XX-ROW-XX)</label>
                        <input type="text" id="scan_location" class="form-control" placeholder="Scan barcode lokasi..." autocomplete="off">
                        <div id="loc_info" style="font-size: 13px; color: var(--accent-blue); margin-top: 4px; display: none;"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label>Jenis / Kategori</label>
                            <select id="sel_jenis" class="form-control" onchange="onJenisChange()">
                                <option value="">-- Deteksi Otomatis --</option>
                                <option value="accessory">Aksesoris (Otomatis)</option>
                                <option value="simcard">SIM Card (Otomatis)</option>
                                @foreach($deviceModels->keys() as $type)
                                    <option value="device_{{ $type }}">Device - {{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Tipe Alat (Model)</label>
                            <select id="sel_tipe" class="form-control" disabled>
                                <option value="">-- Pilih Model --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label>2. Barang (Device SN / Aksesoris / MSISDN)</label>
                        <input type="text" id="scan_item" class="form-control" placeholder="Scan barcode barang..." autocomplete="off" disabled>
                        <div id="multi_sn_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;"></div>
                        <div id="item_info" style="font-size: 13px; color: var(--accent-indigo); margin-top: 4px; display: none;"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label>3. Quantity (Fisik)</label>
                            <div class="qty-controls">
                                <button type="button" class="qty-btn" id="btn_minus" disabled><i class="fa-solid fa-minus"></i></button>
                                <input type="number" id="scan_qty" class="form-control" value="1" min="1" style="width: 80px; text-align: center;" disabled>
                                <button type="button" class="qty-btn" id="btn_plus" disabled><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label>Satuan (Opsional)</label>
                            <input type="text" id="scan_unit" class="form-control" placeholder="Contoh: meter, pack, dll." disabled>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="btn_save_scan" style="width: 100%; margin-top: 24px;" disabled>
                        <i class="fa-solid fa-check"></i> Simpan Item
                    </button>
                </div>
            </div>

            <!-- Scanned Items List -->
            <div class="card">
                <div class="card-header" style="justify-content: space-between;">
                    <div class="card-title">
                        <i class="fa-solid fa-clipboard-list"></i> Item Terscan (<span id="scan_count">{{ $items->total() }}</span>)
                    </div>
                    @if(auth()->user()->hasRole('super_admin', 'admin', 'pic'))
                    <form action="{{ route('stock.opname.session.complete', $session->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Selesaikan sesi ini dan jalankan crosscheck otomatis?')">
                            Selesaikan & Crosscheck
                        </button>
                    </form>
                    @endif
                </div>
                <div class="list-scanned" id="scanned_list">
                    @forelse($items as $item)
                        <div class="item-scanned" id="item_{{ $item->id }}">
                            <div>
                                <strong>{{ $item->item_name ?: $item->item_code }}</strong>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    {{ strtoupper($item->item_type) }} | {{ $item->item_code }}<br>
                                    Lokasi: {{ $item->location_barcode }}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 16px; font-weight: bold;">Qty: {{ $item->qty_physical }} {{ $item->unit }}</div>
                                <button type="button" class="btn btn-sm btn-outline btn-delete-item" data-id="{{ $item->id }}" style="color: var(--danger); border-color: transparent; padding: 2px 6px; margin-top: 4px;">
                                    <i class="fa-solid fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    @empty
                        <div id="empty_scan" style="padding: 24px; text-align: center; color: var(--text-muted);">
                            Belum ada item yang discan.
                        </div>
                    @endforelse
                </div>
                @if($items->hasPages())
                <div style="padding: 12px 16px; border-top: 1px solid var(--border-color);">
                    {{ $items->links('pagination::bootstrap-4') }}
                </div>
                @endif
            </div>
        </div>

    @else
        <!-- RESULT PANEL -->
        <div class="card result-panel">
            <div class="card-header" style="justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div class="card-title">
                    <i class="fa-solid fa-chart-pie"></i> Hasil Crosscheck Stok
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <a href="{{ route('stock.opname.export.raw', $session->id) }}" class="btn btn-outline" target="_blank">
                        <i class="fa-solid fa-file-excel" style="color: var(--success);"></i> Data Scan (Raw)
                    </a>
                    <a href="{{ route('stock.opname.export.result', $session->id) }}" class="btn btn-outline" target="_blank">
                        <i class="fa-solid fa-file-excel" style="color: var(--success);"></i> Hasil Crosscheck
                    </a>
                    
                    @if(auth()->user()->hasRole('super_admin', 'admin', 'pic') && empty($session->crosscheck_result['applied']))
                        <form action="{{ route('stock.opname.session.apply', $session->id) }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Terapkan koreksi stok ke database secara permanen?')">
                                <i class="fa-solid fa-save"></i> Terapkan Koreksi ke Sistem
                            </button>
                        </form>
                    @elseif(!empty($session->crosscheck_result['applied']))
                        <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> Telah Diterapkan</span>
                    @endif
                </div>
            </div>
            
            <div class="card-body">
                @php
                    $res = $session->crosscheck_result;
                    $stats = $res['stats'] ?? ['sesuai' => 0, 'selisih' => 0];
                    $details = $res['details'] ?? ['device' => [], 'accessory' => [], 'simcard' => []];
                @endphp

                <div style="display: flex; gap: 16px; margin-bottom: 24px;">
                    <div style="flex: 1; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); padding: 16px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: var(--success);">{{ $stats['sesuai'] }}</div>
                        <div style="color: var(--text-secondary); font-size: 14px;">Item Sesuai</div>
                    </div>
                    <div style="flex: 1; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); padding: 16px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: var(--danger);">{{ $stats['selisih'] }}</div>
                        <div style="color: var(--text-secondary); font-size: 14px;">Item Selisih</div>
                    </div>
                </div>

                <!-- Selisih -->
                <h3 style="margin-top: 0; color: var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Item Selisih</h3>
                <div class="table-wrapper" style="margin-bottom: 32px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipe</th>
                                <th>Kode / SN</th>
                                <th>Nama Barang</th>
                                <th>Lokasi Rak</th>
                                <th style="text-align: center;">Sistem</th>
                                <th style="text-align: center;">Fisik</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $hasSelisih = false; 
                                $hiddenDiff = $res['hidden_device_diff'] ?? [];
                            @endphp
                            @foreach(['device', 'accessory', 'simcard'] as $type)
                                @foreach($details[$type] as $row)
                                    @if(str_starts_with($row['status'], 'SELISIH'))
                                        @php $hasSelisih = true; @endphp
                                        <tr style="background: rgba(239, 68, 68, 0.05);">
                                            <td><span class="badge badge-secondary">{{ strtoupper($type) }}</span></td>
                                            <td><strong>{{ $row['code'] }}</strong></td>
                                            <td>{{ $row['name'] ?? '-' }}</td>
                                            <td style="font-size: 12px; color: var(--text-muted);">-</td>
                                            <td style="text-align: center;">{{ $row['sys_qty'] }}</td>
                                            <td style="text-align: center; font-weight: bold;">{{ $row['phys_qty'] }}</td>
                                            <td style="color: var(--danger);">{{ $row['status'] }}</td>
                                        </tr>
                                        
                                        {{-- Detail SN untuk Device --}}
                                        @if($type === 'device')
                                            @foreach($hiddenDiff as $snDetail)
                                                @if(($snDetail['name'] ?? '') === $row['code']) {{-- code berisi nama model --}}
                                                    <tr style="background: rgba(239,68,68,0.02);">
                                                        <td></td>
                                                        <td colspan="2">
                                                            <div style="display: flex; align-items: center; gap: 8px; padding-left: 20px; border-left: 2px solid {{ $snDetail['diff'] > 0 ? 'var(--warning)' : 'var(--danger)' }};">
                                                                <i class="fa-solid fa-arrow-turn-up fa-rotate-90" style="color: var(--text-muted);"></i>
                                                                <span style="font-family: monospace; font-size: 13px;">{{ $snDetail['code'] }}</span>
                                                                <span class="badge badge-{{ $snDetail['diff'] > 0 ? 'warning' : 'danger' }}" style="font-size: 10px;">
                                                                    {{ $snDetail['diff'] > 0 ? 'NYASAR (Scan Fisik)' : 'HILANG (Data Sistem)' }}
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td style="font-size: 12px;">
                                                            @if(($snDetail['diff'] ?? 0) > 0 && !empty($snDetail['rack_code']))
                                                                <span style="background: rgba(99,102,241,0.1); color: var(--accent-indigo); border-radius: 4px; padding: 2px 6px; font-family: monospace; font-size: 11px;">
                                                                    <i class="fa-solid fa-location-dot"></i>
                                                                    {{ $snDetail['rack_code'] }} / {{ $snDetail['row_code'] }}
                                                                </span>
                                                            @else
                                                                <span style="color: var(--text-muted); font-size: 11px;">-</span>
                                                            @endif
                                                        </td>
                                                        <td colspan="2">
                                                            @if(($snDetail['diff'] ?? 0) > 0 && !empty($snDetail['item_id']) && empty($session->crosscheck_result['applied']))
                                                                <div style="display: flex; gap: 4px;">
                                                                    <button type="button" class="btn btn-sm btn-outline" onclick="editSN('{{ $snDetail['item_id'] }}', '{{ $snDetail['code'] }}')" title="Edit SN">
                                                                        <i class="fa-solid fa-pen" style="color: var(--accent-blue);"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline" onclick="deleteSN('{{ $snDetail['item_id'] }}')" title="Hapus Scan">
                                                                        <i class="fa-solid fa-trash" style="color: var(--danger);"></i>
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @endif
                                    @endif
                                @endforeach
                            @endforeach
                            
                            @if(!$hasSelisih)
                                <tr><td colspan="7" style="text-align: center; color: var(--success); padding: 16px;">Luar biasa! Tidak ada selisih stok.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Sesuai -->
                <h3 style="margin-top: 0; color: var(--success);"><i class="fa-solid fa-check-circle"></i> Item Sesuai</h3>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipe</th>
                                <th>Kode / SN</th>
                                <th>Nama Barang</th>
                                <th style="text-align: center;">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hasSesuai = false; @endphp
                            @foreach(['device', 'accessory', 'simcard'] as $type)
                                @foreach($details[$type] as $row)
                                    @if($row['status'] === 'SESUAI')
                                        @php $hasSesuai = true; @endphp
                                        <tr>
                                            <td><span class="badge badge-secondary">{{ strtoupper($type) }}</span></td>
                                            <td>{{ $row['code'] }}</td>
                                            <td>{{ $row['name'] }}</td>
                                            <td style="text-align: center;">{{ $row['sys_qty'] }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                            
                            @if(!$hasSesuai)
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 16px;">Tidak ada item yang sesuai.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
@if($session->isOpen())
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const inpLoc = document.getElementById('scan_location');
        const inpItem = document.getElementById('scan_item');
        const inpQty = document.getElementById('scan_qty');
        const inpUnit = document.getElementById('scan_unit');
        const btnMinus = document.getElementById('btn_minus');
        const btnPlus = document.getElementById('btn_plus');
        const btnSave = document.getElementById('btn_save_scan');
        const locInfo = document.getElementById('loc_info');
        const itemInfo = document.getElementById('item_info');
        const scannedList = document.getElementById('scanned_list');
        const scanCount = document.getElementById('scan_count');
        const emptyScan = document.getElementById('empty_scan');
        
        const selJenis = document.getElementById('sel_jenis');
        const selTipe = document.getElementById('sel_tipe');
        
        const deviceModelsData = @json($deviceModels);

        let currentLoc = null; // { rack_code, row_code, barcode }
        let currentItem = null; // { item_type, item_code, item_name }
        let scannedSNs = []; // Array of string for multi SN scanning

        // Logic Dropdown
        window.onJenisChange = () => {
            const val = selJenis.value;
            selTipe.innerHTML = '<option value="">-- Pilih Model --</option>';
            
            if (val.startsWith('device_')) {
                const type = val.replace('device_', '');
                selTipe.disabled = false;
                if (deviceModelsData[type]) {
                    deviceModelsData[type].forEach(mod => {
                        const opt = document.createElement('option');
                        opt.value = mod.model;
                        opt.textContent = mod.model;
                        selTipe.appendChild(opt);
                    });
                }
            } else {
                selTipe.disabled = true;
            }
        };

        // Focus awal ke lokasi
        inpLoc.focus();

        // Resolver API
        async function resolveBarcode(barcode) {
            let reqType = null;
            let reqModel = null;
            if (selJenis.value) {
                if (selJenis.value === 'accessory' || selJenis.value === 'simcard') {
                    reqType = selJenis.value;
                } else if (selJenis.value.startsWith('device_')) {
                    reqType = 'device';
                    reqModel = selTipe.value;
                    if (!reqModel) {
                        alert('Silakan pilih Tipe Alat (Model) terlebih dahulu.');
                        return { success: false, bypass: true };
                    }
                }
            }

            try {
                const res = await fetch('{{ route('stock.opname.api.resolve') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ barcode, reqType, reqModel })
                });
                return await res.json();
            } catch (e) {
                return { success: false, message: 'Gagal menghubungi server.' };
            }
        }

        // Logic Scan Lokasi
        inpLoc.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = inpLoc.value.trim();
                if (!val) return;

                const data = await resolveBarcode(val);
                if (data.success && data.type === 'location') {
                    currentLoc = data.data;
                    locInfo.textContent = `Rak: ${currentLoc.rack_code} | Row: ${currentLoc.row_code}`;
                    locInfo.style.display = 'block';
                    
                    // Enable item input & focus
                    inpItem.disabled = false;
                    inpItem.focus();
                } else {
                    alert(data.message || 'Format barcode lokasi salah (Gunakan RAK-XX-ROW-XX).');
                    inpLoc.value = '';
                }
            }
        });

        // Logic Scan Item
        inpItem.addEventListener('keypress', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = inpItem.value.trim();
                if (!val) return;

                const data = await resolveBarcode(val);
                if (data.bypass) return; // aborted by local validation

                if (data.success && data.type === 'item') {
                    currentItem = data;
                    itemInfo.textContent = `[${data.item_type.toUpperCase()}] ${data.item_name}`;
                    itemInfo.style.display = 'block';

                    if (data.item_type === 'device') {
                        // Multi-scan mode
                        if (!scannedSNs.includes(data.item_code)) {
                            scannedSNs.push(data.item_code);
                            renderSNChips();
                        }
                        inpItem.value = ''; // clear for next scan
                        
                        // Disable minus/plus, just follow array length
                        inpQty.disabled = false;
                        inpUnit.disabled = true; // Unit is for physical qty usually
                        btnMinus.disabled = true;
                        btnPlus.disabled = true;
                        inpQty.value = scannedSNs.length;
                        btnSave.disabled = false;
                        
                        // Keep focus on scan_item so user can rapid-scan
                        inpItem.focus();
                    } else {
                        // Setup QTY controls normal
                        inpQty.disabled = false;
                        inpUnit.disabled = false;
                        btnMinus.disabled = false;
                        btnPlus.disabled = false;
                        inpQty.focus();
                        inpQty.select();
                        btnSave.disabled = false;
                    }
                } else {
                    alert(data.message || 'Barang tidak ditemukan di sistem.');
                    inpItem.value = '';
                }
            }
        });

        window.removeSN = (sn) => {
            scannedSNs = scannedSNs.filter(s => s !== sn);
            renderSNChips();
            inpQty.value = scannedSNs.length || 1;
            if (scannedSNs.length === 0) {
                btnSave.disabled = true;
                inpQty.value = 1;
            }
        };

        function renderSNChips() {
            const container = document.getElementById('multi_sn_container');
            container.innerHTML = '';
            scannedSNs.forEach(sn => {
                const el = document.createElement('span');
                el.className = 'badge badge-secondary';
                el.style.display = 'inline-flex';
                el.style.alignItems = 'center';
                el.style.gap = '6px';
                el.style.padding = '6px 10px';
                el.innerHTML = `${sn} <i class="fa-solid fa-times" style="cursor: pointer; color: #fca5a5;" onclick="removeSN('${sn}')"></i>`;
                container.appendChild(el);
            });
        }

        // Logic Qty Buttons
        btnMinus.addEventListener('click', () => {
            if (inpQty.value > 1) inpQty.value = parseInt(inpQty.value) - 1;
        });
        btnPlus.addEventListener('click', () => {
            inpQty.value = parseInt(inpQty.value) + 1;
        });

        // Simpan Scan
        btnSave.addEventListener('click', async () => {
            if (!currentLoc || !currentItem) return;

            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
            
            try {
                if (currentItem.item_type === 'device' && scannedSNs.length > 0) {
                    // Send each SN one by one
                    for (const sn of scannedSNs) {
                        const payload = {
                            location_barcode: currentLoc.barcode,
                            rack_code: currentLoc.rack_code,
                            row_code: currentLoc.row_code,
                            item_type: currentItem.item_type,
                            item_code: sn,
                            item_name: currentItem.item_name,
                            qty_physical: 1,
                            unit: ''
                        };
                        await sendScan(payload);
                    }
                } else {
                    // Normal single item (accessory, simcard)
                    const payload = {
                        location_barcode: currentLoc.barcode,
                        rack_code: currentLoc.rack_code,
                        row_code: currentLoc.row_code,
                        item_type: currentItem.item_type,
                        item_code: currentItem.item_code,
                        item_name: currentItem.item_name,
                        qty_physical: parseInt(inpQty.value) || 1,
                        unit: inpUnit.value.trim()
                    };
                    await sendScan(payload);
                }

                // Berhasil simpan semua, refresh UI
                window.location.reload(); 
            } catch (e) {
                alert('Gagal menghubungi server.');
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fa-solid fa-check"></i> Simpan Item';
            }
        });

        async function sendScan(payload) {
            const res = await fetch('{{ route('stock.opname.scan', $session->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success && !data.message.includes("sudah discan")) {
                alert(`Gagal menyimpan ${payload.item_code}: ${data.message}`);
            }
        }

        // Delete Item via event delegation (for raw scan list)
        if (scannedList) {
            scannedList.addEventListener('click', async (e) => {
                const btn = e.target.closest('.btn-delete-scan') || e.target.closest('.btn-delete-item');
                if (!btn) return;

                if (!confirm('Hapus item ini dari sesi opname?')) return;

                const itemId = btn.getAttribute('data-id');
                try {
                    const res = await fetch(`/stock-opname/session/{{ $session->id }}/scan/${itemId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        document.getElementById(`item_${itemId}`).remove();
                        const cnt = parseInt(scanCount.textContent) - 1;
                        scanCount.textContent = cnt;
                        if (cnt === 0 && emptyScan) emptyScan.style.display = 'block';
                    } else {
                        alert(data.message || 'Gagal menghapus item.');
                    }
                } catch (e) {
                    alert('Error server.');
                }
            });
        }
    });

    // Edit SN in Crosscheck Result
    window.editSN = async (itemId, currentSN) => {
        const newSN = prompt('Ubah SN untuk item ini:', currentSN);
        if (!newSN || newSN === currentSN) return;

        try {
            const res = await fetch(`{{ url('stock-opname/session') }}/{{ $session->id }}/scan/${itemId}`, {
                method: 'PUT',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ item_code: newSN })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Gagal mengubah SN.');
            }
        } catch (e) {
            alert('Gagal menghubungi server.');
        }
    };

    // Delete SN in Crosscheck Result
    window.deleteSN = async (itemId) => {
        if (!confirm('Yakin ingin menghapus SN ini dari hasil scan fisik Anda?')) return;

        try {
            const res = await fetch(`{{ url('stock-opname/session') }}/{{ $session->id }}/scan/${itemId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Gagal menghapus scan.');
            }
        } catch (e) {
            alert('Gagal menghubungi server.');
        }
    };
</script>
@endif
@endsection
