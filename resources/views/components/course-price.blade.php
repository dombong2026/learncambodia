@props(['course', 'quote' => null])
@php($quote ??= \App\Support\CoursePricing::quote($course->pricing))
<span {{ $attributes }}>
    @if($quote['model'] === 'paid')
        @if($quote['offer'])<span>{{ $quote['offer'] }} · </span>@endif
        @if($quote['amount_minor'] < $quote['base_minor'])
            <s>{{ \App\Support\CoursePricing::formatAmount($quote['base_minor'], $quote['currency']) }}</s>
        @endif
        <strong>{{ \App\Support\CoursePricing::formatAmount($quote['amount_minor'], $quote['currency']) }}</strong>
    @elseif($quote['model'] === 'membership')
        <strong>{{ $quote['plan'] }}</strong> membership
    @else
        <strong>Free</strong>
    @endif
</span>
