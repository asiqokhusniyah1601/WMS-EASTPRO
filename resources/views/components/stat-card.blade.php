@props([
    'color' => 'blue',
    'icon' => 'fa-cube',
    'title' => '',
    'value' => null,
    'valueId' => null,
    'href' => null,
    'drill' => null,
    'hint' => 'Lihat detail',
    'hintIcon' => 'fa-arrow-up-right-from-square',
])

@php
    $tag = $href ? 'a' : 'div';
    $classes = 'stat-card ' . $color . ($href ? ' stat-card-link' : '');
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($drill) data-drill="{{ $drill }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}>
    <div class="stat-icon"><i class="fa-solid {{ $icon }}"></i></div>
    <div class="stat-details">
        <h3>{{ $title }}</h3>
        <div class="stat-value" @if($valueId) id="{{ $valueId }}" @endif>{{ $value }}</div>
        @if(trim($slot ?? '') !== '')
            {{ $slot }}
        @elseif($href)
            <span class="stat-detail-hint"><i class="fa-solid {{ $hintIcon }}"></i> {{ $hint }}</span>
        @endif
    </div>
</{{ $tag }}>
