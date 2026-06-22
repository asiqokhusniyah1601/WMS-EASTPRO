@extends('layouts.app')

<!--@yield('title', 'Stock Opname / Koreksi Stok | DLMS')-->

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-scale-balanced"
        iconColor="var(--accent-amber)"
        title="Stock Opname Aksesoris"
        subtitle="Koreksi stok aksesoris per gudang berdasarkan hasil hitung fisik. Sistem akan menyesuaikan stok global dan mencatat setiap perubahan di audit trail." />

    <!-- Warehouse Selector -->
    <div class="card" style="margin-bottom: 24px;">
        <form action="{{ route('stock.opname') }}" method="GET" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0; flex-grow: 1; max-width: 420px;">
                <label for="warehouse_selector">Pilih Gudang</label>
                <select name="warehouse" id="warehouse_selector" class="form-control" onchange="this.form.submit()">
                    @foreach($warehouses as $code => $name)
                        <option value="{{ $code }}" {{ $selected === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-primary">Tampilkan</button></noscript>
        </form>
    </div>

    <form action="{{ route('stock.opname.post') }}" method="POST">
        @csrf
        <input type="hidden" name="warehouse_code" value="{{ $selected }}">

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            <!-- Opname Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-toolbox"></i>
                        <span>Hitung Fisik: {{ $warehouses[$selected] ?? $selected }}</span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Aksesoris</th>
                                <th style="text-align: center;">Qty Sistem</th>
                                <th style="text-align: center; width: 140px;">Qty Fisik</th>
                                <th style="text-align: center;">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accessories as $acc)
                                @php $sysQty = (int) ($whStock[$acc->code] ?? 0); @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $acc->name }}</strong>
                                        <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">{{ $acc->code }}</div>
                                    </td>
                                    <td style="text-align: center;" data-sys="{{ $sysQty }}">{{ $sysQty }}</td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="counts[{{ $acc->code }}]" value="{{ $sysQty }}"
                                            class="form-control opname-input" data-sys="{{ $sysQty }}"
                                            style="text-align: center; padding: 6px;">
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge delta-badge" data-code="{{ $acc->code }}">0</span>
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state colspan="4" icon="fa-plug"
                                    title="Belum ada master aksesoris"
                                    message="Tambahkan data aksesoris di menu Master Data terlebih dahulu sebelum melakukan stock opname." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reason + Submit + Recent -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Konfirmasi Koreksi</div>
                    </div>
                    <div class="form-group">
                        <label for="reason">Alasan Penyesuaian <span style="color: var(--accent-red);">*</span></label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" required
                            placeholder="Contoh: Hasil stock opname bulanan, ditemukan selisih akibat...">{{ old('reason') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;"
                        onclick="return confirm('Simpan hasil stock opname untuk gudang ini?')">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Stock Opname
                    </button>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Koreksi Terakhir</div>
                    </div>
                    <div style="padding: 4px 0;">
                        @forelse($recentAdjustments as $adj)
                            <div style="padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                                <div style="font-size: 13px; font-weight: 600;">
                                    {{ $adj->accessory_code }}
                                    <span class="badge {{ $adj->qty >= 0 ? 'badge-success' : 'badge-danger' }}">
                                        {{ $adj->qty >= 0 ? '+' : '' }}{{ $adj->qty }}
                                    </span>
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">{{ $adj->notes }}</div>
                                <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">{{ $adj->created_at->format('Y-m-d H:i') }}</div>
                            </div>
                        @empty
                            <div style="color: var(--text-muted); font-size: 13px; font-style: italic; padding: 8px 0;">
                                Belum ada riwayat koreksi untuk gudang ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function recalcDelta(input) {
        const sys = parseInt(input.dataset.sys || '0', 10);
        const val = parseInt(input.value || '0', 10);
        const delta = (isNaN(val) ? 0 : val) - sys;
        const row = input.closest('tr');
        const badge = row.querySelector('.delta-badge');
        badge.textContent = (delta > 0 ? '+' : '') + delta;
        badge.className = 'badge delta-badge ' + (delta === 0 ? '' : (delta > 0 ? 'badge-success' : 'badge-danger'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.opname-input').forEach(function (input) {
            input.addEventListener('input', function () { recalcDelta(input); });
        });
    });
</script>
@endsection
