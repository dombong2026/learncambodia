@extends('layouts.workspace')
@section('title', 'Campaign Builder')
@section('workspace_compact', '1')
@push('workspace_styles')
<link rel="stylesheet" href="{{ asset('css/campaign-studio-premium.css') }}">
<style>
#cs-builder .cs-editor-toolbar button{display:inline-grid;place-items:center;width:34px;height:34px;padding:6px;border:1px solid transparent;border-radius:8px;transition:background .15s,color .15s}
#cs-builder .cs-editor-toolbar button:hover{background:rgba(87,134,186,.16);border-color:rgba(116,150,187,.3)}
#cs-builder .cs-editor-toolbar button svg{display:block;flex:none;width:17px;height:17px;stroke:currentColor}
#cs-builder .cs-editor-toolbar button:focus-visible{outline:2px solid #f0c36c;outline-offset:2px}
</style>
@endpush
@section('content')
@php
    $coverAsset = $campaign && $campaign->draft !== null
        ? ($campaign->draft_cover_asset ?? '') : ($campaign?->cover_asset ?? '');
    $coverKind = $campaign?->draft_kind ?? $campaign?->kind ?? 'quiz';
@endphp
<section class="cs-shell" id="cs-builder"
    data-save-url="{{ route('admin.appearance.campaign.save') }}"
    data-upload-url="{{ route('admin.appearance.campaign.media') }}"
    data-campaign-id="{{ $campaign?->id ?? '' }}"
    data-asset-base="{{ url('/campaign-media') }}"
    data-version="{{ (int) $version }}"
    data-cover-asset="{{ $coverAsset }}"
    data-cover-kind="{{ $coverKind }}">
    <div class="cs-topbar">
        <div class="cs-topbar-copy">
            <a class="cs-back" href="{{ route('admin.appearance.edit', 'home') }}">← All campaigns</a>
            <div class="cs-kicker">HOMEPAGE CAMPAIGN</div>
            <h1>{{ $campaign ? 'Edit campaign' : 'Create a new campaign' }}</h1>
            <p>Build interactive quizzes and exams with modules, lessons, explanations, and feedback.</p>
        </div>
        <div class="cs-topbar-actions">
            <span id="cs-save-state" role="status" aria-live="polite" class="cs-state">Ready to edit</span>
            <button class="cs-btn cs-btn-quiet" id="cs-save-draft-top" type="button">Save draft</button>
            <button class="cs-btn cs-btn-primary" id="cs-publish-top" type="button">Publish</button>
        </div>
    </div>

    <div id="cs-alert" class="cs-alert" role="alert" hidden></div>
    <div class="cs-card" style="margin-bottom:17px">
        <label class="cs-label" for="cs-campaign-title">Campaign title</label>
        <input id="cs-campaign-title" class="cs-input" maxlength="180" required value="{{ $campaign?->draft_title ?? $campaign?->title ?? '' }}" placeholder="e.g. English Grammar — 20 Questions">
        <p class="cs-note">Create as many independent campaigns as you need. Each has its own cover, questions, and publication status.</p>
    </div>

    <div class="cs-layout">
        <div class="cs-main">
            <div class="cs-section-heading">
                <div>
                    <h2>Modules &amp; lessons</h2>
                    <p>Organize lessons into collapsible modules. Questions are numbered automatically.</p>
                </div>
                <button class="cs-btn cs-btn-outline" type="button" id="cs-add-module">＋ Add module</button>
            </div>
            <div class="cs-summary-strip">
                <div><span id="cs-modules-count">0</span><small>Modules</small></div>
                <div><span id="cs-lessons-count">0</span><small>Lessons</small></div>
                <div><span id="cs-questions-count">0</span><small>Questions</small></div>
            </div>
            <div id="cs-modules" class="cs-modules" aria-live="polite"></div>
            <div class="cs-help">
                <strong>Editing guidance</strong>
                <p>Each module can contain multiple lessons. A lesson can include a rich text introduction, optional media, questions, answer choices, a correct answer, and explanations.</p>
            </div>
        </div>

        <aside class="cs-sidebar">
            <section class="cs-card cs-cover-card">
                <div class="cs-card-heading">
                    <div><span class="cs-kicker">CAMPAIGN MEDIA</span><h2>Featured cover</h2></div>
                    <span class="cs-chip">1920 × 1080</span>
                </div>
                <p class="cs-subtext">This cover represents this Quiz or Exam campaign, not an individual lesson.</p>
                <div id="cs-cover-preview" class="cs-cover-preview" aria-label="Campaign cover preview">
                    <div id="cs-cover-placeholder" class="cs-cover-placeholder">
                        <span aria-hidden="true">▧</span>
                        <strong>No cover selected</strong>
                        <small>Upload a 16:9 image</small>
                    </div>
                    <img id="cs-cover-image" src="{{ $coverAsset ? route('campaign.asset', $coverAsset) : '' }}" alt="Featured campaign cover" @if(!$coverAsset) hidden @endif>
                </div>
                <label class="cs-label" for="cs-cover-kind">Featured content type</label>
                <select id="cs-cover-kind" class="cs-control">
                    <option value="quiz" @selected($coverKind === 'quiz')>Quiz</option>
                    <option value="exam" @selected($coverKind === 'exam')>Exam</option>
                </select>
                <label class="cs-upload-button" for="cs-cover-file">＋ Upload cover image</label>
                <input id="cs-cover-file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden>
                <button id="cs-remove-cover" type="button" class="cs-btn cs-btn-quiet cs-full" @if(!$coverAsset) hidden @endif>Remove featured cover</button>
                <p id="cs-cover-message" class="cs-note" aria-live="polite">JPG, PNG, or WebP. Exactly 1920 × 1080 pixels.</p>
            </section>

            <section class="cs-card">
                <div class="cs-card-heading"><div><span class="cs-kicker">PUBLICATION</span><h2>Publish campaign</h2></div></div>
                <p class="cs-subtext">Save your work privately or publish it directly as administrator. Existing review workflows elsewhere are unchanged.</p>
                <div class="cs-publication-state">
                    <span class="cs-dot" aria-hidden="true"></span>
                    <span>{{ $hasDraft ? 'Unpublished changes available' : ($publishedAt ? 'Published campaign' : 'Not published yet') }}</span>
                </div>
                <button class="cs-btn cs-btn-primary cs-full" type="button" id="cs-publish">Publish campaign</button>
                <button class="cs-btn cs-btn-outline cs-full" type="button" id="cs-save-draft">Save draft</button>
                <p class="cs-note">Changes are saved only when you choose Save draft or Publish.</p>
            </section>

            <section class="cs-card">
                <div class="cs-card-heading"><div><span class="cs-kicker">OVERVIEW</span><h2>Campaign outline</h2></div></div>
                <div id="cs-outline" class="cs-outline"></div>
            </section>
        </aside>
    </div>

    <script type="application/json" id="cs-initial-rows">@json($rows ?? [])</script>
</section>
<script defer src="{{ asset('js/campaign-studio-premium.js') }}"></script>
@endsection
