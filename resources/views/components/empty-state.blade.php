@props([
    'icon' => 'fa-inbox',
    'title' => 'Belum ada data',
    'message' => null,
    'colspan' => null,
])

@if($colspan)
<tr>
    <td colspan="{{ $colspan }}" style="padding: 0;">
@endif

<div class="empty-state">
    <div class="empty-state-icon"><i class="fa-solid {{ $icon }}"></i></div>
    <div class="empty-state-title">{{ $title }}</div>
    @if($message)
        <div class="empty-state-msg">{{ $message }}</div>
    @endif
    @if(trim($slot ?? '') !== '')
        <div class="empty-state-action">{{ $slot }}</div>
    @endif
</div>

@if($colspan)
    </td>
</tr>
@endif
