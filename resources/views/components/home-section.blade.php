@props(['id', 'title', 'image', 'items', 'catalogUrl' => null, 'catalogLabel' => null])

<section id="{{ $id }}" class="lc-home-section" aria-labelledby="{{ $id }}-title">
    <div class="lc-home-section-media">
        <img src="{{ asset('images/home/'.$image.'.webp') }}" width="1024" height="576" alt="" loading="lazy" decoding="async">
    </div>
    <div class="lc-home-section-body">
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        {{ $slot }}
        <ul class="lc-home-title-list">
            @foreach($items as $item)
                <li>
                    <a href="{{ $item['url'] }}">
                        <span>{{ $item['title'] }}</span>
                        <x-workspace-icon name="chevron-right" aria-hidden="true" />
                    </a>
                </li>
            @endforeach
        </ul>
        @if($catalogUrl)
            <a class="lc-home-catalog-link" href="{{ $catalogUrl }}">
                {{ $catalogLabel }} <x-workspace-icon name="chevron-right" aria-hidden="true" />
            </a>
        @endif
    </div>
</section>
