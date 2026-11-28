@extends('layouts.public')

@section('title', 'English Placement Tests · '.config('app.name'))
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/placement.css') }}?v=1">
@endpush

@section('content')
<div class="lc-placement">
    <div class="lc-placement-hero">
        <div class="lc-placement-frame">
            <h1>English Placement Tests</h1>
            <p>Find a starting point for your English studies. Choose a free LearnCambodia assessment or work through its preparation lessons first.</p>
            <div class="lc-placement-actions">
                <a class="lc-placement-primary" href="#placement-tests">Choose a test <span aria-hidden="true">↓</span></a>
                <a class="lc-placement-secondary" href="#eap-placement">How EAP placement works</a>
            </div>
        </div>
    </div>
    <div class="lc-placement-frame lc-placement-body">
        <section id="placement-tests" aria-labelledby="placement-tests-title">
            <div class="lc-placement-section-head">
                <h2 id="placement-tests-title">Choose a placement test</h2>
                <p>All six tests use original LearnCambodia questions. Each includes a timed screening and a writing sample for instructor review.</p>
            </div>
            <div class="lc-placement-grid">
                @foreach ($tracks as $track => $entry)
                    @php($exam = $exams->get('placement-'.$track))
                    <article class="lc-placement-card">
                        <div class="lc-placement-meta"><span>{{ count($entry['modules']) }} modules · {{ array_sum(array_map('count', $entry['modules'])) }} lessons</span>@if($exam)<span>{{ $exam->time_limit }} min</span>@endif</div>
                        <h3><a href="{{ route('placement.show', ['track' => $track, 'lang' => app()->getLocale()]) }}">{{ $entry['title'] }}</a></h3>
                        <p>{{ $entry['description'] }}</p>
                        <dl class="lc-placement-facts">
                            <div><dt>Skills</dt><dd>{{ $entry['skills'] }}</dd></div>
                            <div><dt>Provider reference</dt><dd><a href="{{ $entry['provider_url'] }}">{{ $entry['provider'] }}</a></dd></div>
                        </dl>
                        <div class="lc-placement-actions">
                            <a class="lc-placement-secondary" href="{{ route('placement.show', ['track' => $track, 'lang' => app()->getLocale()]) }}">View lessons</a>
                            @if($exam)
                                <a class="lc-placement-primary" href="{{ route('specialized-exams.show', ['exam' => $exam, 'lang' => app()->getLocale()]) }}">Take test <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="eap-placement" class="lc-placement-panel" aria-labelledby="eap-title">
            <h2 id="eap-title">Placement for English for Academic Purposes</h2>
            <ol class="lc-placement-steps">
                <li><h3>Complete the screening</h3><p>Your score gives an immediate snapshot of the skills covered by the automatically graded items.</p></li>
                <li><h3>Write for 30 minutes</h3><p>Use the writing time to plan, draft and revise. An instructor applies the same rubric across all six pathways.</p></li>
                <li><h3>Review the writing feedback</h3><p>When the screening and writing point to different starting levels, give more weight to the writing for EAP placement.</p></li>
            </ol>
            <p class="lc-placement-note">LearnCambodia results are diagnostic practice. Formal CEFR classifications and official score reports come from the relevant providers.</p>
        </section>

        <section class="lc-placement-panel" aria-labelledby="placement-scores">
            <h2 id="placement-scores">Already have a test score?</h2>
            <p>If you have an IELTS Academic, TOEFL iBT or PTE Academic score, have the official report ready, including the test date and section scores. Ask the institution whether it accepts your report for placement or a waiver. Institutions set their own score and recency requirements.</p>
            <div class="lc-placement-actions">
                <a class="lc-placement-secondary" href="https://ielts.org/organisations/ielts-for-organisations/compare-ielts">Official score comparisons</a>
                <a class="lc-placement-secondary" href="{{ url('/cefr').'?lang='.app()->getLocale() }}">Explore CEFR levels</a>
            </div>
        </section>
    </div>
</div>
@endsection
