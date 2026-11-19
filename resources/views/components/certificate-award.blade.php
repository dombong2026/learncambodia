@props(['certificate' => null, 'claim'])

@if($certificate && !$certificate->revoked_at)
    <a class="inline-block rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white" href="{{ route('certificates.show', [$certificate->code, 'lang' => app()->getLocale()]) }}">
        {{ __('certificate_verification.view_certificate') }}
    </a>
@elseif(!$certificate)
    <form method="post" action="{{ $claim }}">
        @csrf
        <button class="rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">{{ __('certificate_verification.claim_certificate') }}</button>
    </form>
@endif
