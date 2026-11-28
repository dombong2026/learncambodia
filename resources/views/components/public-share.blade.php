@props(['url', 'title'])

@php
    $publicUrl = url()->query($url, ['lang' => app()->getLocale()]);
    $encodedUrl = rawurlencode($publicUrl);
    $encodedTitle = rawurlencode($title);
    $destinations = [
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl,
        'Telegram' => 'https://t.me/share/url?url='.$encodedUrl.'&text='.$encodedTitle,
        'WhatsApp' => 'https://api.whatsapp.com/send?text='.rawurlencode($title.' '.$publicUrl),
        'X' => 'https://twitter.com/intent/tweet?url='.$encodedUrl.'&text='.$encodedTitle,
    ];
@endphp

@once
    <link rel="stylesheet" href="{{ asset('css/public-share.css') }}?v=2">
    <script defer src="{{ asset('js/public-share.js') }}?v=1"></script>
@endonce
<details {{ $attributes->class('public-share') }} data-public-share>
    <summary aria-label="{{ __('detail.share') }}">
        <x-public-social-icon name="share" />
        <span>{{ __('detail.share') }}</span>
    </summary>
    <div class="public-share__panel">
        @foreach($destinations as $name => $destination)
            <a href="{{ $destination }}" target="_blank" rel="noopener noreferrer nofollow">
                <x-public-social-icon :name="strtolower($name)" :class="'public-share__icon public-share__icon--'.strtolower($name)" />
                {{ $name }}
            </a>
        @endforeach
        <button type="button" data-public-share-native hidden>
            <x-public-social-icon name="share" class="public-share__icon" />
            {{ __('public_links.native_share') }}
        </button>
        <button type="button" data-public-share-copy hidden>
            <x-public-social-icon name="link" class="public-share__icon" />
            {{ __('public_links.copy') }}
        </button>
        <input type="url" readonly value="{{ $publicUrl }}" aria-label="{{ __('detail.share_url') }}" data-public-share-url data-title="{{ $title }}">
        <p role="status" data-public-share-status data-copied="{{ __('detail.share_copied') }}" data-fallback="{{ __('detail.share_fallback') }}"></p>
    </div>
</details>
