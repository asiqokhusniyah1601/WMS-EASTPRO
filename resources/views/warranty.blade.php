@extends('layouts.app')

@section('title', 'Garansi Perangkat | WMS EasyGo')

@section('styles')
<style>
    .warranty-card {
        border-radius: 12px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        padding: 24px;
        margin-bottom: 24px;
    }

    .warranty-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .status-badge.warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-badge.expired {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Modal styling */
    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .modal-backdrop.show {
        opacity: 1;
        pointer-events: auto;
    }

    .modal-container {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        width: 100%;
        max-width: 450px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        transform: translateY(-20px);
        transition: transform 0.3s ease;
        overflow: hidden;
    }

    .modal-backdrop.show .modal-container {
        transform: translateY(0);
    }

    .modal-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .modal-close-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 18px;
        cursor: pointer;
    }

    .modal-close-btn:hover {
        color: var(--text-primary);
    }
</style>
@endsection

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-shield-halved"
        title="Garansi Perangkat"
        subtitle="Pantau masa aktif sewa dan garansi perangkat pelanggan WMS." />

    @if(session('success'))
        <div class="alert-box alert-success animate-fade-in" style="margin-bottom: 24px;">
            <div class="alert-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="alert-message">{{ session('success') }}</div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-list"></i>
                <span>Daftar Masa Aktif Perangkat</span>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Serial Number</th>
                        <th>Tipe Perangkat</th>
                        <th>Pelanggan</th>
                        <th>Kepemilikan</th>
                        <th>Tgl Berakhir</th>
                        <th>Masa Aktif</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        @php
                            $custDev = $customerDeviceMap->get($device->id);
                            $customerName = $custDev && $custDev->customer ? $custDev->customer->name : 'Tidak Diketahui';
                            
                            $endDate = \Carbon\Carbon::parse($device->warranty_end_date);
                            $isExpired = $endDate->isPast();
                            $daysLeft = now()->startOfDay()->diffInDays($endDate, false);

                            $statusClass = 'active';
                            $statusLabel = 'Aktif';
                            if ($isExpired) {
                                $statusClass = 'expired';
                                $statusLabel = 'Habis';
                            } elseif ($daysLeft <= 7) {
                                $statusClass = 'warning';
                                $statusLabel = 'Segera Habis';
                            }
                        @endphp
                        <tr>
                            <td style="font-weight: 600; color: var(--accent-blue);">{{ $device->serial_number }}</td>
                            <td><span class="badge badge-info">{{ $device->type }}</span></td>
                            <td><strong>{{ $customerName }}</strong></td>
                            <td>
                                @if($device->ownership_status === 'SEWA')
                                    <span class="badge badge-primary"><i class="fa-solid fa-clock-rotate-left"></i> Sewa</span>
                                @else
                                    <span class="badge badge-success"><i class="fa-solid fa-handshake"></i> Beli Putus</span>
                                @endif
                            </td>
                            <td>{{ $endDate->format('d M Y') }}</td>
                            <td>
                                @if($isExpired)
                                    <span style="color: var(--accent-rose); font-weight: 600;">Lewat {{ abs($daysLeft) }} Hari</span>
                                @else
                                    <span style="color: var(--accent-emerald); font-weight: 600;">Sisa {{ $daysLeft }} Hari</span>
                                @endif
                            </td>
                            <td>
                                <span class="status-badge {{ $statusClass }}">
                                    <i class="fa-solid {{ $isExpired ? 'fa-circle-xmark' : ($daysLeft <= 7 ? 'fa-circle-exclamation' : 'fa-circle-check') }}"></i>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="openRenewalModal('{{ $device->id }}', '{{ $device->serial_number }}')"
                                    style="margin-right: 4px;">
                                    <i class="fa-solid fa-arrows-rotate"></i> Perpanjang
                                </button>
                                <button type="button" class="btn btn-sm" onclick="openStopModal('{{ $device->id }}', '{{ $device->serial_number }}')"
                                    style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);">
                                    <i class="fa-solid fa-circle-stop"></i> Stop
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                <i class="fa-solid fa-shield-halved" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--text-muted);"></i>
                                Belum ada perangkat E-SEAL terinstall dengan masa garansi / sewa aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RENEWAL MODAL -->
<div class="modal-backdrop" id="renewalModal">
    <div class="modal-container">
        <form action="{{ route('warranty.renew') }}" method="POST">
            @csrf
            <input type="hidden" name="device_id" id="modal_device_id">
            
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-arrows-rotate" style="color: var(--accent-blue);"></i>
                    <span>Perpanjang Garansi / Sewa</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeRenewalModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                    Perpanjang masa aktif untuk perangkat SN: <strong id="modal_sn_display" style="color: var(--accent-blue);"></strong>.
                </p>
                
                <div class="form-group">
                    <label class="form-label" for="modal_duration">Durasi Perpanjangan</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="number" name="warranty_duration" id="modal_duration" min="1" value="1" class="form-control" style="width: 80px; text-align: center;" required>
                        <select name="warranty_unit" id="modal_unit" class="form-control">
                            <option value="days">Hari</option>
                            <option value="weeks">Minggu</option>
                            <option value="months" selected>Bulan</option>
                            <option value="years">Tahun</option>
                        </select>
                    </div>
                    <small style="color: var(--text-muted); margin-top: 4px; display: block;">Masa aktif baru dihitung dari hari ini.</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRenewalModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- STOP / UNINSTALL MODAL -->
<div class="modal-backdrop" id="stopModal">
    <div class="modal-container">
        <form action="{{ route('warranty.stop') }}" method="POST">
            @csrf
            <input type="hidden" name="device_id" id="stop_device_id">

            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-circle-stop" style="color: #ef4444;"></i>
                    <span>Hentikan Masa Aktif (Uninstall)</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeStopModal()">&times;</button>
            </div>

            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                    Anda akan menghentikan masa aktif perangkat SN:
                    <strong id="stop_sn_display" style="color: #ef4444;"></strong>.
                </p>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="stop_warehouse_code" style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">Kembalikan ke Gudang (untuk QC) <span style="color: var(--accent-rose);">*</span></label>
                    <select name="warehouse_code" id="stop_warehouse_code" class="form-control" required>
                        <option value="">— Pilih Gudang Tujuan —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->code }}" {{ session('active_warehouse_code') === $wh->code ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; font-size: 13px; color: #ef4444;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Setelah dihentikan, perangkat tidak akan muncul lagi di daftar ini. Perangkat dan SIM card pasangannya akan dikembalikan ke gudang yang dipilih untuk menjalani QC ulang.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeStopModal()">Batal</button>
                <button type="submit" class="btn" style="background: #ef4444; color: #fff;">Ya, Hentikan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openRenewalModal(deviceId, serialNumber) {
        document.getElementById('modal_device_id').value = deviceId;
        document.getElementById('modal_sn_display').innerText = serialNumber;
        document.getElementById('renewalModal').classList.add('show');
    }

    function closeRenewalModal() {
        document.getElementById('renewalModal').classList.remove('show');
    }

    function openStopModal(deviceId, serialNumber) {
        document.getElementById('stop_device_id').value = deviceId;
        document.getElementById('stop_sn_display').innerText = serialNumber;
        document.getElementById('stopModal').classList.add('show');
    }

    function closeStopModal() {
        document.getElementById('stopModal').classList.remove('show');
    }

    // Close on click outside modal container
    ['renewalModal','stopModal'].forEach(id => {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    });
</script>
@endsection
