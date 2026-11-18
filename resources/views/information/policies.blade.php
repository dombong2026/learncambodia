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
                @php($number = $policies->firstItem() + $loop->index)
                <li>
                    <a class="policy-card" href="{{ url()->query($policy->publicUrl(), ['lang' => app()->getLocale()]) }}" aria-labelledby="policy-title-{{ $number }}">
                        <div class="policy-card-header">
                            <span class="policy-card-icon"><x-policy-card-icon :policy="$policy" /></span>
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
