@extends('layouts.workspace')
@section('title','Home appearance')
@section('heading','Home appearance')
@section('description','Manage the homepage hero and campaign curriculum.')
@section('content')
<link rel="stylesheet" href="{{ asset('css/campaign-studio.css') }}">
<div class="cs cs-home">
<nav class="cs-heading"><a class="cs-link" href="{{ route('admin.appearance.index') }}">← Appearance</a><a class="cs-link" href="{{ route('home') }}">View homepage ↗</a></nav>
@foreach($errors->all() as $error)<p role="alert">{{ $error }}</p>@endforeach

<section class="cs-card" style="margin-bottom:20px">
    @php($activeLayout = \App\Support\Appearance::value('home','home_layout'))
    <h2>Homepage Layout Manager</h2>

    @if(session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p>Active homepage: <strong>{{ ucfirst($activeLayout) }}</strong></p>

    <form method="POST" action="{{ route('admin.appearance.home-layout.activate') }}">
        @csrf

        <label>Homepage design
            <select name="home_layout" required>
                <option value="classic" @selected(old('home_layout',$activeLayout)==='classic')>
                    Classic — Original homepage
                </option>
                <option value="sunrise" @selected(old('home_layout',$activeLayout)==='sunrise')>
                    Sunrise — Natural premium homepage
                </option>
            </select>
        </label>

        @error('home_layout')
            <p role="alert">{{ $message }}</p>
        @enderror

        <div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">
            <a class="cs-button" target="_blank" rel="noopener"
               href="{{ route('admin.appearance.home-preview','classic') }}">
                Preview Classic
            </a>

            <a class="cs-button" target="_blank" rel="noopener"
               href="{{ route('admin.appearance.home-preview','sunrise') }}">
                Preview Sunrise
            </a>

            <button type="submit" class="cs-primary">
                Activate homepage
            </button>
        </div>

        <p>Layout changes take effect immediately.
           No independent approval is required.</p>
    </form>
</section>

<div class="cs-layout">
@if($pendingRevision)
<section class="cs-card"><h2>Awaiting review</h2><p>Revision #{{ $pendingRevision->id }} needs independent approval.</p><a class="cs-link" href="{{ url('/dashboard/reviews?focus=appearance') }}">Open review queue →</a></section>
@else
<form class="cs-card" action="{{ route('admin.appearance.store','home') }}" method="post">@csrf
<input type="hidden" name="home_settings_version" value="{{ $revision?->id ?? $approvedVersion }}">

<h2>Classic homepage hero settings</h2>

<label>Display mode<select name="hero_mode"><option value="default" @selected(old('hero_mode',$settings['hero_mode'] ?? '')==='default')>Default hero</option><option value="course_player" @selected(old('hero_mode',$settings['hero_mode'] ?? '')==='course_player')>Course Player</option></select></label>
<label>Default hero title<input name="hero_title" maxlength="130" value="{{ old('hero_title',$settings['hero_title'] ?? '') }}"></label>
<label>Default hero introduction<textarea rows="3" name="hero_intro" maxlength="480">{{ old('hero_intro',$settings['hero_intro'] ?? '') }}</textarea></label>
<div><button class="cs-primary">Save appearance draft</button></div></form>
@endif
<aside class="cs-side"><section class="cs-card"><h2>Campaign builder</h2><p>Manage the modules, lessons and media in the homepage player.</p><a class="cs-button" href="{{ route('admin.appearance.campaign.edit',['lang'=>app()->getLocale()]) }}">Create a new campaign →</a></section>
@if($revision && !$pendingRevision)<form class="cs-card" method="post" action="{{ route('admin.appearance.submit',$revision) }}">@csrf<h2>Draft #{{ $revision->id }}</h2><button>Submit for review</button></form>@endif
</aside></div></div>

@include('workspace.admin.homepage-campaigns-table')
@endsection
