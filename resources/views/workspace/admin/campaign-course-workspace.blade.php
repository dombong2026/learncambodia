@extends('layouts.workspace')
@section('title', 'Campaign Course Builder')
@section('workspace_compact', '1')
@push('workspace_styles')
<link rel="stylesheet" href="{{ asset('css/campaign-studio-premium.css') }}">
<link rel="stylesheet" href="{{ asset('css/campaign-course-workspace.css') }}?v=20261124-1">
@endpush
@section('content')
@php
    $coverAsset = $campaign && $campaign->draft !== null
        ? ($campaign->draft_cover_asset ?? '') : ($campaign?->cover_asset ?? '');
    $coverKind = $campaign?->draft_kind ?? $campaign?->kind ?? 'quiz';
    $isPublished = ($campaign?->status ?? '') === 'published';
    $hasDraftChanges = $campaign?->draft !== null;
@endphp
<section class="cs-shell cs-course-workspace" id="cs-builder"
    data-save-url="{{ route('admin.appearance.campaign.save') }}"
    data-upload-url="{{ route('admin.appearance.campaign.media') }}"
    data-campaign-id="{{ $campaign?->id ?? '' }}"
    data-asset-base="{{ url('/campaign-media') }}"
    data-version="{{ (int) $version }}"
    data-cover-asset="{{ $coverAsset }}"
    data-cover-kind="{{ $coverKind }}"
    data-publication-status="{{ $isPublished ? 'published' : 'draft' }}">
    <header class="cs-course-header">
        <div class="cs-course-brand">
            <div class="cs-course-brandmark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" width="28" height="28"><path d="M3 27h26M5 27V14h4v13m14 0V14h4v13M11 27V9l5-6 5 6v18M13 14h6m-6 6h6M1 17h4m22 0h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span>Learn<span class="cs-brand-accent">Cambodia</span>.com</span>
        </div>
        <div class="cs-course-header-title">
            <span class="cs-course-label">CAMPAIGN STUDIO</span>
            <h1>{{ $campaign ? 'Edit campaign' : 'Course Builder' }} <span aria-hidden="true">/</span> <span id="cs-header-title">{{ $campaign?->draft_title ?? $campaign?->title ?? 'New learning campaign' }}</span></h1>
        </div>
        <div class="cs-course-header-actions">
            <span class="cs-course-saved" id="cs-save-state" role="status" aria-live="polite">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
                Ready to edit
            </span>
            <button class="cs-btn cs-btn-primary" id="cs-save-draft-top" type="button">Save draft</button>
            <a href="{{ route('admin.appearance.edit', ['section' => 'home', 'lang' => 'en']) }}" class="cs-course-exit" title="All campaigns">All campaigns</a>
        </div>
    </header>

    <div id="cs-alert" class="cs-alert" role="alert" hidden></div>
    <button id="cs-publish-top" type="button" hidden>Publish</button>

    <div class="cs-layout cs-course-grid">
        <aside class="cs-course-outline" aria-label="Course outline and lesson navigation">
            <div class="cs-course-outline-heading">
                <div class="cs-section-label">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="m12 2 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5"/></svg>
                    <h2>Course Outline</h2>
                </div>
                <span class="cs-mini-chip" id="cs-unit-label">0 units</span>
            </div>
            <div class="cs-course-outline-help">Select a lesson to edit. Drag within a unit to reorder lessons.</div>
            <nav class="cs-course-tree" id="cs-course-tree" aria-label="Modules and lessons"></nav>
            <div class="cs-course-outline-footer">
                <button class="cs-btn cs-btn-primary cs-full" type="button" id="cs-add-module">
                    <span aria-hidden="true">＋</span> Add New Unit
                </button>
            </div>
        </aside>

        <main class="cs-main cs-course-editor" aria-labelledby="cs-edit-title">
            <div class="cs-course-editor-heading">
                <div>
                    <div class="cs-kicker">CURRICULUM EDITOR</div>
                    <h2 id="cs-edit-title">Edit Lesson</h2>
                    <p id="cs-edit-subtitle">Select a lesson from the course outline to start editing.</p>
                </div>
                <div class="cs-course-editor-actions">
                    <span class="cs-mini-chip" id="cs-current-lesson-number">LESSON 1.1</span>
                    <button class="cs-btn cs-btn-outline" type="button" id="cs-save-draft-panel">Save</button>
                </div>
            </div>
            <nav class="cs-course-tabs" role="tablist" aria-label="Lesson editor sections">
                <button type="button" role="tab" id="cs-tab-metadata" data-cs-tab="metadata" aria-selected="true" aria-controls="cs-course-stage" class="is-active">Metadata (CEFR)</button>
                <button type="button" role="tab" id="cs-tab-curriculum" data-cs-tab="curriculum" aria-selected="false" aria-controls="cs-course-stage">Curriculum</button>
                <button type="button" role="tab" id="cs-tab-assessments" data-cs-tab="assessments" aria-selected="false" aria-controls="cs-course-stage">Assessments</button>
                <button type="button" role="tab" id="cs-tab-translation" data-cs-tab="translation" aria-selected="false" aria-controls="cs-course-stage">Translation (EN/KM)</button>
            </nav>
            <div id="cs-course-stage" class="cs-course-stage" role="tabpanel" aria-labelledby="cs-tab-metadata">
                <div class="cs-course-campaign-metadata" data-cs-section="metadata">
                    <div class="cs-form-heading">
                        <div class="cs-section-label"><h3>Campaign details</h3></div>
                        <span class="cs-mini-chip">CAMPAIGN SETTINGS</span>
                    </div>
                    <div class="cs-course-campaign-fields">
                        <div>
                            <label class="cs-label" for="cs-campaign-title">Campaign / course title</label>
                            <input id="cs-campaign-title" class="cs-input" maxlength="180" required value="{{ $campaign?->draft_title ?? $campaign?->title ?? '' }}" placeholder="e.g. Intensive IELTS Preparation">
                        </div>
                        <div>
                            <label class="cs-label" for="cs-course-type">Campaign type</label>
                            <select class="cs-control" id="cs-course-type">
                                <option value="quiz" @selected($coverKind === 'quiz')>Quiz campaign</option>
                                <option value="exam" @selected($coverKind === 'exam')>Exam campaign</option>
                            </select>
                        </div>
                    </div>
                    <p class="cs-note">All modules and lessons belong to this campaign. Create separate campaigns from the Homepage Appearance manager.</p>
                </div>
                <div class="cs-section-heading cs-course-current-heading">
                    <div>
                        <h3 id="cs-current-unit-heading">Unit 1</h3>
                        <p>Changes are saved to this campaign only.</p>
                    </div>
                    <span class="cs-course-selection-state" id="cs-current-content-type">QUIZ</span>
                </div>
                <div id="cs-modules" class="cs-modules" aria-live="polite"></div>
            </div>
            <div class="cs-course-metrics" aria-label="Campaign curriculum summary">
                <div><span id="cs-modules-count">0</span><small>Units</small></div>
                <div><span id="cs-lessons-count">0</span><small>Lessons</small></div>
                <div><span id="cs-questions-count">0</span><small>Questions</small></div>
            </div>
        </main>

        <aside class="cs-sidebar cs-course-workflow" aria-label="Campaign settings and publishing">
            <section class="cs-card cs-course-publish-card">
                <div class="cs-card-heading">
                    <div class="cs-section-label">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-8h6v8M2 9l10-6 10 6"/></svg>
                        <h2>Publishing &amp; Workflow</h2>
                    </div>
                </div>
                <div class="cs-workflow-state">
                    <span>Campaign state</span>
                    <span id="cs-publication-status" class="cs-course-status {{ $isPublished ? 'is-published' : 'is-draft' }}">{{ $isPublished ? 'Published' : 'Draft' }}</span>
                </div>
                <p class="cs-subtext">Administrators can save drafts and publish directly. No additional approval is required.</p>
                <button class="cs-btn cs-btn-primary cs-full" id="cs-publish" type="button">Publish campaign</button>
                <button class="cs-btn cs-btn-outline cs-full" id="cs-save-draft" type="button">Save draft</button>
                <div class="cs-workflow-steps" aria-label="Campaign readiness">
                    <div class="cs-workflow-step"><span class="cs-workflow-step-mark is-complete">✓</span><div><strong>Campaign created</strong><small>Prepare the title, units and lessons</small></div></div>
                    <div class="cs-workflow-step"><span class="cs-workflow-step-mark" id="cs-readiness-mark">2</span><div><strong>Content ready</strong><small id="cs-readiness-label">Add at least one quiz question</small></div></div>
                    <div class="cs-workflow-step"><span class="cs-workflow-step-mark {{ $isPublished ? 'is-complete' : '' }}" id="cs-published-mark">{{ $isPublished ? '✓' : '3' }}</span><div><strong>Published on homepage</strong><small id="cs-published-label">{{ $isPublished ? 'Visible in the homepage hero' : 'Not yet published' }}</small></div></div>
                </div>
            </section>

            <section class="cs-card cs-cover-card">
                <div class="cs-card-heading">
                    <div><span class="cs-kicker">CAMPAIGN MEDIA</span><h2>Featured cover</h2></div>
                    <span class="cs-chip">1920 × 1080</span>
                </div>
                <div id="cs-cover-preview" class="cs-cover-preview" aria-label="Campaign cover preview">
                    <div id="cs-cover-placeholder" class="cs-cover-placeholder">
                        <span aria-hidden="true">▧</span>
                        <strong>No cover selected</strong>
                        <small>Upload a 16:9 image</small>
                    </div>
                    <img id="cs-cover-image" src="{{ $coverAsset ? route('campaign.asset', $coverAsset) : '' }}" alt="Campaign featured cover" @if(!$coverAsset) hidden @endif>
                </div>
                <label class="cs-label" for="cs-cover-kind">Featured content type</label>
                <select id="cs-cover-kind" class="cs-control">
                    <option value="quiz" @selected($coverKind === 'quiz')>Quiz</option>
                    <option value="exam" @selected($coverKind === 'exam')>Exam</option>
                </select>
                <label class="cs-upload-button" for="cs-cover-file">＋ Upload cover image</label>
                <input id="cs-cover-file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden>
                <button id="cs-remove-cover" type="button" class="cs-btn cs-btn-quiet cs-full" @if(!$coverAsset) hidden @endif>Remove featured cover</button>
                <p id="cs-cover-message" class="cs-note" aria-live="polite">JPG, PNG, or WebP, exactly 1920 × 1080 pixels.</p>
            </section>
            <section class="cs-card cs-course-help-card">
                <div class="cs-section-label"><h2>Course setup tips</h2></div>
                <p class="cs-subtext">1. Create units and lessons in the outline.</p>
                <p class="cs-subtext">2. Set the CEFR level and lesson content.</p>
                <p class="cs-subtext">3. Build Quiz/Exam questions and explanations.</p>
                <p class="cs-subtext">4. Upload a featured cover and publish.</p>
                <div id="cs-outline" class="cs-outline" hidden></div>
            </section>
        </aside>
    </div>
    <script type="application/json" id="cs-initial-rows">@json($rows ?? [])</script>
</section>
<script defer src="{{ asset('js/campaign-studio-premium.js') }}?v=course-20261124"></script>
<script defer src="{{ asset('js/campaign-course-workspace.js') }}?v=course-20261124"></script>
@endsection
