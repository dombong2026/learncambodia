@extends('layouts.workspace')
@section('title','Website Skins')
@section('heading','Website Skins')
@section('description','Manage the public website design, weekly rotation and seasonal editions.')
@section('content')
<link rel="stylesheet" href="{{ asset('css/website-skins-manager.css') }}">
<div class="wsm">
    <a class="wsm-link" href="{{ route('admin.appearance.index') }}">← Appearance</a>
    @if(session('status'))<p class="wsm-message" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="wsm-message wsm-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="wsm-panel wsm-summary">
        <div><p class="wsm-eyebrow">Global frontend appearance</p><h2>Website Skins</h2><p>One active skin at a time. Activating a new skin automatically deactivates the previous one.</p></div>
        <div><span class="wsm-eyebrow">Active skin</span><strong data-active-skin="{{ $activeSkin ?? 'legacy' }}">{{ $skins[$activeSkin]['name'] ?? 'Existing website' }}</strong><span>{{ ucfirst($settings['mode'] ?? 'legacy') }} mode</span></div>
        <div class="wsm-actions">
            <form method="POST" action="{{ route('admin.appearance.website-skins.update') }}">@csrf<input type="hidden" name="mode" value="automatic"><button class="wsm-button" type="submit" @disabled(($settings['mode'] ?? 'legacy') === 'automatic')>Automatic rotation</button></form>
            <form method="POST" action="{{ route('admin.appearance.website-skins.update') }}">@csrf<input type="hidden" name="mode" value="legacy"><button class="wsm-button wsm-outline" type="submit" @disabled(($settings['mode'] ?? 'legacy') === 'legacy')>Use existing website</button></form>
        </div>
    </section>
    <div class="wsm-grid" aria-label="Available website skins">
        @foreach($skins as $key => $skin)
            @php
                $isActive = $key === $activeSkin;
            @endphp
            <article class="wsm-panel wsm-skin" data-skin-card="{{ $key }}" data-active="{{ $isActive ? 'true' : 'false' }}">
                <img class="wsm-art" src="{{ asset('images/website-skins/'.$key.'.svg') }}" width="800" height="420" alt="" loading="lazy">
                <div class="wsm-card-content">
                    <div class="wsm-name">
                        <img src="{{ asset('images/website-skins/icons/'.$key.'.svg') }}" alt="" width="40" height="40">
                        <div><p class="wsm-eyebrow">{{ $skin['day'] }}</p><h3>{{ $skin['name'] }}</h3></div>
                    </div>
                    <p class="wsm-description">{{ ucfirst($skin['art_direction']) }}.</p>
                    <div class="wsm-palette" aria-label="Light and dark color palettes">
                        @foreach($skin['light'] as $color)<span style="background:{{ $color }}" title="{{ $color }}"></span>@endforeach
                        @foreach($skin['dark'] as $color)<span style="background:{{ $color }}" title="{{ $color }}"></span>@endforeach
                    </div>
                    <div class="wsm-controls">
                        <a class="wsm-link" href="{{ route('admin.appearance.website-skins.preview',$key) }}" target="_blank" rel="noopener">Preview ↗</a>
                        <form method="POST" action="{{ route('admin.appearance.website-skins.update') }}">
                            @csrf
                            <input type="hidden" name="mode" value="manual">
                            <input type="hidden" name="skin" value="{{ $key }}">
                            <button class="wsm-switch-button" type="submit" role="switch" aria-checked="{{ $isActive ? 'true' : 'false' }}" aria-label="{{ $skin['name'] }} active" @disabled($isActive && ($settings['mode'] ?? '') === 'manual')>
                                <span class="wsm-switch" aria-hidden="true"><span></span></span><span>{{ $isActive ? 'Active' : 'Activate' }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
    <section class="wsm-panel wsm-schedule">
        <h2>Seasonal and holiday schedules</h2><p>Automatic mode uses Cambodia time (UTC+7) and changes at midnight. Date ranges are inclusive and override the weekday schedule. Manual activation takes priority.</p>
        <form class="wsm-schedule-form" method="POST" action="{{ route('admin.appearance.website-skins.periods.add') }}">
            @csrf
            <label>Start date<input type="date" name="start" required></label>
            <label>End date<input type="date" name="end" required></label>
            <label>Skin<select name="skin" required>@foreach($skins as $key => $skin)<option value="{{ $key }}">{{ $skin['name'] }}</option>@endforeach</select></label>
            <button class="wsm-button" type="submit">Add schedule</button>
        </form>
        @forelse($settings['periods'] ?? [] as $period)
            <div class="wsm-history-row"><div><strong>{{ $skins[$period['skin']]['name'] ?? 'Unknown' }}</strong><p>{{ $period['start'] }} – {{ $period['end'] }}</p></div>
            <form method="POST" action="{{ route('admin.appearance.website-skins.periods.remove') }}">@csrf
                @foreach(['start','end','skin'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $period[$field] }}">@endforeach
                <button class="wsm-button wsm-outline" type="submit">Remove</button>
            </form></div>
        @empty<p>No custom periods configured.</p>@endforelse
    </section>
    <section class="wsm-panel wsm-schedule"><h2>Recent changes</h2>
        @forelse($history as $revision)
            <div class="wsm-history-row"><div><strong>{{ ucfirst($revision->settings['mode'] ?? 'legacy') }}@if(($revision->settings['mode'] ?? '') === 'manual') · {{ $skins[$revision->settings['manual_skin']]['name'] ?? 'Unknown' }}@endif</strong><p>Revision #{{ $revision->id }} · {{ $revision->created_at?->timezone('Asia/Phnom_Penh')->format('d M Y H:i') }}</p></div><span>{{ ucfirst($revision->status) }}</span></div>
        @empty<p>No skin changes yet.</p>@endforelse
    </section>
</div>
@endsection
