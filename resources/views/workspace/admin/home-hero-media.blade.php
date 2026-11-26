@extends('layouts.workspace')
@section('title','Homepage Hero Media')
@section('heading','Homepage Hero Media')
@section('description','Select the image or promo video displayed in the homepage hero across all website skins.')
@section('content')
<link rel="stylesheet" href="{{ asset('css/website-skins-manager.css') }}">
<div class="wsm">
    <div class="wsm-actions"><a class="wsm-link" href="{{ route('admin.appearance.index') }}">← Appearance</a><a class="wsm-link" href="{{ route('admin.appearance.website-skins.index') }}">Website Skins</a><a class="wsm-link" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage ↗</a></div>
    @if(session('status'))<p class="wsm-message" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="wsm-message wsm-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="wsm-panel wsm-summary">
        <div><p class="wsm-eyebrow">Current homepage attraction</p><h2>{{ $current ? ucfirst($current['kind']) : 'Default Image' }}</h2>
            <p>{{ $current ? $current['media']->original_name : 'Each homepage keeps its original artwork.' }}</p>
            <p>One shared selection applies immediately to Sunrise and all nine skins, and to the default Classic hero. Campaign-player settings remain independent.</p>
        </div>
        @if($current)<x-home-hero-media :selection="$current"/>
        @else<img class="wsm-art" src="{{ asset('images/website-skins/natural-premium.svg') }}" width="800" height="420" alt="Example default skin illustration">@endif
    </section>
    <div class="wsm-grid" aria-label="Homepage hero media sources">
        <section class="wsm-panel wsm-card-content">
            <div class="wsm-name"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="2"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg><div><p class="wsm-eyebrow">01 · Built in</p><h3>Default Image</h3></div></div>
            <p>Restore the original homepage image or the active skin's own artwork.</p>
            <form method="POST" action="{{ route('admin.appearance.home-hero-media.update') }}">@csrf<input type="hidden" name="source" value="default"><button class="wsm-button" type="submit" @disabled(!$current)>Use default image</button></form>
        </section>
        <section class="wsm-panel wsm-card-content">
            <div class="wsm-name"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5"/></svg><div><p class="wsm-eyebrow">02 · New file</p><h3>Upload Media</h3></div></div>
            <p>Image or MP4/WebM video. Maximum 50 MB; saved to your configured Media Library storage.</p>
            <form class="wsm-card-content" style="padding:0" method="POST" enctype="multipart/form-data" action="{{ route('admin.appearance.home-hero-media.update') }}">
                @csrf<input type="hidden" name="source" value="upload">
                <label>Image or video<input class="block w-full text-sm" type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm" required></label>
                <label>Accessible description<input class="w-full rounded-lg border bg-transparent p-2 text-sm" name="label" maxlength="200" placeholder="Learn Cambodia promotional video"></label>
                <button class="wsm-button" type="submit">Upload &amp; activate</button>
            </form>
        </section>
        <section class="wsm-panel wsm-card-content">
            <div class="wsm-name"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 8h18M8 3v5M16 3v5M8 12l8 4-8 4z"/></svg><div><p class="wsm-eyebrow">03 · Existing file</p><h3>Media Library</h3></div></div>
            <p>Find existing images or promo videos below. Activating a file makes it publicly viewable.</p>
            <a class="wsm-link" href="#hero-library">Browse available media ↓</a>
        </section>
    </div>
    <section id="hero-library" class="wsm-panel wsm-schedule">
        <h2>Choose from Media Library</h2><p>Recommended: 1920 × 1080 (16:9), optimized for the web. Videos play on demand with controls.</p>
        <form class="wsm-schedule-form" method="GET" action="{{ route('admin.appearance.home-hero-media.index') }}">
            <label>Search<input type="search" name="q" maxlength="80" value="{{ $search }}" placeholder="Promo video"></label>
            <label>Media type<select name="kind"><option value="">All media</option><option value="image" @selected($kind==='image')>Images</option><option value="video" @selected($kind==='video')>Videos</option></select></label>
            <button class="wsm-button" type="submit">Search</button>
        </form>
        <div class="wsm-grid">
            @forelse($items as $media)
                <article class="wsm-panel">
                    @if(str_starts_with($media->mime,'image/'))
                        <img class="wsm-art" src="{{ route('editor-media.show',$media) }}" alt="" loading="lazy" width="800" height="420">
                    @else
                        <div class="wsm-art" style="display:grid;place-items:center;background:#142438;color:white"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m10 7 7 5-7 5z" fill="currentColor"/></svg></div>
                    @endif
                    <div class="wsm-card-content">
                        <strong style="overflow-wrap:anywhere">{{ $media->original_name }}</strong><p>{{ $media->mime }} · {{ number_format($media->size / 1048576, 2) }} MB</p>
                        <a class="wsm-link" href="{{ route('editor-media.show',$media) }}" target="_blank" rel="noopener">Preview file ↗</a>
                        <form class="wsm-card-content" style="padding:0" method="POST" action="{{ route('admin.appearance.home-hero-media.update') }}">
                            @csrf<input type="hidden" name="source" value="library"><input type="hidden" name="media_id" value="{{ $media->id }}">
                            <label>Accessible description<input class="w-full rounded-lg border bg-transparent p-2 text-sm" name="label" maxlength="200" value="{{ ($current['media']->id ?? null) === $media->id ? $current['label'] : '' }}" placeholder="Describe this media"></label>
                            <button class="wsm-button" type="submit">{{ ($current['media']->id ?? null) === $media->id ? 'Save current selection' : 'Use this media' }}</button>
                        </form>
                    </div>
                </article>
            @empty<p>No matching images or videos. Upload one above.</p>@endforelse
        </div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </section>
</div>
@endsection
