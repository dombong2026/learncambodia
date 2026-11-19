@extends('layouts.public')
@section('title', $certificate->snapshot['heading'])
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/certificate-designer.css') }}?v=1">
@endpush
@section('content')
    @php
        $design = $certificate->snapshot + [
            'date' => 'Issued '.$certificate->created_at->toDateString(),
            'code' => $certificate->code,
        ];
    @endphp
    <div class="cert-issued mx-auto max-w-5xl space-y-4 px-4 py-8">
        @if($certificate->revoked_at)<p class="text-center font-semibold text-red-600">Revoked certificate</p>@endif
        <x-certificate-paper :certificate="$design" :revoked="(bool) $certificate->revoked_at"
            :background="!empty($design['design']['background']) ? route('certificates.background', $certificate->code) : null" />
        <button type="button" data-print-certificate class="mx-auto block rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white">Print / Save as PDF</button>
    </div>
@endsection
