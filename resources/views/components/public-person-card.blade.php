@props(['profile','kind'])
<article class="group flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-amber-400 hover:shadow-xl dark:border-slate-700 dark:bg-[#152f51]">
    <div class="flex items-start gap-4">
        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-full border-4 border-white bg-gradient-to-br from-[#305f94] to-[#112e5b] text-2xl font-black text-[#f9d58d] shadow-md dark:border-slate-800" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->display_name,0,1)) }}</div>
        <div class="min-w-0 flex-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#ae7b26] dark:text-[#f5c979]">{{ $kind==='instructor' ? __('people.instructor') : __('people.student') }}</span>
            <h3 class="mt-1 break-words text-lg font-black leading-snug text-[#0d2f5b] dark:text-white">{{ $profile->display_name }}</h3>
            <p class="mt-1 break-words text-xs leading-6 text-slate-600 dark:text-slate-300">{{ $profile->headline }}</p>
        </div>
    </div>
    @if($profile->subjects)<p class="text-xs leading-6 text-slate-500 dark:text-slate-300"><span class="font-bold">{{ __('people.interests') }}:</span> {{ $profile->subjects }}</p>@endif
    <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
        <span class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-400"><x-workspace-icon name="shield-check" class="mr-1 inline h-3.5 w-3.5" />{{ __('people.publicly_approved') }}</span>
        <a href="{{ $kind === 'instructor' ? route('instructors.show',$profile) : route('students.show',$profile) }}" class="rounded-xl bg-[#0e315e] px-4 py-2.5 text-xs font-black text-white transition hover:bg-[#235185] focus-visible:outline-2 focus-visible:outline-[#e8b956]">{{ __('people.view_profile') }}</a>
    </div>
</article>
