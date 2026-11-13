@extends('layouts.workspace')
@section('title', __('people.profile_reviews'))
@section('heading', __('people.profile_reviews'))
@section('description', __('people.review_intro'))
@section('content')
<div class="space-y-5">
    @forelse($profiles as $profile)
        <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-700 dark:bg-[#162e50]">
            <div class="flex flex-wrap items-start justify-between gap-2"><div>
                <p class="text-xs font-extrabold uppercase tracking-widest text-[#b48429]">{{ ucfirst($profile->kind) }} · #{{ $profile->id }}</p>
                <h2 class="mt-2 text-xl font-black">{{ $profile->display_name }}</h2>
                <p class="mt-2 text-sm font-semibold">{{ $profile->headline }}</p>
            </div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">{{ __('people.review_pending') }}</span></div>
            <p class="mt-4 text-xs text-slate-500">{{ $profile->subjects }} @if($profile->city) · {{ $profile->city }} @endif</p>
            <p class="mt-4 max-h-48 overflow-y-auto whitespace-pre-line break-words rounded-xl bg-slate-50 p-4 text-sm leading-7 text-slate-600 dark:bg-[#0c2442] dark:text-slate-300">{{ $profile->bio }}</p>
            @if($profile->kind==='student')
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-[#463924] dark:text-[#f9d89c]">{{ __('people.adult_review_warning') }}</p>
            @endif
            @if($profile->user_id===auth()->id())
                <p class="mt-5 text-xs font-bold text-amber-700">{{ __('people.independent_reviewer') }}</p>
            @else
                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <form action="{{ route('admin.public-profiles.review',$profile) }}" method="POST">@csrf
                        <input type="hidden" name="decision" value="approve"><button type="submit" class="w-full rounded-xl bg-emerald-700 px-5 py-3 text-xs font-black text-white hover:bg-emerald-800">{{ __('people.approve_profile') }}</button>
                    </form>
                    <form action="{{ route('admin.public-profiles.review',$profile) }}" method="POST" class="space-y-2">@csrf
                        <input type="hidden" name="decision" value="reject">
                        <label for="note-{{ $profile->id }}" class="block text-xs font-bold">{{ __('people.review_reason') }}</label>
                        <textarea id="note-{{ $profile->id }}" name="note" required maxlength="500" rows="2" class="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs dark:border-slate-700 dark:bg-[#0d284b]"></textarea>
                        <button type="submit" class="rounded-xl border border-red-300 px-5 py-2.5 text-xs font-bold text-red-700 dark:text-red-300">{{ __('people.reject_profile') }}</button>
                    </form>
                </div>
            @endif
        </section>
    @empty
        <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#152b4b]">{{ __('people.no_reviews') }}</p>
    @endforelse
</div>
<div class="mt-6">{{ $profiles->links() }}</div>
@endsection
