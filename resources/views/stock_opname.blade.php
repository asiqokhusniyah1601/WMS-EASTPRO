@extends('layouts.app')

@section('title', 'Stock Opname Warehouse | DLMS')

@section('styles')
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-boxes-packing"
        iconColor="var(--accent-indigo)"
        title="Stock Opname Warehouse"
        subtitle="Sistem opname berbasis barcode untuk mencatat dan memverifikasi stok fisik (Device, Aksesoris, SIM Card) di gudang secara realtime." />

    <!-- Warehouse Selector & Start Session -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="justify-content: space-between;">
            <form action="{{ route('stock.opname') }}" method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; min-width: 250px;">
                    <label for="warehouse_selector">Gudang Aktif</label>
                    <select name="warehouse" id="warehouse_selector" class="form-control" disabled>
                        @foreach($warehouses as $code => $name)
                            <option value="{{ $code }}" {{ $selectedWh === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-outline">Pilih</button></noscript>
            </form>

            @if(auth()->user()->hasRole('super_admin', 'admin', 'pic'))
            <form id="formStartSession" action="{{ route('stock.opname.session.start') }}" method="POST" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                @csrf
                <input type="hidden" name="warehouse_code" value="{{ $selectedWh }}">
                <div class="form-group" style="margin: 0;">
                    <label for="opname_date">Tanggal Opname</label>
                    <input type="date" name="opname_date" id="opname_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Mulai sesi opname baru untuk {{ $warehouses[$selectedWh] ?? $selectedWh }} pada tanggal ' + document.getElementById('opname_date').value + '?')">
                    <i class="fa-solid fa-play"></i> Mulai Sesi Opname
                </button>
            </form>
            @endif
        </div>
    </div>

    <!-- Tabs -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <button class="btn btn-outline active-tab-btn" onclick="switchTab('sessions')" id="tabSessionsBtn" style="border-bottom: 2px solid var(--accent-blue); border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-primary); font-weight: 600;">
            <i class="fa-solid fa-list-check" style="color: var(--accent-blue);"></i> Daftar Sesi Opname
        </button>
        <button class="btn btn-outline" onclick="switchTab('teknisi')" id="tabTeknisiBtn" style="border-bottom: 2px solid transparent; border-radius: 0; padding-bottom: 12px; background: none; border-top: none; border-left: none; border-right: none; color: var(--text-secondary);">
            <i class="fa-solid fa-users-gear" style="color: var(--accent-indigo);"></i> Opname Teknisi
        </button>
    </div>

    <!-- Sesi Barcode List -->
    <div id="tabSessions">
        <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-list-check"></i> Daftar Sesi Opname
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Sesi</th>
                        <th>Status</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Operator</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $sess)
                    <tr>
                        <td><strong>#{{ $sess->id }}</strong></td>
                        <td>
                            @if($sess->status === 'open')
                                <span class="badge badge-warning">Berjalan</span>
                            @else
                                <span class="badge badge-success">Selesai</span>
                            @endif
                        </td>
                        <td>{{ $sess->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $sess->completed_at ? $sess->completed_at->format('d M Y H:i') : '-' }}</td>
                        <td>{{ $sess->startedBy->name ?? 'System' }}</td>
                        <td style="text-align: right;">
                            <a href="{{ route('stock.opname.session.show', $sess->id) }}" class="btn btn-sm btn-outline">
                                @if($sess->status === 'open')
                                    <i class="fa-solid fa-barcode"></i> Lanjutkan Scan
                                @else
                                    <i class="fa-solid fa-eye"></i> Lihat Hasil
                                @endif
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);">
                            Belum ada sesi opname untuk gudang ini.<br>Klik tombol "Mulai Sesi Opname" untuk memulai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sessions->hasPages())
        <div style="padding: 12px 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            {{ $sessions->links('pagination::bootstrap-4') }}
        </div>
        @endif
        </div>
    </div>



    <!-- Opname Teknisi Tab -->
    <div id="tabTeknisi" style="display: none;">
        <div class="card">
            <div class="card-header" style="justify-content: space-between;">
                <div class="card-title">
                    <i class="fa-solid fa-users-gear"></i> Opname Alat Teknisi (Gudang: {{ $warehouses[$selectedWh] ?? $selectedWh }})
                </div>
                <div>
                    <button class="btn btn-primary" onclick="loadTeknisiData()">
                        <i class="fa-solid fa-sync"></i> Refresh Data
                    </button>
                </div>
            </div>
            
            <div id="teknisiLoading" style="padding: 32px; text-align: center; color: var(--text-muted); display: none;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Memuat data teknisi...
            </div>

            <div id="teknisiContent" style="display: none;">
                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="table" id="teknisiTable" style="min-width: 600px;">
                        <thead id="teknisiThead">
                            <tr>
                                <th>Keterangan</th>
                                <!-- Kolom teknisi diisi JS -->
                            </tr>
                        </thead>
                        <tbody id="teknisiTbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <div style="padding: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
                    <button class="btn btn-success" onclick="crosscheckTeknisi()">
                        <i class="fa-solid fa-check-double"></i> Crosscheck Hasil
                    </button>
                </div>
            </div>
        </div>

        <!-- Result Card -->
        <div class="card" id="teknisiResultCard" style="display: none; margin-top: 24px;">
            <div class="card-header" style="justify-content: space-between;">
                <div class="card-title">
                    <i class="fa-solid fa-chart-pie"></i> Hasil Crosscheck Opname Teknisi
                </div>
                <form action="{{ route('stock.opname.teknisi.export') }}" method="POST" id="formExportTeknisi">
                    @csrf
                    <input type="hidden" name="warehouse_code" value="{{ $selectedWh }}">
                    <input type="hidden" name="results_json" id="exportResultsJson" value="">
                    <button type="submit" class="btn btn-outline">
                        <i class="fa-solid fa-file-excel" style="color: var(--success);"></i> Export ke Excel
                    </button>
                </form>
            </div>
            
            <div class="card-body">
                <div style="display: flex; gap: 16px; margin-bottom: 24px;" id="teknisiSummary">
                    <!-- Populated by JS -->
                </div>

                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="table" id="teknisiResultTable" style="min-width: 600px;">
                        <thead id="teknisiResultThead">
                            <tr>
                                <th>Keterangan</th>
                                <!-- Kolom teknisi diisi JS -->
                                <th style="text-align: center;">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="teknisiResultTbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function switchTab(tab) {
        document.getElementById('tabSessions').style.display = tab === 'sessions' ? 'block' : 'none';
        document.getElementById('tabTeknisi').style.display = tab === 'teknisi' ? 'block' : 'none';
        
        const formStart = document.getElementById('formStartSession');
        
        const btnSess = document.getElementById('tabSessionsBtn');
        const btnTek = document.getElementById('tabTeknisiBtn');
        
        if (tab === 'sessions') {
            if (formStart) formStart.style.display = 'flex';
            btnSess.style.borderBottomColor = 'var(--accent-blue)';
            btnSess.style.color = 'var(--text-primary)';
            btnSess.style.fontWeight = '600';
            btnTek.style.borderBottomColor = 'transparent';
            btnTek.style.color = 'var(--text-secondary)';
            btnTek.style.fontWeight = 'normal';
        } else if (tab === 'teknisi') {
            if (formStart) formStart.style.display = 'none';
            btnTek.style.borderBottomColor = 'var(--accent-blue)';
            btnTek.style.color = 'var(--text-primary)';
            btnTek.style.fontWeight = '600';
            btnSess.style.borderBottomColor = 'transparent';
            btnSess.style.color = 'var(--text-secondary)';
            btnSess.style.fontWeight = 'normal';
            
            // Auto load data if empty
            if (document.getElementById('teknisiTbody').innerHTML.trim() === '') {
                loadTeknisiData();
            }
        }
    }



    // === Logic Opname Teknisi (Matrix) ===
    let _teknisiMatrix = null; // simpan data matrix untuk crosscheck

    async function loadTeknisiData() {
        const loading = document.getElementById('teknisiLoading');
        const content = document.getElementById('teknisiContent');
        const thead = document.getElementById('teknisiThead');
        const tbody = document.getElementById('teknisiTbody');
        const resultCard = document.getElementById('teknisiResultCard');
        const warehouseCode = '{{ $selectedWh }}';
        
        loading.style.display = 'block';
        content.style.display = 'none';
        resultCard.style.display = 'none';
        
        try {
            const res = await fetch(`{{ route('stock.opname.teknisi.data') }}?warehouse=${warehouseCode}`);
            const json = await res.json();
            
            if (json.success) {
                _teknisiMatrix = json;
                const techs = json.technicians;
                const rows = json.rows;

                // Render header
                let headerHtml = '<tr><th style="white-space:nowrap;">Keterangan</th>';
                techs.forEach(t => {
                    headerHtml += `<th style="text-align:center;white-space:nowrap;">${t.name}</th>`;
                });
                headerHtml += '<th style="text-align:center;">TOTAL</th></tr>';
                thead.innerHTML = headerHtml;

                // Render body
                tbody.innerHTML = '';
                if (rows.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${techs.length + 2}" style="text-align:center;color:var(--text-muted);padding:24px;">Tidak ada data alat teknisi untuk gudang ini.</td></tr>`;
                } else {
                    rows.forEach(row => {
                        const tr = document.createElement('tr');
                        let total = 0;
                        let cells = `<td style="white-space:nowrap;"><strong>${row.item}</strong></td>`;
                        techs.forEach(t => {
                            const sysQty = row.techs[t.code] || 0;
                            total += sysQty;
                            cells += `<td style="text-align:center;">
                                <input type="number" class="form-control tek-phys-input"
                                    data-item="${encodeURIComponent(row.item)}"
                                    data-code="${t.code}"
                                    value="${sysQty}" min="0"
                                    style="text-align:center;width:70px;padding:4px;">
                            </td>`;
                        });
                        cells += `<td style="text-align:center;font-weight:bold;">${total}</td>`;
                        tr.innerHTML = cells;
                        tbody.appendChild(tr);
                    });
                }

                loading.style.display = 'none';
                content.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
            alert('Gagal memuat data teknisi.');
            loading.style.display = 'none';
        }
    }

    async function crosscheckTeknisi() {
        const inputs = document.querySelectorAll('.tek-phys-input');
        const counts = {};
        let hasData = false;
        
        inputs.forEach(inp => {
            const item = decodeURIComponent(inp.getAttribute('data-item'));
            const code = inp.getAttribute('data-code');
            if (!counts[item]) counts[item] = {};
            counts[item][code] = parseInt(inp.value) || 0;
            hasData = true;
        });
        
        if (!hasData) {
            alert('Tidak ada data teknisi untuk diproses.');
            return;
        }

        const btn = event.currentTarget;
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

        try {
            const res = await fetch(`{{ route('stock.opname.teknisi.crosscheck') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    warehouse_code: '{{ $selectedWh }}',
                    counts: counts
                })
            });
            const json = await res.json();

            if (json.success) {
                renderTeknisiResult(json.technicians, json.results, json.summary);
            } else {
                alert(json.message || 'Gagal melakukan crosscheck.');
            }
        } catch (e) {
            console.error(e);
            alert('Error server saat crosscheck.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    function renderTeknisiResult(techs, results, summary) {
        const resultCard = document.getElementById('teknisiResultCard');
        const thead = document.getElementById('teknisiResultThead');
        const tbody = document.getElementById('teknisiResultTbody');
        const summaryDiv = document.getElementById('teknisiSummary');
        const inpExport = document.getElementById('exportResultsJson');

        // Setup export - kirim data lengkap termasuk teknisi
        inpExport.value = JSON.stringify({ technicians: techs, results: results });

        // Render summary
        summaryDiv.innerHTML = `
            <div style="flex:1;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);padding:16px;border-radius:8px;text-align:center;">
                <div style="font-size:24px;font-weight:bold;color:var(--success);">${summary.sesuai}</div>
                <div style="color:var(--text-secondary);font-size:14px;">Item Sesuai</div>
            </div>
            <div style="flex:1;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);padding:16px;border-radius:8px;text-align:center;">
                <div style="font-size:24px;font-weight:bold;color:var(--danger);">${summary.selisih}</div>
                <div style="color:var(--text-secondary);font-size:14px;">Item Selisih</div>
            </div>
        `;

        // Render header
        let headerHtml = '<tr><th style="white-space:nowrap;">Keterangan</th>';
        techs.forEach(t => {
            headerHtml += `<th style="text-align:center;white-space:nowrap;">${t.name}</th>`;
        });
        headerHtml += '<th style="text-align:center;">TOTAL</th></tr>';
        thead.innerHTML = headerHtml;

        // Render rows
        tbody.innerHTML = '';
        results.forEach(r => {
            const isSelisih = r.status === 'SELISIH';
            const tr = document.createElement('tr');
            if (isSelisih) tr.style.background = 'rgba(239,68,68,0.05)';

            let total = 0;
            let cells = `<td style="white-space:nowrap;">`;
            cells += isSelisih
                ? `<strong style="color:var(--danger);">${r.item}</strong>`
                : `<strong>${r.item}</strong>`;
            cells += `</td>`;

            techs.forEach(t => {
                const cell = r.techs[t.code] || { sys_qty: 0, phys_qty: 0, diff: 0 };
                const qty = cell.phys_qty;
                total += qty;
                const diffColor = cell.diff === 0 ? '' : (cell.diff > 0 ? 'color:var(--warning);' : 'color:var(--danger);');
                const diffLabel = cell.diff !== 0 ? ` <small>(${cell.diff > 0 ? '+' : ''}${cell.diff})</small>` : '';
                cells += `<td style="text-align:center;${diffColor}font-weight:${cell.diff !== 0 ? 'bold' : 'normal'};">${qty}${diffLabel}</td>`;
            });

            cells += `<td style="text-align:center;font-weight:bold;">${total}</td>`;
            tr.innerHTML = cells;
            tbody.appendChild(tr);
        });

        resultCard.style.display = 'block';
        resultCard.scrollIntoView({ behavior: 'smooth' });
    }
</script>
@endsection
