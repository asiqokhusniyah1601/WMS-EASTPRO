@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'iconColor' => 'var(--accent-indigo)',
])

<div class="page-header">
    <div class="page-header-text">
        <h2 class="page-header-title">
            @if($icon)<i class="fa-solid {{ $icon }}" style="color: {{ $iconColor }};"></i>@endif
            <span>{{ $title }}</span>
        </h2>
        @if($subtitle)
            <p class="page-header-sub">{{ $subtitle }}</p>
        @endif
    </div>

    @if(trim($slot ?? '') !== '')
        <div class="page-header-actions">{{ $slot }}</div>
    @endif
</div>
