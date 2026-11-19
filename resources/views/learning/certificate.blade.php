@extends('layouts.public')
@section('title', $certificate->snapshot['heading'])
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/certificate-designer.css') }}?v=2">
    <script defer src="{{ asset('js/cert-qr-qrcode-generator.js') }}"></script>
    <script defer src="{{ asset('js/certificate-qr.js') }}"></script>
@endpush
@section('content')
    @php
        $design = $certificate->snapshot + [
            'date' => 'Issued '.$certificate->created_at->toDateString(),
            'code' => $certificate->code,
        ];
        $verification = route('certificates.verify', ['code' => $certificate->code]);
    @endphp
    <div class="cert-issued mx-auto max-w-5xl space-y-4 px-4 py-8">
        @if($certificate->revoked_at)<p class="text-center font-semibold text-red-600">Revoked certificate</p>@endif
        <x-certificate-paper :certificate="$design" :revoked="(bool) $certificate->revoked_at" :verification-url="$verification"
            :background="!empty($design['design']['background']) ? route('certificates.background', $certificate->code) : null" />
        <div class="flex flex-wrap justify-center gap-3">
            <button type="button" data-print-certificate class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Print / Save as PDF</button>
            <a href="{{ $verification }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold dark:border-slate-700">{{ __('certificate_verification.title') }}</a>
        </div>
    </div>
@endsection
