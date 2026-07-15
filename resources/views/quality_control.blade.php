@extends('layouts.app')

@php
    $activeTab = in_array(request('tab'), ['incoming','return','report']) ? request('tab') : 'incoming';
    $maxThroughput = max(1, max($qcReport['throughput'] ?: [0]));
@endphp

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-clipboard-check"
        iconColor="var(--accent-emerald)"
        title="Quality Control (QC)"
        subtitle="Satu pusat QC: verifikasi barang masuk, inspeksi perangkat return, dan laporan QC." />

    @if(session('success'))
        <div class="alert-box alert-success animate-fade-in" style="margin-bottom: 20px;">
            <div class="alert-icon"><i class="fa-solid fa-check-circle"></i></div>
            <div class="alert-message">{{ session('success') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="alert-box alert-danger animate-fade-in" style="margin-bottom: 20px;">
            <div class="alert-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="alert-message">{{ $errors->first() }}</div>
        </div>
    @endif

    <!-- TAB NAV -->
    <div class="qc-tabs" style="display:flex; gap:8px; border-bottom:1px solid var(--border-color); margin-bottom:20px; flex-wrap:wrap;">
        <button type="button" class="qc-tab-btn" data-tab="incoming">
            <i class="fa-solid fa-truck-ramp-box"></i> Barang Masuk
            <span class="badge badge-warning">{{ count($incoming) }}</span>
        </button>
        <button type="button" class="qc-tab-btn" data-tab="return">
            <i class="fa-solid fa-rotate-left"></i> Return / Inspeksi
            <span class="badge badge-info">{{ count($returns) }}</span>
        </button>
        <button type="button" class="qc-tab-btn" data-tab="report">
            <i class="fa-solid fa-chart-column"></i> Laporan QC
        </button>
    </div>

    {{-- ============================ TAB 1: BARANG MASUK ============================ --}}
    <div class="qc-panel" data-panel="incoming">
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div class="card-title">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <span>Menunggu QC Penerimaan</span>
                    <span class="badge badge-warning" style="margin-left:6px;">{{ count($incoming) }} unit</span>
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <span class="badge badge-info" title="Gudang aktif (scope QC)">
                        <i class="fa-solid fa-warehouse"></i> {{ $warehouseName ?: $warehouseCode ?: 'Semua Gudang' }}
                    </span>
                    <select id="modelFilter" class="form-control" style="width:auto; min-width:170px; padding:6px 12px; font-size:13px;">
                        <option value="">Semua Model</option>
                        @foreach($models as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                    <div style="position: relative; display: flex; align-items: center;">
                        <i class="fa-solid fa-barcode" style="position: absolute; left: 10px; color: var(--text-muted);"></i>
                        <input type="text" id="barcodeScanInput" class="form-control" placeholder="Scan Barcode QC..." style="width:auto; min-width:180px; padding:6px 12px 6px 30px; font-size:13px; border-color: var(--accent-indigo);" title="Scan barcode untuk langsung checklist" autofocus>
                    </div>
                    <input type="text" id="snSearch" class="form-control" placeholder="Cari Manual..." style="width:auto; min-width:130px; padding:6px 12px; font-size:13px;">
                </div>
            </div>

            <div id="bulkBar" style="display:none; padding:10px 16px; background:rgba(0,0,0,0.03); border-bottom:1px solid var(--border-color); align-items:center; gap:12px;">
                <span><strong id="bulkCount">0</strong> unit dipilih</span>
                <button type="button" class="btn btn-primary" style="padding:6px 14px; font-size:13px;" onclick="openInQc('bulk')">
                    <i class="fa-solid fa-clipboard-check"></i> Proses QC Terpilih
                </button>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:38px; text-align:center;"><input type="checkbox" id="checkAll" title="Pilih semua"></th>
                            <th>Serial Number</th>
                            <th>Model</th>
                            <th>Tipe</th>
                            <th style="text-align:center;">Kondisi</th>
                            <th>Tanggal Masuk</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="qcTbody">
                        @forelse($incoming as $dev)
                            <tr class="qc-row" data-id="{{ $dev->id }}" data-sn="{{ strtoupper($dev->serial_number) }}" data-model="{{ $dev->model ?: $dev->type }}">
                                <td style="text-align:center;"><input type="checkbox" class="qc-check" value="{{ $dev->id }}"></td>
                                <td style="font-weight:600; color:var(--accent-blue);">{{ $dev->serial_number }}</td>
                                <td><span class="badge badge-info">{{ $dev->model ?: '-' }}</span></td>
                                <td>{{ $dev->type }}</td>
                                <td style="text-align:center;"><span class="badge {{ $dev->unit_condition === 'BEKAS' ? 'badge-warning' : 'badge-success' }}">{{ $dev->unit_condition ?: 'BARU' }}</span></td>
                                <td style="color:var(--text-muted); font-size:13px;">{{ optional($dev->created_at)->format('d M Y H:i') ?: '-' }}</td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="openInQc('single', {{ $dev->id }}, '{{ $dev->serial_number }}')">
                                        <i class="fa-solid fa-clipboard-check"></i> Proses QC
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="7" icon="fa-clipboard-check"
                                title="Tidak ada unit menunggu QC"
                                message="Unit baru akan muncul di sini setelah Receiving. QC hanya di gudang penerimaan pertama." />
                        @endforelse
                    </tbody>
                    </tbody>
                </table>
            </div>
            <div id="paginationControls" style="display:flex; justify-content:center; align-items:center; gap:8px; margin: 15px 0;"></div>
        </div>
    </div>

    {{-- ============================ TAB 2: RETURN / INSPEKSI ============================ --}}
    <div class="qc-panel" data-panel="return" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <span>Perangkat Return Menunggu Inspeksi (RETURNED / UNDER_QC)</span>
                    <span class="badge badge-info" style="margin-left:6px;">{{ count($returns) }} unit</span>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Serial Number</th>
                            <th>Tipe Perangkat</th>
                            <th>Gudang Saat Ini</th>
                            <th>Status</th>
                            <th style="text-align:center;">Aksi QC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $dev)
                            <tr>
                                <td style="font-weight:600; color:var(--accent-blue);">{{ $dev->serial_number }}</td>
                                <td><span class="badge badge-info">{{ $dev->type }}</span></td>
                                <td>{{ $dev->warehouse_code }}</td>
                                <td>
                                    @if($dev->status === 'RETURNED')
                                        <span class="badge badge-warning">RETURNED</span>
                                    @else
                                        <span class="badge badge-amber">UNDER_QC</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="openRetQc({{ $dev->id }}, '{{ $dev->serial_number }}')">
                                        <i class="fa-solid fa-clipboard-check"></i> Proses QC
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="5" icon="fa-clipboard-check"
                                title="Tidak ada perangkat menunggu inspeksi"
                                message="Perangkat akan muncul di sini setelah di-return dari teknisi/customer." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============================ TAB 3: LAPORAN QC ============================ --}}
    <div class="qc-panel" data-panel="report" style="display:none;">
        <div class="stats-grid" style="margin-bottom:20px;">
            <x-stat-card color="rose" icon="fa-clipboard-list" title="Antrian QC Penerimaan" :value="$qcReport['queue_incoming']" hint="Belum diproses" />
            <x-stat-card color="amber" icon="fa-rotate-left" title="Antrian QC Return" :value="$qcReport['queue_return']" hint="Belum diinspeksi" />
            <x-stat-card color="emerald" icon="fa-circle-check" title="Lolos QC ({{ $qcReport['days'] }} hari)" :value="$qcReport['passed']" hint="QC_PASSED" />
            <x-stat-card color="blue" icon="fa-percent" title="Reject Rate ({{ $qcReport['days'] }} hari)" :value="$qcReport['reject_rate'].'%'" hint="{{ $qcReport['failed'] }} dari {{ $qcReport['total'] }} unit" />
            <x-stat-card color="indigo" icon="fa-stopwatch" title="Rata-rata Terima → QC OK"
                :value="$qcReport['avg_lead_hours'] !== null ? $qcReport['avg_lead_hours'].' jam' : '-'" hint="Lead time barang masuk" />
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-chart-column"></i> Throughput QC Harian ({{ $qcReport['days'] }} hari terakhir)</div></div>
            <div style="padding:16px; overflow-x:auto;">
                <div style="display:flex; align-items:flex-end; gap:4px; height:140px; min-width:600px;">
                    @foreach($qcReport['throughput'] as $day => $count)
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end;" title="{{ $day }}: {{ $count }} unit">
                            <span style="font-size:10px; color:var(--text-muted);">{{ $count > 0 ? $count : '' }}</span>
                            <div style="width:100%; max-width:22px; background:var(--accent-blue); border-radius:4px 4px 0 0; height:{{ $count > 0 ? round($count / $maxThroughput * 110) + 4 : 2 }}px; opacity:{{ $count > 0 ? 1 : 0.25 }};"></div>
                            <span style="font-size:9px; color:var(--text-muted); white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($day)->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> Reject per Model ({{ $qcReport['days'] }} hari)</div></div>
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Model</th><th style="text-align:center;">Jumlah Reject</th></tr></thead>
                    <tbody>
                        @forelse($qcReport['reject_by_model'] as $row)
                            <tr>
                                <td style="font-weight:600;">{{ $row->model ?: '-' }}</td>
                                <td style="text-align:center;"><span class="badge badge-danger">{{ $row->total }}</span></td>
                            </tr>
                        @empty
                            <x-empty-state colspan="2" icon="fa-circle-check"
                                title="Tidak ada reject"
                                message="Belum ada perangkat yang gagal QC dalam periode ini. " />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ===== MODAL: QC PENERIMAAN ===== --}}
