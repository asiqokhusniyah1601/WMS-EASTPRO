@extends('layouts.app')

<!--@yield('title', 'Global Search & Audit Trail | DLMS')-->

@section('content')
    <div class="animate-fade-in">
        <x-page-header
            icon="fa-magnifying-glass"
            title="Pencarian Global & Audit Trail"
            subtitle="Lacak siklus hidup (lifecycle) perangkat, posisi terakhir, serta riwayat lengkap perubahan statusnya." />

        <!-- Search Form Bar -->
        <div class="card" style="margin-bottom: 24px;">
            <form action="{{ route('search') }}" method="GET" style="display: flex; gap: 12px; align-items: center;">
                <div style="position: relative; flex-grow: 1;">
                    <i class="fa-solid fa-magnifying-glass"
                        style="position: absolute; left: 16px; top: 14px; color: var(--text-muted);"></i>
                    <input type="text" name="q" value="{{ $q }}"
                        placeholder="Masukkan Serial Number (SN) atau IMEI perangkat..." class="form-control"
                        style="padding-left: 48px; font-size: 15px; height: 46px;">
                </div>
                <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 24px;">Cari Perangkat</button>
            </form>
        </div>

        @if(isset($warning) && $warning)
            <div class="alert-box alert-danger animate-fade-in" style="margin-bottom: 24px;">
                <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                <div class="alert-message">{{ $warning }}</div>
            </div>
        @elseif(!empty($q))
            <!-- Results Section -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 24px;">

                <!-- Device Info Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-info-circle"></i>
                            <span>Status Aset Terkini (Hasil Pencarian)</span>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="table">
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
                                @forelse($results as $dev)
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
                                                <i class="fa-solid fa-user-gear"
                                                    style="color: var(--accent-indigo); margin-right: 4px;"></i>
                                                {{ $dev['current_holder'] }}
                                            @else
                                                <i class="fa-solid fa-warehouse"
                                                    style="color: var(--text-muted); margin-right: 4px;"></i>
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
                                @empty
                                    <x-empty-state colspan="9" icon="fa-magnifying-glass"
                                        title="Tidak ada perangkat ditemukan"
                                        message="Tidak ada perangkat yang cocok dengan kata kunci &quot;{{ $q }}&quot;. Coba kata kunci atau Serial Number lain." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Transaction Audit Trail Logs -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>Riwayat Audit Trail (Lifecycle Log)</span>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID Log</th>
                                    <th>Device SN</th>
                                    <th>Aksi Perubahan</th>
                                    <th>Dari</th>
                                    <th>Ke</th>
                                    <th>Operator</th>
                                    <th>Scanned By</th>
                                    <th>Via</th>
                                    <th>Tanggal & Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($audit_trails as $tx)
                                    <tr>
                                        <td style="font-family: monospace; font-size: 12px; color: var(--text-muted);">
                                            {{ $tx['id'] }}
                                        </td>
                                        <td style="font-weight: 600; color: var(--accent-blue);">{{ $tx['device_sn'] }}</td>
                                        <td>
                                            @if($tx['action'] === 'RECEIVING')
                                                <span class="badge badge-success">{{ $tx['action'] }}</span>
                                            @elseif($tx['action'] === 'TRANSFER_OUT' || $tx['action'] === 'TRANSFER_IN')
                                                <span class="badge badge-info">{{ $tx['action'] }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ $tx['action'] }}</span>
                                            @endif
                                            @if(!empty($tx['notes']))
                                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><i class="fa-solid fa-circle-info"></i> {{ $tx['notes'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $tx['from'] }}</td>
                                        <td>{{ $tx['to'] }}</td>
                                        <td>{{ $tx['operator'] }}</td>
                                        <td><i class="fa-solid fa-barcode" style="font-size: 11px; margin-right: 4px;"></i>
                                            {{ $tx['scanned_by'] }}</td>
                                        <td>{{ $tx['via'] }}</td>
                                        <td style="color: var(--text-secondary); font-size: 13px;">{{ $tx['timestamp'] }}</td>
                                    </tr>
                                @empty
                                    <x-empty-state colspan="9" icon="fa-clock-rotate-left"
                                        title="Belum ada riwayat audit trail"
                                        message="Tidak ada riwayat audit trail untuk kata kunci &quot;{{ $q }}&quot;." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @else
            <!-- Empty state searching -->
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                <i class="fa-solid fa-chart-line" style="font-size: 48px; margin-bottom: 16px; color: var(--border-color);"></i>
                <h4 style="color: var(--text-secondary); font-size: 16px; font-weight: 600;">Temukan Detail Perangkat</h4>
                <p style="font-size: 13px; max-width: 400px; margin: 8px auto 0;">Masukkan Serial Number atau IMEI di kolom
                    pencarian di atas untuk melihat detail siklus hidup dan riwayat transaksi.</p>
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
    </script>
@endsection