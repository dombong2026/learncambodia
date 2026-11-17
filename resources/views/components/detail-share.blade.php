@props(['url', 'title', 'instructors' => []])

<div class="mt-5 space-y-4 border-t border-white/20 pt-4" data-detail-share>
    @foreach($instructors as $instructor)
        <x-instructor-social-links :profile="$instructor" />
    @endforeach
    <button type="button"
        class="grid h-10 w-10 place-items-center rounded-xl border border-white/25 text-[#ffe2ac] transition hover:border-[#efbe62] hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#efbe62]"
        aria-label="{{ __('detail.share') }}" title="{{ __('detail.share') }}"
        data-share-url="{{ $url }}" data-share-title="{{ $title }}"
        data-share-copied="{{ __('detail.share_copied') }}" data-share-fallback="{{ __('detail.share_fallback') }}">
        <x-public-social-icon name="share" />
    </button>
    <p class="text-xs text-[#d1e0ef] empty:hidden" data-share-status role="status"></p>
    <input hidden readonly type="url" value="{{ $url }}" aria-label="{{ __('detail.share_url') }}"
        class="w-full rounded-lg border border-white/25 bg-[#082242] px-3 py-2 text-xs text-white" data-share-copy>
</div>
