@props(['number', 'record', 'category', 'level', 'measure', 'status', 'href' => null, 'secondary' => null])
@php
    $tone = match ($status) {
        'Published', 'Approved', 'Active', 'Available' => 'positive',
        'Pending', 'Pending Review', 'Inactive', 'Awaiting activation' => 'waiting',
        'Rejected', 'Declined', 'Cancelled' => 'negative',
        default => 'neutral',
    };
    $menu = 'learning-actions-'.$record->public_id;
@endphp
<tr>
    <td class="learning-manager-number">{{ $number }}</td>
    <td><span class="learning-manager-id">{{ $record->public_id }}</span></td>
    <th scope="row" class="learning-manager-title">
        @if($href)<a href="{{ $href }}">{{ $record->title }}</a>@else{{ $record->title }}@endif
        @if($secondary)<small>{{ $secondary }}</small>@endif
    </th>
    <td class="learning-manager-category">{{ $category ?? '—' }}</td>
    <td class="learning-manager-level">{{ $level ?? '—' }}</td>
    <td class="learning-manager-number">{{ $measure }}</td>
    <td><span class="learning-manager-status" data-tone="{{ $tone }}">{{ $status }}</span></td>
    <td class="learning-manager-actions">
        @if($slot->isNotEmpty())
            <button type="button" class="learning-manager-trigger" popovertarget="{{ $menu }}" aria-label="Actions for {{ $record->title }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.9"/><circle cx="12" cy="12" r="1.9"/><circle cx="12" cy="19" r="1.9"/></svg>
            </button>
            <div id="{{ $menu }}" popover class="learning-manager-menu">{{ $slot }}</div>
        @endif
    </td>
</tr>
