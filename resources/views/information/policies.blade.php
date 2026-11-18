@extends('layouts.public')
@section('title', 'Policy Center')
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/public-policies.css') }}">
@endpush
@section('content')
<section class="policy-center">
    <h1 class="text-4xl font-black tracking-tight">Policy Center</h1>
    @if($policies->isNotEmpty())
        <ol class="policy-grid" role="list" start="{{ $policies->firstItem() }}">
            @foreach($policies as $policy)
                @php
                    $number = $policies->firstItem() + $loop->index;
                    $icon = match($policy->slug) {
                        'acceptable-use-and-academic-integrity-policy' => 'book-open-check',
                        'account-and-data-deletion-policy' => 'user-round',
                        'assessment-results-and-certificate-review-procedure' => 'award',
                        'complaints-and-consumer-dispute-procedure' => 'messages-square',
                        'cookies-and-browser-storage-notice' => 'layers',
                        'copyright-and-content-rights-policy' => 'file-pen',
                        'course-information-and-purchase-procedure' => 'book',
                        'email-and-marketing-preferences' => 'mail',
                        'enrollment-fees-and-refunds-policy' => 'wallet',
                        'learner-safeguarding-and-accessibility-policy' => 'shield-plus',
                        'personal-data-requests-procedure' => 'id-card',
                        'privacy', 'privacy-policy' => 'lock',
                        'security-reporting-and-account-recovery' => 'key',
                        'terms', 'terms-of-service' => 'scale',
                        default => 'file',
                    };
                @endphp
                <li>
                    <a class="policy-card" href="{{ url()->query($policy->publicUrl(), ['lang' => app()->getLocale()]) }}" aria-labelledby="policy-title-{{ $number }}">
                        <div class="policy-card-header">
                            <span class="policy-card-icon"><x-workspace-icon :name="$icon" /></span>
                            <span class="policy-card-number" aria-hidden="true">{{ sprintf('%02d', $number) }}</span>
                        </div>
                        <h2 class="policy-card-title" id="policy-title-{{ $number }}">{{ $policy->title }}</h2>
                        @if($policy->summary)
                            <p class="policy-card-summary">{{ $policy->summary }}</p>
                        @endif
                        @if($policy->policy_effective_on)
                            <p class="policy-card-date">Effective <time datetime="{{ $policy->policy_effective_on->toDateString() }}">{{ $policy->policy_effective_on->toFormattedDateString() }}</time></p>
                        @endif
                    </a>
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-6 text-sm text-slate-600 dark:text-slate-300">No published policies.</p>
    @endif
    <div class="mt-6">{{ $policies->links() }}</div>
</section>
@endsection
