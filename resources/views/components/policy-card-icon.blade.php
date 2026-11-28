@props(['policy'])

@php
    $icons = [
        'acceptable-use-and-academic-integrity-policy' => 'M12 5C8 3 5 3 2 4v15c4-1 7-1 10 2 3-3 6-3 10-2V4c-3-1-6-1-10 1Zm0 0v16M5 11l2 2 3-4',
        'account-and-data-deletion-policy' => 'M14.5 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M8.25 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 12h6',
        'assessment-results-and-certificate-review-procedure' => 'M12 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12ZM8.5 13l-2 8 5.5-3 5.5 3-2-8M10 8l1.5 1.5L14 7',
        'complaints-and-consumer-dispute-procedure' => 'M3 4h15v10H8l-5 4V4Zm5 4h6m-6 3h4m8-4h2v13l-4-3h-6',
        'cookies-and-browser-storage-notice' => 'M21 11a9 9 0 1 1-9-9 4 4 0 0 0 4 5 4 4 0 0 0 5 4ZM7.5 10h.01m2.5 6h.01m4-3h.01m2 4h.01',
        'copyright-and-content-rights-policy' => 'M12 2.5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19Zm3.5 6a5 5 0 1 0 0 7',
        'course-information-and-purchase-procedure' => 'M3 8h18l-1 13H4L3 8Zm5 0V6a4 4 0 1 1 8 0v2m-8 6h8m-8 3h5',
        'email-and-marketing-preferences' => 'M3 5h18v14H3zM3 6l9 7 9-7',
        'enrollment-fees-and-refunds-policy' => 'M3 6h17v14H3zM3 9h17m-8 3-3 3 3 3m-3-3h7M5 4h13',
        'learner-safeguarding-and-accessibility-policy' => 'M12 2l8 4v6c0 4-3 8-8 10-5-2-8-6-8-10V6l8-4Zm0 14-3.3-3.3a2 2 0 0 1 3.3-2.2 2 2 0 0 1 3.3 2.2L12 16Z',
        'personal-data-requests-procedure' => 'M3 5h18v14H3zM8 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-3 4a3 3 0 0 1 6 0m3-6h4m-4 4h4',
        'privacy-policy' => 'M5 11h14v10H5zM8 11V7a4 4 0 1 1 8 0v4m-4 4v2',
        'security-reporting-and-account-recovery' => 'M8 14a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Zm0-5.5h.01m4 3 9 9m-4-4 2-2m0 4 2-2',
        'terms-of-service' => 'M12 3v18M5 7h14M5 7l-3 7h6L5 7Zm14 0-3 7h6l-3-7ZM8 21h8',
    ];
    $title = \Illuminate\Support\Str::slug($policy->title);
    $slug = \Illuminate\Support\Str::slug($policy->slug);
    $alias = ['privacy' => 'privacy-policy', 'terms' => 'terms-of-service'];
    $path = $icons[$title] ?? $icons[$alias[$slug] ?? $slug] ?? null;
@endphp

<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @if($path)
        <path d="{{ $path }}" />
    @else
        {{-- Encode the six-digit public ID as a stable 4-by-5 pattern for uncategorized policies. --}}
        <rect x="3" y="2" width="18" height="20" rx="2" />
        @php($id = (int) ($policy->public_id ?? $policy->getKey()))
        @for($bit = 0; $bit < 20; $bit++)
            @if(($id >> $bit) & 1)
                <circle cx="{{ 6.75 + ($bit % 4) * 3.5 }}" cy="{{ 5.5 + intdiv($bit, 4) * 3.25 }}" r=".8" fill="currentColor" stroke="none" />
            @endif
        @endfor
    @endif
</svg>
