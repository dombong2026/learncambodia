@extends('layouts.public')
@section('title', __('certificate_verification.title'))
@section('meta_description', __('certificate_verification.intro'))

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/certificate-verification.css') }}">
    <script defer src="{{ asset('js/cert-qr-jsqr.js') }}"></script>
    <script defer src="{{ asset('js/certificate-verification.js') }}"></script>
@endpush

@section('content')
<div class="cv-page" data-certificate-verification
    data-messages="{{ json_encode(\Illuminate\Support\Arr::only(__('certificate_verification'), [
        'invalid_file', 'no_qr', 'wrong_qr', 'unreadable', 'requesting', 'scanning', 'camera_failed', 'checking', 'camera_unavailable',
    ]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
    <div class="cv-container">
        <div class="cv-intro">
            <h1>{{ __('certificate_verification.title') }}</h1>
            <p>{{ __('certificate_verification.intro') }}</p>
        </div>

        @if($status)
            <section class="cv-result cv-result-{{ $status }}" aria-labelledby="cv-result-title">
                <div class="cv-result-heading">
                    <span aria-hidden="true" class="cv-status-icon"><x-workspace-icon :name="$record ? 'check' : 'info'" /></span>
                    <div>
                        <h2 id="cv-result-title">{{ __('certificate_verification.'.$status) }}</h2>
                        <p>{{ __('certificate_verification.'.$status.'_message') }}</p>
                    </div>
                </div>
                @if($record)
                    <dl>
                        @foreach($record as $key => $value)
                            <div><dt>{{ __('certificate_verification.'.$key) }}</dt><dd @if($key === 'code') dir="ltr" @endif>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endif

        <div class="cv-panel">
            <form data-cv-form method="GET" action="{{ route('certificates.verify') }}">
                <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                <label for="cv-code">{{ __('certificate_verification.input') }}</label>
                <div class="cv-form-row">
                    <input data-cv-code id="cv-code" name="code" type="text" dir="ltr" spellcheck="false"
                        autocomplete="off" autocapitalize="none" maxlength="350" required value="{{ $code ?? $input }}">
                    <button class="cv-primary" type="submit">{{ __('certificate_verification.submit') }}</button>
                </div>
            </form>
            <div class="cv-qr-actions" data-cv-qr-actions hidden>
                <div class="cv-upload">
                    <label for="cv-file">{{ __('certificate_verification.upload') }}</label>
                    <p id="cv-file-help">{{ __('certificate_verification.formats') }}</p>
                    <input data-cv-file id="cv-file" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="cv-file-help">
                </div>
                <div class="cv-camera">
                    <button type="button" data-cv-start>{{ __('certificate_verification.scan') }}</button>
                    <button type="button" data-cv-stop hidden>{{ __('certificate_verification.stop') }}</button>
                    <video data-cv-video aria-label="{{ __('certificate_verification.camera_preview') }}" playsinline muted hidden></video>
                </div>
                <p class="cv-local">{{ __('certificate_verification.local') }}</p>
                <p class="cv-message" role="status" data-cv-status></p>
            </div>
        </div>
    </div>
</div>
@endsection
