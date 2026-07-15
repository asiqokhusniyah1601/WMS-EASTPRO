@props([
    'name' => 'warehouse',
    'label' => 'Gudang',
    'warehouses' => [],
    'id' => null,
    'required' => true,
    'selected' => null,
    'hint' => null,
    'placeholder' => '-- Pilih Gudang --',
    'showEmptyOption' => true,
    'readonly' => false,
])

@php
    use App\Models\Warehouse;

    $user = auth()->user();
    $lockedCode = ($user && $user->isWarehouseBound()) ? $user->warehouse_code : null;
    $selectedCode = $lockedCode ?? $selected ?? session('active_warehouse_code');
    $fieldId = $id ?? $name;

    if ($readonly && !$lockedCode) {
        $lockedCode = $selectedCode;
    }

    $options = [];
    if ($warehouses instanceof \Illuminate\Support\Collection) {
        foreach ($warehouses as $wh) {
            $options[$wh->code] = $wh->name . ($wh->type ? ' (' . $wh->type . ')' : '');
        }
    } elseif (is_array($warehouses)) {
        foreach ($warehouses as $code => $optLabel) {
            if (is_string($code)) {
                $options[$code] = (string) $optLabel;
            } elseif (is_object($optLabel) && isset($optLabel->code)) {
                $options[$optLabel->code] = $optLabel->name . (isset($optLabel->type) ? ' (' . $optLabel->type . ')' : '');
            }
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'form-group']) }}>
    <label for="{{ $fieldId }}" class="form-label">{{ $label }} @if($required)<span style="color: var(--accent-rose);">*</span>@endif</label>

    @if($lockedCode)
        @php
            $displayName = $options[$lockedCode]
                ?? Warehouse::where('code', $lockedCode)->value('name')
                ?? $lockedCode;
        @endphp
        <input type="hidden" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $lockedCode }}">
        <div class="form-control" style="background: var(--bg-tertiary); cursor: default; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-lock" style="color: var(--text-muted); font-size: 12px;" title="Gudang terikat ke akun Anda"></i>
            <span>{{ $displayName }}</span>
            <span style="font-size: 11px; color: var(--text-muted); font-family: monospace; margin-left: auto;">{{ $lockedCode }}</span>
        </div>
    @else
        <select name="{{ $name }}" id="{{ $fieldId }}" class="form-control" @if($required) required @endif>
            @if($showEmptyOption && (!$required || !$selectedCode))
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $code => $display)
                <option value="{{ $code }}" {{ $selectedCode === $code ? 'selected' : '' }}>{{ $display }}</option>
            @endforeach
        </select>
    @endif

    @if($hint)
        <small style="color: var(--text-muted); display: block; margin-top: 4px;">{{ $hint }}</small>
    @endif
</div>
