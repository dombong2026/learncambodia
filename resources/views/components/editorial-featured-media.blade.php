@props(['entry'])
@switch($entry->featured_kind)
    @case('image')
        @if($entry->featuredMedia)
            <figure class="editorial-featured">
                <img src="{{ route('editor-media.show', $entry->featuredMedia) }}" alt="{{ $entry->featured_alt ?? '' }}" class="block max-h-[34rem] w-full object-contain">
            </figure>
        @endif
        @break
    @case('video')
        @if($entry->featuredMedia)
            <div class="editorial-featured"><x-featured-video :src="route('editor-media.show', $entry->featuredMedia)" :label="$entry->title"/></div>
        @endif
        @break
    @case('youtube')
        @if(preg_match('/^[A-Za-z0-9_-]{11}$/D', $entry->featured_value ?? ''))
            <div class="editorial-featured">
                <iframe class="block aspect-video w-full" src="https://www.youtube-nocookie.com/embed/{{ $entry->featured_value }}" title="{{ $entry->title }} on YouTube" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        @endif
        @break
    @case('color')
        @if(preg_match('/^#[a-f0-9]{6}$/D', $entry->featured_value ?? ''))
            <div class="editorial-featured editorial-color" style="--featured-color:{{ $entry->featured_value }}" aria-hidden="true"></div>
        @endif
        @break
@endswitch
