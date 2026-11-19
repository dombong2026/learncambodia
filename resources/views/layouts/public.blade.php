<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    @php
        $locale = app()->getLocale();
        $socialLocale = ['en' => 'en_US', 'km' => 'km_KH', 'zh' => 'zh_CN'][$locale] ?? 'en_US';
        $socialUrl = url()->query(url()->current(), ['lang' => $locale]);
        $meta = \App\Support\PlatformBranding::pageMeta(
            $__env->getSection('title'),
            $__env->getSection('meta_description'),
            $__env->getSection('og_image'),
        );
    @endphp
    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:site_name" content="{{ \App\Support\PlatformBranding::name() }}">
    <meta property="og:url" content="{{ $socialUrl }}">
    <meta property="og:locale" content="{{ $socialLocale }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    @if($meta['image'])
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @include('partials.brand-head')
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @stack('public_styles')
</head>
@php
    $typography = \App\Support\Appearance::approved('typography');
    $fontClass = ['system'=>'font-sans','serif'=>'font-serif','mono'=>'font-mono'][$typography['font_style']];
    $scaleClass = ['compact'=>'text-sm','normal'=>'text-base','large'=>'text-lg'][$typography['text_scale']];
@endphp
<body class="{{ $fontClass }} {{ $scaleClass }} min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    @include('partials.header')
    @include('partials.account-preview')
    <main id="main-content" class="min-h-[50vh]">@yield('content')</main>
    @include('partials.footer')
</body>
</html>
