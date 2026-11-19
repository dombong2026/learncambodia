@props(['certificate', 'background' => null, 'editable' => false, 'revoked' => false, 'verificationUrl' => null])

@php
    $design = \App\Support\CertificateDesign::options($certificate['design'] ?? null);
    $accent = \App\Support\CertificateDesign::color($certificate['accent'] ?? null, '#8b5e18');
    $texts = [
        'type' => $certificate['type'],
        'heading' => $certificate['heading'],
        'learner' => $certificate['learner'],
        'body' => strtr($certificate['body'], [
            '{learner}' => $certificate['learner'], '{course}' => $certificate['course'],
            '{assessment}' => $certificate['assessment'] ?? '',
        ]),
        'issuer' => $certificate['issuer'],
        'date' => $certificate['date'],
        'code' => $certificate['code'],
    ];
@endphp

<article class="cert-paper" aria-label="{{ $editable ? 'Certificate preview' : $certificate['heading'] }}"
    data-orientation="{{ ($certificate['orientation'] ?? null) === 'portrait' ? 'portrait' : 'landscape' }}"
    data-font="{{ $design['font'] }}" data-frame="{{ $design['frame'] ? 'on' : 'off' }}"
    style="--cert-accent:{{ $accent }};--cert-ink:{{ $design['ink'] }}">
    @if($editable || $background)
        <img data-cert-image class="cert-artwork" alt="" @if($background) src="{{ $background }}" @else hidden @endif>
    @endif
    <div class="cert-ornament" aria-hidden="true"></div>
    @foreach($texts as $key => $value)
        @php
            $tag = $key === 'heading' && !$editable ? 'h1' : 'p';
            $field = $design['fields'][$key];
        @endphp
        <{{ $tag }} class="cert-element cert-element-{{ $key }}" data-cert-field="{{ $key }}"
            style="--cert-x:{{ $field['x'] }}%;--cert-y:{{ $field['y'] }}%;--cert-size:{{ $field['size'] }}"
            >{{ $value }}</{{ $tag }}>
    @endforeach
    @if($editable || $verificationUrl)
        @php($qr = $design['fields']['qr'])
        <div class="cert-element cert-element-qr" data-cert-field="qr"
            data-certificate-qr="{{ $verificationUrl ?? route('certificates.verify') }}"
            style="--cert-x:{{ $qr['x'] }}%;--cert-y:{{ $qr['y'] }}%;--cert-size:{{ $qr['size'] }};">
            <svg role="img" aria-label="{{ __('certificate_verification.qr_label') }}" shape-rendering="crispEdges"></svg>
        </div>
    @endif
    @if($revoked)
        <p class="cert-revoked">Revoked certificate</p>
    @endif
</article>
