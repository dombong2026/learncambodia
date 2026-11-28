@props(['title', 'entry' => null, 'icon' => 'book-open', 'label'])
<section class="detail-hero border-b border-slate-200 bg-gradient-to-br from-[#f8fafc] via-white to-[#edf2f9] dark:border-slate-700 dark:from-[#102b4b] dark:via-[#12365d] dark:to-[#0b284e]">
    <div class="detail-grid mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="min-w-0">
            <div class="flex items-center gap-2" data-detail-heading>
                <h1 dir="auto" class="detail-title min-w-0 flex-1 text-2xl font-black leading-tight text-[#0c2e5b] sm:text-3xl lg:text-4xl dark:text-white" title="{{ $title }}"><span>{{ $title }}</span></h1>
                <button type="button" class="detail-title-toggle shrink-0 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold dark:border-slate-500"
                    data-detail-title-toggle data-pause-label="{{ __('detail.pause_title') }}" data-play-label="{{ __('detail.play_title') }}"
                    aria-label="{{ __('detail.pause_title') }}" title="{{ __('detail.pause_title') }}" hidden>
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <path data-detail-pause d="M4 3h3v10H4zM9 3h3v10H9z"/>
                        <path data-detail-play d="m5 3 8 5-8 5z" hidden/>
                    </svg>
                </button>
            </div>

            @php
                $hasCover = $entry && match ($entry->featured_kind) {
                    'image', 'video' => (bool) $entry->featuredMedia,
                    'youtube' => preg_match('/^[A-Za-z0-9_-]{11}$/D', $entry->featured_value ?? '') === 1,
                    'color' => preg_match('/^#[a-f0-9]{6}$/D', $entry->featured_value ?? '') === 1,
                    default => false,
                };
            @endphp
            @if($hasCover)
                <div class="detail-cover mt-4">
                    <x-editorial-featured-media :entry="$entry"/>
                </div>
            @elseif($icon)
                <div class="detail-cover-placeholder mt-4 rounded-2xl px-5 text-center text-white">
                    <x-workspace-icon :name="$icon" class="h-11 w-11 text-[#f2c674] sm:h-14 sm:w-14"/>
                    <span class="text-sm font-bold">{{ $label }}</span>
                </div>
            @endif

            @isset($meta)
                <ul class="detail-meta mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-slate-700 sm:text-sm dark:text-slate-200">{{ $meta }}</ul>
            @endisset
        </div>

        <aside class="self-start rounded-3xl bg-[#0d305f] p-5 text-white shadow-sm sm:p-6">
            {{ $rail }}
        </aside>
    </div>
</section>
