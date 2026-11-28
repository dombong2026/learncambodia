@extends('layouts.public')

@section('title', $entry['title'].' · '.config('app.name'))
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/placement.css') }}?v=1">
@endpush

@section('content')
<div class="lc-placement">
    <div class="lc-placement-hero">
        <div class="lc-placement-frame">
            <a class="lc-placement-back" href="{{ route('placement.index', ['lang' => app()->getLocale()]) }}">← All placement tests</a>
            <h1>{{ $entry['title'] }}</h1>
            <p>{{ $entry['description'] }}</p>
            <div class="lc-placement-actions">
                @if($exam)
                    <a class="lc-placement-primary" href="{{ route('specialized-exams.show', ['exam' => $exam, 'lang' => app()->getLocale()]) }}">Take test <span aria-hidden="true">→</span></a>
                @endif
                <a class="lc-placement-secondary" href="#preparation">Browse preparation lessons</a>
            </div>
        </div>
    </div>
    <div class="lc-placement-frame lc-placement-body lc-placement-track">
        <section id="preparation" aria-labelledby="placement-preparation-title">
            <div class="lc-placement-section-head">
                <h2 id="placement-preparation-title">Preparation modules</h2>
                <p>Try each practice task before opening its worked answer.</p>
            </div>
            @foreach($entry['modules'] as $module => $lessons)
                <section class="lc-placement-module">
                    <div class="lc-placement-section-head">
                        <h3>{{ $module }}</h3>
                        <p>Module {{ $loop->iteration }} · {{ count($lessons) }} lessons</p>
                    </div>
                    @foreach($lessons as $lesson)
                        <details class="lc-placement-lesson">
                            <summary>{{ $lesson['title'] }}</summary>
                            <div>
                                <p>{{ $lesson['instruction'] }}</p>
                                <p><strong>Try it</strong> {{ $lesson['practice'] }}</p>
                                <details class="lc-placement-answer">
                                    <summary>Show worked answer</summary>
                                    <p>{{ $lesson['answer'] }}</p>
                                </details>
                            </div>
                        </details>
                    @endforeach
                </section>
            @endforeach
        </section>
        <aside aria-label="Assessment and scoring">
            <div class="lc-placement-panel">
                <h2>Test at a glance</h2>
                <dl class="lc-placement-facts">
                    <div><dt>Skills</dt><dd>{{ $entry['skills'] }}</dd></div>
                    @if($exam)<div><dt>Time</dt><dd>{{ $exam->time_limit }} minutes total. Set aside about 30 minutes for writing.</dd></div>@endif
                    <div><dt>Screening</dt><dd>18 automatically scored questions with original simulated listening audio.</dd></div>
                    <div><dt>Provider reference</dt><dd><a href="{{ $entry['provider_url'] }}">{{ $entry['provider'] }}</a></dd></div>
                </dl>
            </div>
            @include('placement.rubric')
        </aside>
    </div>
</div>
@endsection
