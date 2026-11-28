@props(['item'])

<div @class(['lc-lib-cover', 'lc-lib-cover--book' => $item->kind === 'book', 'lc-lib-cover--resource' => $item->kind === 'resource'])>
    @if($item->cover_id)
        <img src="{{ route('library.cover', $item) }}" alt="" loading="lazy" decoding="async">
    @else
        <x-workspace-icon :name="$item->kind === 'book' ? 'book-open' : 'file'" aria-hidden="true"/>
    @endif
</div>
