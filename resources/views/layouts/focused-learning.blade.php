<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ \App\Support\PlatformBranding::name() }}</title>
    @include('partials.brand-head')
    @include('partials.theme-boot')
    @include('partials.audio-player-head')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @stack('focused-scripts')
    <link rel="stylesheet" href="{{ asset('css/focused-learning.css') }}?v=4">
    <script defer src="{{ asset('js/focused-learning.js') }}?v=1"></script>
</head>
<body class="focused-learning antialiased">
    <a class="fl-skip" href="#lesson-main">{{ __('ui.skip_content') }}</a>
    <main data-learning-reader id="lesson-main" class="fl-shell max-w-[1600px] @yield('focused-layout-class')">
@include('partials.learning-reader-toolbar')
@yield('content')</main>
</body>
</html>
