<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    @hasSection('public_robots')
        <meta name="robots" content="@yield('public_robots')">
    @endif
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
        @hasSection('assessment_image_alt')
            <meta property="og:image:alt" content="{{ $__env->getSection('assessment_image_alt') }}">
            <meta name="twitter:image" content="{{ $meta['image'] }}">
            <meta name="twitter:image:alt" content="{{ $__env->getSection('assessment_image_alt') }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @include('partials.brand-head')
    @include('partials.theme-boot')
    @include('partials.audio-player-head')
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    <link rel="stylesheet" href="{{ asset('css/public-mobile-navigation.css') }}">
    @stack('public_styles')
    @php
        $usePublicFrame = $__env->hasSection('public_not_found') || request()->routeIs(
            'home', 'categories.*', 'courses.index', 'courses.show', 'articles.*',
            'authors.*', 'instructors.*', 'students.*', 'cefr.*',
            'assessments.exams', 'assessments.quizzes', 'assessments.show',
            'public.exams.*', 'public.quizzes.*', 'specialized-exams.show',
            'learning.exams', 'learning.exam.show', 'learning.reading',
            'learning.library', 'library.show', 'library.reader',
            'learning.recommendations', 'learning.leaderboard',
            'help.*', 'information.show', 'placement.*', 'pages.show', 'policies.*', 'certificates.verify',
        ) || (auth()->guest() && request()->routeIs('learning.studio', 'specialized-exams.attempts.show'));
    @endphp
    @if($usePublicFrame)
        <link rel="stylesheet" href="{{ asset('css/public-frame.css') }}?v=2">
    @endif
</head>
@php
    $typography = \App\Support\Appearance::approved('typography');
    $fontClass = ['system'=>'font-sans','serif'=>'font-serif','mono'=>'font-mono'][$typography['font_style']];
    $scaleClass = ['compact'=>'text-sm','normal'=>'text-base','large'=>'text-lg'][$typography['text_scale']];
@endphp
<body @if($usePublicFrame) data-public-frame @endif class="public-layout {{ $fontClass }} {{ $scaleClass }} min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-slate-900">{{ __('ui.skip_content') }}</a>
    @include('partials.header')
    @include('partials.account-preview')
    <main @hasSection('learning-reading') data-learning-reader @endif id="main-content" class="min-h-[50vh]">
@hasSection('learning-reading') @include('partials.learning-reader-toolbar') @endif
@yield('content')</main>
    @include('partials.footer')
    @include('partials.mobile-study-navigation')
</body>
</html>
