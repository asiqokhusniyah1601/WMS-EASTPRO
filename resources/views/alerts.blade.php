@extends('layouts.app')

@section('title', 'Alert Center | DLMS')

@php
    $totalInsights = count($insights['critical']) + count($insights['warning']) + count($insights['info']);
@endphp

@section('content')
<div class="animate-fade-in">
    <x-page-header
        icon="fa-bell"
        iconColor="var(--accent-amber)"
        title="Alert Center"
        subtitle="Notifikasi stok minimum, dead stock, dan prediksi tren AI yang membutuhkan perhatian.">
        @if(auth()->user()?->isWarehouseBound())
            <span class="badge badge-info" style="font-size: 12px;"><i class="fa-solid fa-lock"></i> Scope: {{ $warehouses[$view] ?? $view }}</span>
        @else
        <form method="GET" action="{{ route('alerts') }}" style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-filter" style="color: var(--text-muted); font-size: 12px;"></i>
            <select name="warehouse" class="form-control" style="width: auto; min-width: 200px; padding: 6px 10px; font-size: 13px;" onchange="this.form.submit()">
                <option value="global" {{ $view === 'global' ? 'selected' : '' }}>Semua Gudang (Global)</option>
                @foreach($warehouses as $code => $name)
                    <option value="{{ $code }}" {{ $view === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                @endforeach
            </select>
        </form>
        @endif
    </x-page-header>

    <!-- Summary cards -->
    <div class="stats-grid">
        <x-stat-card color="red" icon="fa-circle-exclamation" title="Kritis" :value="count($insights['critical'])" />
        <x-stat-card color="amber" icon="fa-triangle-exclamation" title="Peringatan" :value="count($insights['warning'])" />
        <x-stat-card color="blue" icon="fa-circle-info" title="Info" :value="count($insights['info'])" />
        <x-stat-card color="emerald" icon="fa-boxes-stacked" title="Alert Stok Minimum" :value="count($stockAlerts)" />
    </div>

    <!-- Stock Alert (StockAlertThreshold) -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-warehouse"></i> Stok di Bawah Batas Minimum</div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Jenis</th>
                        <th>Gudang</th>
                        <th style="text-align:center;">Sisa Stok</th>
                        <th style="text-align:center;">Batas Min</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockAlerts as $a)
                        <tr>
                            <td style="font-weight:600;"><i class="fa-solid {{ $a['icon'] }}" style="color: var(--text-muted); margin-right:6px;"></i>{{ $a['label'] }}</td>
                            <td><span class="badge badge-info">{{ $a['type'] }}</span></td>
                            <td>{{ $a['warehouse'] }}</td>
                            <td style="text-align:center; font-weight:700; color: {{ $a['current'] === 0 ? 'var(--accent-red)' : 'var(--accent-amber)' }};">{{ $a['current'] }}</td>
                            <td style="text-align:center;">{{ $a['min'] }}</td>
                            <td style="text-align:center;">
                                @if($a['level'] === 'critical')
                                    <span class="badge badge-danger">HABIS</span>
                                @else
                                    <span class="badge badge-warning">MENIPIS</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="6" icon="fa-circle-check"
                            title="Semua stok aman"
                            message="Semua stok berada di atas batas minimum. Tidak ada peringatan stok saat ini." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- AI Insights & other alerts (moved from dashboard) -->
    <div class="card" style="border-left: 4px solid var(--accent-indigo); background: linear-gradient(145deg, var(--card-bg) 0%, rgba(99, 102, 241, 0.05) 100%);">
        <div class="card-header" style="border-bottom: none;">
            <div class="card-title"><i class="fa-solid fa-brain" style="color: var(--accent-indigo);"></i> AI Trend Insights & Alerts</div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-top: 12px;">
            @php $hasInsights = false; @endphp

            @foreach($insights['critical'] as $insight)
                @php $hasInsights = true; @endphp
                <div class="alert alert-danger" style="margin-bottom: 0;">
                    <i class="fa-solid {{ $insight['icon'] }}"></i>
                    <div>
                        {!! $insight['message'] !!}
                        <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;"><i class="fa-regular fa-clock"></i> {{ $insight['time'] }}</div>
                    </div>
                </div>
            @endforeach

            @foreach($insights['warning'] as $insight)
                @php $hasInsights = true; @endphp
                <div class="alert alert-warning" style="margin-bottom: 0;">
                    <i class="fa-solid {{ $insight['icon'] }}"></i>
                    <div>
                        {!! $insight['message'] !!}
                        <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;"><i class="fa-regular fa-clock"></i> {{ $insight['time'] }}</div>
                    </div>
                </div>
            @endforeach

            @foreach($insights['info'] as $insight)
                @php $hasInsights = true; @endphp
                <div class="alert alert-info" style="margin-bottom: 0;">
                    <i class="fa-solid {{ $insight['icon'] }}"></i>
                    <div>
                        {!! $insight['message'] !!}
                        <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;"><i class="fa-regular fa-clock"></i> {{ $insight['time'] }}</div>
                    </div>
                </div>
            @endforeach

            @if(!$hasInsights)
                <div style="color: var(--text-muted); font-size: 13px; font-style: italic;">
                    <i class="fa-solid fa-check-circle" style="color: var(--accent-emerald);"></i> Tidak ada anomali tren yang terdeteksi saat ini.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