<div id="inQcModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div class="card animate-fade-in" style="width:100%; max-width:520px; padding:0;">
        <div class="card-header" style="border-bottom:1px solid var(--border-color); padding:16px 20px;">
            <h3 style="margin:0; font-size:16px;"><i class="fa-solid fa-clipboard-check"></i> Hasil QC Penerimaan — <span id="inQcTarget" style="color:var(--accent-blue);"></span></h3>
        </div>
        <form action="{{ route('qc.incoming.post') }}" method="POST" style="padding:20px;" id="inQcForm">
            @csrf
            <div id="inQcIds"></div>
            <div class="form-group">
                <label class="form-label">Keputusan QC</label>
                <div style="display:flex; gap:12px;">
                    <label style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; border:1px solid var(--border-color); border-radius:8px; padding:10px;">
                        <input type="radio" name="decision" value="OK" checked onchange="toggleInDecision()">
                        <span style="color:var(--accent-emerald); font-weight:600;"><i class="fa-solid fa-circle-check"></i> QC OK</span>
                    </label>
                    <label style="flex:1; display:flex; align-items:center; gap:8px; cursor:pointer; border:1px solid var(--border-color); border-radius:8px; padding:10px;">
                        <input type="radio" name="decision" value="REJECT" onchange="toggleInDecision()">
                        <span style="color:var(--danger-color); font-weight:600;"><i class="fa-solid fa-circle-xmark"></i> QC Reject</span>
                    </label>
                </div>
            </div>
            <div class="form-group" id="inOkFields">
                <label class="form-label">Kondisi Unit</label>
                <select name="condition" class="form-control">
                    <option value="BARU">BARU (mulus / unit baru)</option>
                    <option value="BEKAS">BEKAS (layak pakai, bekas)</option>
                </select>
                <small style="color:var(--text-muted);">QC OK → status <strong>IN_STOCK</strong> (siap dimutasi).</small>
            </div>
            <div class="form-group" id="inRejectFields" style="display:none;">
                <label class="form-label">Tindak Lanjut Reject</label>
                <select name="disposition" class="form-control">
                    <option value="RETEST">Re-test (uji ulang → tetap antrian QC)</option>
                    <option value="RETUR_VENDOR">Retur Vendor (karantina / dikirim balik)</option>
                    <option value="DISPOSED">Disposed (dimusnahkan)</option>
                </select>
                <small style="color:var(--text-muted);">Re-test → <strong>PENDING_QC</strong> · Retur Vendor → <strong>FLAGGED</strong> · Disposed → <strong>DISPOSED</strong>.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan QC</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Catatan hasil pengujian / temuan..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('inQcModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan Hasil QC</button>
            </div>
        </form>
    </div>
</div>

{{-- ===== MODAL: QC RETURN / INSPEKSI ===== --}}
<div id="retQcModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div class="card animate-fade-in" style="width:100%; max-width:500px; padding:0;">
        <div class="card-header" style="border-bottom:1px solid var(--border-color); padding:16px 20px;">
            <h3 style="margin:0; font-size:16px;">Form Inspeksi QC - <span id="retQcTarget" style="color:var(--accent-blue);"></span></h3>
        </div>
        <form action="{{ route('inspection.submit') }}" method="POST" style="padding:20px;">
            @csrf
            <input type="hidden" name="device_id" id="ret_device_id">
            <div class="form-group">
                <label class="form-label">Kondisi Fisik</label>
                <select name="condition" class="form-control" required>
                    <option value="">-- Pilih Kondisi --</option>
                    <option value="GOOD">Baik / Mulus</option>
                    <option value="SCRATCHED">Gores / Cacat Ringan</option>
                    <option value="DAMAGED">Rusak Fisik Berat</option>
                    <option value="UNKNOWN">Tidak Bisa Dinyalakan (Mati Total)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan Inspeksi</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Detail kerusakan / hasil test..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Keputusan Akhir (QC Result)</label>
                <div style="display:flex; gap:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="qc_result" value="PASSED" required>
                        <span style="color:var(--accent-emerald); font-weight:600;">PASSED (Kembali ke Stok)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="radio" name="qc_result" value="FAILED" required>
                        <span style="color:var(--danger-color); font-weight:600;">FAILED (Flagged / Rusak)</span>
                    </label>
                </div>
                <small style="color:var(--text-muted); display:block; margin-top:8px;">PASSED → IN_STOCK (BEKAS). FAILED → FLAGGED.</small>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('retQcModal').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Hasil QC</button>
            </div>
        </form>
    </div>
</div>

<style>
    .qc-tab-btn { background:none; border:none; padding:10px 16px; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:2px solid transparent; display:flex; align-items:center; gap:8px; }
    .qc-tab-btn.active { color:var(--accent-blue); border-bottom-color:var(--accent-blue); }
    .qc-tab-btn .badge { font-size:10px; }
</style>

<script>
(function () {
    // --- Tabs ---
    const initial = @json($activeTab);
    function showTab(tab) {
        document.querySelectorAll('.qc-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        document.querySelectorAll('.qc-panel').forEach(p => p.style.display = (p.dataset.panel === tab ? '' : 'none'));
        const url = new URL(window.location); url.searchParams.set('tab', tab); history.replaceState({}, '', url);
    }
    document.querySelectorAll('.qc-tab-btn').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));
    showTab(initial);

    // --- Paginasi & Filter ---
    const rowsPerPage = 50;
    let currentPage = 1;
    let filteredRows = [];

    const checks = () => Array.from(document.querySelectorAll('.qc-check'));
    const checkedIds = () => checks().filter(c => c.checked && c.closest('tr').style.display !== 'none').map(c => c.value);
    
    function applyFilter() {
        const model = (document.getElementById('modelFilter')?.value || '').toLowerCase();
        const sn = (document.getElementById('snSearch')?.value || '').trim().toUpperCase();
        
        filteredRows = [];
        document.querySelectorAll('.qc-row').forEach(r => {
            const okModel = !model || (r.dataset.model || '').toLowerCase() === model;
            const okSn = !sn || (r.dataset.sn || '').includes(sn);
            
            if (okModel && okSn) {
                filteredRows.push(r);
            } else {
                r.style.display = 'none';
            }
        });
        
        currentPage = 1;
        renderPagination();
    }

    function renderPagination() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        
        filteredRows.forEach((r, index) => {
            if (index >= (currentPage - 1) * rowsPerPage && index < currentPage * rowsPerPage) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
        
        const controls = document.getElementById('paginationControls');
        if (controls) {
            if (totalPages <= 1) {
                controls.innerHTML = '';
            } else {
                controls.innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>
                    <span style="font-size:13px; color:var(--text-muted); padding:0 10px;">Hal ${currentPage} / ${totalPages} (${filteredRows.length} item)</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
                `;
            }
        }
        updateBulkBar();
    }

    window.changePage = function(page) {
        currentPage = page;
        renderPagination();
    };

    document.getElementById('modelFilter')?.addEventListener('change', applyFilter);
    document.getElementById('snSearch')?.addEventListener('input', applyFilter);
    
    // Inisialisasi awal
    if (document.querySelector('.qc-row')) applyFilter();

    document.getElementById('checkAll')?.addEventListener('change', function () {
        checks().forEach(c => { if (c.closest('tr').style.display !== 'none') c.checked = this.checked; });
        updateBulkBar();
    });
    checks().forEach(c => c.addEventListener('change', updateBulkBar));
    function updateBulkBar() {
        const n = checkedIds().length, bar = document.getElementById('bulkBar');
        if (!bar) return;
        document.getElementById('bulkCount').textContent = n;
        bar.style.display = n > 0 ? 'flex' : 'none';
    }

    // --- Incoming modal ---
    window.openInQc = function (mode, id, sn) {
        const c = document.getElementById('inQcIds'); c.innerHTML = '';
        let ids = [];
        if (mode === 'single') { ids = [id]; document.getElementById('inQcTarget').textContent = sn; }
        else {
            ids = checkedIds();
            if (!ids.length) { alert('Pilih minimal 1 unit.'); return; }
            document.getElementById('inQcTarget').textContent = ids.length + ' unit terpilih';
        }
        ids.forEach(v => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'device_ids[]'; i.value = v; c.appendChild(i); });
        document.querySelector('#inQcForm input[name="decision"][value="OK"]').checked = true;
        toggleInDecision();
        document.querySelector('#inQcForm textarea[name="notes"]').value = '';
        document.getElementById('inQcModal').style.display = 'flex';
    };
    window.toggleInDecision = function () {
        const dec = document.querySelector('#inQcForm input[name="decision"]:checked').value;
        document.getElementById('inOkFields').style.display = dec === 'OK' ? '' : 'none';
        document.getElementById('inRejectFields').style.display = dec === 'REJECT' ? '' : 'none';
    };

    // --- Return modal ---
    window.openRetQc = function (id, sn) {
        document.getElementById('ret_device_id').value = id;
        document.getElementById('retQcTarget').textContent = sn;
        document.getElementById('retQcModal').style.display = 'flex';
    };

    // --- Barcode Scanner Logic ---
    const scanInput = document.getElementById('barcodeScanInput');
    if (scanInput) {
        let scanTimeout;
        const processScan = function() {
            let sn = scanInput.value.trim().toUpperCase();
            if (!sn) return;

            let row = document.querySelector(`.qc-row[data-sn="${sn}"]`);
            if (row) {
                let cb = row.querySelector('.qc-check');
                if (cb && !cb.checked) {
                    cb.checked = true;
                    // Pindahkan row ke paling atas
                    let tbody = document.getElementById('qcTbody');
                    if (tbody) tbody.prepend(row);
                    
                    // Re-apply filter untuk update paginasi dan kembali ke halaman 1
                    applyFilter(); 
                }
                
                // Highlight
                let originalBg = row.style.backgroundColor;
                row.style.backgroundColor = '#dcfce7'; 
                row.style.transition = 'background-color 0.5s';
                setTimeout(() => { row.style.backgroundColor = originalBg; }, 1000);
            } else {
                scanInput.style.backgroundColor = '#fee2e2';
                setTimeout(() => { scanInput.style.backgroundColor = ''; }, 400);
            }
            
            scanInput.value = '';
            scanInput.focus();
        };

        scanInput.addEventListener('input', function(e) {
            clearTimeout(scanTimeout);
            scanTimeout = setTimeout(processScan, 300);
        });
        
        scanInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(scanTimeout);
                processScan();
            }
        });
    }

})();
</script>
@endsection
