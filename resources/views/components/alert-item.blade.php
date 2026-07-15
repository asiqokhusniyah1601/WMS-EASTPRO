@props([
    'alert',
    'variant' => 'feed',
    'alertId' => null,
])

@php
    $level = $alert['level'] ?? 'info';
    $icon = $alert['icon'] ?? 'fa-circle-info';
    $type = $alert['type'] ?? '';
    $message = $alert['message'] ?? '';
    $warehouse = $alert['warehouse'] ?? null;

    // Smart action: arahkan ke aksi yang relevan per jenis item.
    if ($type === 'DEVICE') {
        $actionUrl = route('receiving');
        $actionLabel = 'Restock';
        $actionIcon = 'fa-truck-ramp-box';
        $actionPrimary = true;
    } elseif ($type === 'ACCESSORY') {
        $actionUrl = route('receiving') . '?tab=accessory';
        $actionLabel = 'Restock';
        $actionIcon = 'fa-truck-ramp-box';
        $actionPrimary = true;
    } else {
        $actionUrl = route('alerts');
        $actionLabel = 'Tindak';
        $actionIcon = 'fa-magnifying-glass';
        $actionPrimary = false;
    }
@endphp

@if($variant === 'bell')
    <a href="{{ route('alerts') }}" class="notif-item {{ $level }}" data-alert-id="{{ $alertId }}">
        <i class="fa-solid {{ $icon }}"></i>
        <div class="notif-text">
            <div class="notif-msg">{!! $message !!}</div>
        </div>
    </a>
@else
    <div class="alert-feed-item lvl-{{ $level }}">
        <div class="afi-icon"><i class="fa-solid {{ $icon }}"></i></div>
        <div class="afi-body">
            <div class="afi-msg">{!! $message !!}</div>
            @if($warehouse)
                <div class="afi-meta">
                    <i class="fa-solid fa-warehouse"></i> {{ $warehouse }} · {{ $type }}
                </div>
            @endif
            <a href="{{ $actionUrl }}" class="alert-action-btn {{ $actionPrimary ? '' : 'secondary' }}">
                <i class="fa-solid {{ $actionIcon }}"></i> {{ $actionLabel }}
            </a>
        </div>
    </div>
@endif
