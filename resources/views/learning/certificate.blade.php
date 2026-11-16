@extends('layouts.public')
@section('title', $certificate->snapshot['heading'])
@section('content')
    @php($design = $certificate->snapshot)
    <div class="mx-auto max-w-5xl space-y-4 px-4 py-8">
        @if($certificate->revoked_at)<p class="text-center font-semibold text-red-600">Revoked certificate</p>@endif
        <article class="certificate-sheet mx-auto space-y-5 p-8 text-center sm:p-14" style="--certificate-accent:{{ $design['accent'] }}" data-orientation="{{ $design['orientation'] }}">
            <p class="text-sm font-semibold">{{ $design['type'] }}</p>
            <h1 class="text-3xl font-extrabold">{{ $design['heading'] }}</h1>
            <p class="text-xl font-semibold">{{ $design['learner'] }}</p>
            <p class="whitespace-pre-line">{{ strtr($design['body'], ['{learner}'=>$design['learner'], '{course}'=>$design['course']]) }}</p>
            <p class="font-semibold">{{ $design['issuer'] }}</p>
            <p class="text-sm">Issued {{ $certificate->created_at->toDateString() }}</p>
            <p class="break-all text-xs">{{ $certificate->code }}</p>
        </article>
        <button type="button" data-print-certificate class="mx-auto block rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Print / Save as PDF</button>
    </div>
@endsection
