@extends('layouts.public')
@section('title', __('certificate_verification.title'))
@section('meta_description', __('certificate_verification.intro'))

@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/certificate-verification.css') }}?v=2">
    <script defer src="{{ asset('js/cert-qr-jsqr.js') }}"></script>
    <script defer src="{{ asset('js/certificate-verification.js') }}?v=2"></script>
@endpush

@section('content')
@php
    $methods = [
        'id' => ['id-card', 'code'],
        'upload' => ['cloud-storage', 'upload'],
        'camera' => ['target', 'camera_tab'],
    ];
@endphp
<div class="cv-page" data-certificate-verification
    data-messages="{{ json_encode(\Illuminate\Support\Arr::only(__('certificate_verification'), [
        'invalid_file', 'no_qr', 'wrong_qr', 'unreadable', 'requesting', 'scanning', 'camera_failed', 'checking',
        'camera_unavailable', 'reading_image', 'one_image', 'copied', 'copy_failed',
    ]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
    <div class="cv-container">
        <div class="cv-intro">
            <span class="cv-emblem" aria-hidden="true"><x-workspace-icon name="shield-check" /></span>
            <h1>{{ __('certificate_verification.title') }}</h1>
            <p>{{ __('certificate_verification.intro') }}</p>
            <aside class="cv-hint">
                <x-workspace-icon name="info" aria-hidden="true" />
                <p>{{ __('certificate_verification.id_location') }}</p>
            </aside>
        </div>

        <div class="cv-workspace">
            @if($status)
                <section class="cv-result cv-result-{{ $status }}" aria-labelledby="cv-result-title">
                    <header class="cv-result-heading">
                        <span class="cv-result-symbol" aria-hidden="true"><x-workspace-icon :name="$record ? 'check' : 'info'" /></span>
                        <div>
                            <h2 id="cv-result-title">{{ __('certificate_verification.'.$status) }}</h2>
                            <p>{{ __('certificate_verification.'.$status.'_message') }}</p>
                        </div>
                    </header>
                    @if($record)
                        <dl class="cv-record">
                            @foreach($record as $key => $value)
                                <div @class(['cv-record-id' => $key === 'code'])>
                                    <dt>{{ __('certificate_verification.'.$key) }}</dt>
                                    <dd>
                                        <span @if($key === 'code') dir="ltr" @endif>{{ $value }}</span>
                                        @if($key === 'code')
                                            <button class="cv-copy" type="button" data-cv-copy="{{ $value }}" hidden>
                                                {{ __('certificate_verification.copy_id') }}
                                            </button>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="cv-result-footer">
                            <a class="cv-secondary" href="{{ route('certificates.verify', ['lang' => app()->getLocale()]) }}">
                                {{ __('certificate_verification.check_another') }}
                                <x-workspace-icon name="chevron-right" aria-hidden="true" />
                            </a>
                            <p class="cv-message" role="status" data-cv-status></p>
                        </div>
                    @endif
                </section>
            @endif

            @unless($record)
                <section class="cv-panel">
                    <div class="cv-tabs" role="tablist" aria-label="{{ __('certificate_verification.methods') }}" data-cv-tabs hidden>
                        @foreach($methods as $method => [$icon, $label])
                            <button type="button" role="tab" id="cv-tab-{{ $method }}" aria-controls="cv-panel-{{ $method }}"
                                aria-selected="{{ $method === 'id' ? 'true' : 'false' }}" tabindex="{{ $method === 'id' ? '0' : '-1' }}"
                                data-cv-tab="{{ $method }}">
                                <x-workspace-icon :name="$icon" aria-hidden="true" />
                                <span>{{ __('certificate_verification.'.$label) }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="cv-pane" id="cv-panel-id" data-cv-pane="id">
                        <form data-cv-form method="GET" action="{{ route('certificates.verify') }}">
                            <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                            <label for="cv-code">{{ __('certificate_verification.input') }}</label>
                            <div class="cv-form-row">
                                <input data-cv-code id="cv-code" name="code" type="text" dir="ltr" spellcheck="false"
                                    autocomplete="off" autocapitalize="none" maxlength="350" required
                                    placeholder="{{ __('certificate_verification.id_placeholder') }}" value="{{ $code ?? $input }}">
                                <button class="cv-primary" type="submit">
                                    {{ __('certificate_verification.submit') }}
                                    <x-workspace-icon name="chevron-right" aria-hidden="true" />
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="cv-pane" id="cv-panel-upload" data-cv-pane="upload" hidden>
                        <div class="cv-drop" data-cv-drop>
                            <span class="cv-method-symbol" aria-hidden="true"><x-workspace-icon name="cloud-storage" /></span>
                            <p class="cv-drop-label" id="cv-drop-label">{{ __('certificate_verification.drop_image') }}</p>
                            <p id="cv-file-help">{{ __('certificate_verification.formats') }}</p>
                            <div class="cv-file-action">
                                <input data-cv-file id="cv-file" type="file" accept="image/png,image/jpeg,image/webp"
                                    aria-describedby="cv-drop-label cv-file-help">
                                <label class="cv-secondary" for="cv-file">{{ __('certificate_verification.choose_image') }}</label>
                            </div>
                        </div>
                        <p class="cv-privacy"><x-workspace-icon name="lock" aria-hidden="true" />{{ __('certificate_verification.local') }}</p>
                    </div>

                    <div class="cv-pane cv-camera" id="cv-panel-camera" data-cv-pane="camera" hidden>
                        <div class="cv-camera-stage">
                            <span class="cv-method-symbol" aria-hidden="true" data-cv-camera-icon><x-workspace-icon name="target" /></span>
                            <video data-cv-video aria-label="{{ __('certificate_verification.camera_preview') }}" playsinline muted hidden></video>
                            <span class="cv-camera-guide" aria-hidden="true" data-cv-guide hidden></span>
                        </div>
                        <p class="cv-camera-help">{{ __('certificate_verification.scanning') }}</p>
                        <button class="cv-primary" type="button" data-cv-start>{{ __('certificate_verification.scan') }}</button>
                        <button class="cv-secondary" type="button" data-cv-stop hidden>{{ __('certificate_verification.stop') }}</button>
                    </div>
                    <p class="cv-message" role="status" data-cv-status></p>
                </section>
            @endunless
        </div>
    </div>
</div>
@endsection
