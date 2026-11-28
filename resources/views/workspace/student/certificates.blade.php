@extends('layouts.workspace')
@section('title', __('certificate_verification.my_certificates'))
@section('heading', __('certificate_verification.my_certificates'))
@section('content')
    <div class="space-y-4">
        @forelse($certificates as $certificate)
            @php($snapshot = $certificate->snapshot)
            <article class="workspace-panel flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $snapshot['type'] }}</p>
                    <h2 class="mt-1 text-lg font-extrabold">{{ $snapshot['assessment'] ?? $snapshot['course'] }}</h2>
                    <p class="mt-1 text-sm">{{ __('certificate_verification.date') }} · {{ $certificate->created_at->toDateString() }}</p>
                </div>
                <a class="rounded-xl border border-slate-400 px-4 py-2.5 text-sm font-semibold" href="{{ route('certificates.show', [$certificate->code, 'lang' => app()->getLocale()]) }}">
                    {{ __('certificate_verification.view_certificate') }}
                </a>
            </article>
        @empty
            <p class="workspace-panel p-5">{{ __('certificate_verification.no_certificates') }}</p>
        @endforelse
        {{ $certificates->links() }}
    </div>
@endsection
