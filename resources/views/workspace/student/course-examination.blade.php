@php($publishedExams = $course->exams->where('published', true))
@if($publishedExams->isNotEmpty() || $certificates->isNotEmpty() || $canClaimCertificate)
    <section class="workspace-panel mt-6 space-y-4 p-5">
        @if($publishedExams->isNotEmpty())
            <h2 id="course-exams" class="text-lg font-extrabold">Course Exams</h2>
            @foreach($publishedExams as $exam)
                @php($history = $attempts->get($exam->id, collect()))
                @php($active = $history->first(fn ($entry) => !$entry->submitted_at && $entry->expires_at->isFuture()))
                <article class="rounded-xl border border-slate-300 p-4 dark:border-slate-700">
                    <h3 class="font-bold">{{ $exam->title }}</h3>
                    <p class="mt-1 text-sm">{{ $exam->type->name }} · {{ $exam->time_limit }} min · {{ $history->count() }}/{{ $exam->attempt_limit }} attempts started</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm font-semibold">
                        @if($active)
                            <a class="text-blue-700 dark:text-blue-300" href="{{ route('account.exam-attempts.show',[$enrollment,$active]) }}">Continue attempt</a>
                        @elseif($history->count() < $exam->attempt_limit)
                            <form method="post" action="{{ route('account.exam-attempts.start',[$enrollment,$exam]) }}">@csrf<button class="rounded-lg bg-blue-700 px-3 py-2 text-white">Start exam</button></form>
                        @endif
                        @foreach($history->whereNotNull('submitted_at') as $previous)
                            <a class="text-blue-700 dark:text-blue-300" href="{{ route('account.exam-attempts.show',[$enrollment,$previous]) }}">Result · {{ $previous->score }}%</a>
                        @endforeach
                    </div>
                </article>
            @endforeach
        @endif
        @if($certificates->isNotEmpty() || $canClaimCertificate)
            <h2 class="text-lg font-extrabold">{{ __('certificate_verification.kind_course') }} · {{ __('certificate_verification.certificates') }}</h2>
            @if($canClaimCertificate)
                <x-certificate-award :claim="route('account.certificates.claim-course', [$enrollment, 'lang' => app()->getLocale()])" />
            @endif
            @foreach($certificates as $certificate)
                <a class="block text-sm font-semibold text-blue-700 dark:text-blue-300" href="{{ route('certificates.show',$certificate->code) }}">{{ $certificate->snapshot['heading'] }}</a>
            @endforeach
        @endif
    </section>
@endif
