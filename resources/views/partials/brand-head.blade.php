@php
    $favicon16 = \App\Support\PlatformBranding::asset('favicon-16');
    $favicon32 = \App\Support\PlatformBranding::asset('favicon-32');
    $touchIcon = \App\Support\PlatformBranding::asset('touch-icon');
    $appName = \App\Support\PlatformBranding::shortName();
    $manifestUrl = \App\Support\PlatformBranding::usesCustomManifest()
        ? route('brand.manifest')
        : asset('brand/site.webmanifest').'?v=20261114';
@endphp
@if(! $favicon16['custom'] && ! $favicon32['custom'])
    <link rel="icon" href="{{ asset('brand/favicon.ico') }}?v=20261114" sizes="any">
@endif
<link rel="icon" href="{{ $favicon32['url'] }}" sizes="32x32" type="image/png">
<link rel="icon" href="{{ $favicon16['url'] }}" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="{{ $touchIcon['url'] }}" sizes="180x180">
<link rel="manifest" href="{{ $manifestUrl }}">
<meta name="application-name" content="{{ $appName }}">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="msapplication-TileImage" content="{{ asset('brand/mstile-144x144.png') }}?v=20261114">
<meta name="msapplication-TileColor" content="#0c2d5b">
<link rel="stylesheet" href="{{ asset('css/platform-focus.css') }}?v=1">
