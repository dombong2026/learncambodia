@extends('layouts.public')
@section('title',$title)
@section('meta_description',$lede)
@section('content')
@php $appearance=\App\Support\Appearance::approved('contact'); @endphp
<section class="overflow-hidden bg-gradient-to-br from-[#0b294f] via-[#174372] to-[#0c2c56] text-white">
    <div class="mx-auto grid max-w-[1600px] items-center gap-8 px-4 py-14 sm:px-8 lg:grid-cols-[1.25fr_.75fr] lg:px-12 lg:py-20">
        <div>
            <nav aria-label="Breadcrumb" class="mb-10 flex gap-2 text-xs text-[#d0e0f0]"><a href="{{ route('home') }}" class="hover:underline">{{ __('longpage.home') }}</a><span>/</span><span aria-current="page" class="font-bold text-[#f1c776]">{{ __('longpage.contact') }}</span></nav>
            <p class="text-xs font-extrabold uppercase tracking-widest text-[#f0c36d]">{{ __('longpage.contact_eyebrow') }}</p>
            <h1 class="mt-3 text-4xl font-black leading-tight sm:text-5xl xl:text-6xl">{{ $appearance['heading'] }}</h1>
            <p class="mt-5 max-w-3xl text-base leading-9 text-[#dbe7f4]">{{ $appearance['intro'] }}</p>
            <a href="#contact-form" class="mt-7 inline-block rounded-xl bg-[#efc16a] px-6 py-3 text-sm font-black text-[#11325e] hover:bg-[#ffda93]">{{ __('longpage.send_an_inquiry') }}</a>
        </div>
        <div class="rounded-3xl border border-white/15 bg-white/10 p-7">
            <x-workspace-icon name="headphones" class="h-14 w-14 text-[#f1c575]"/>
            <h2 class="mt-5 text-xl font-black">{{ __('longpage.contact_private_support') }}</h2>
            <p class="mt-3 text-sm leading-8 text-[#d4e3f2]">{{ __('longpage.contact_support_intro') }}</p>
            <p class="mt-6 rounded-xl border border-white/20 p-4 text-xs leading-7 text-[#f5dda6]">{{ __('longpage.contact_no_unverified_details') }}</p>
        </div>
    </div>
</section>

<section class="bg-white py-16 dark:bg-[#0d2646]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <p class="text-xs font-extrabold uppercase tracking-widest text-[#b07b27]">{{ __('longpage.contact_channels_label') }}</p>
        <h2 class="mt-2 text-3xl font-black text-[#10315b] dark:text-white">{{ $appearance['channels_heading'] }}</h2>
        <p class="mt-4 max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.contact_channels_intro') }}</p>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['book-open','longpage.contact_course_title','longpage.contact_course_desc',route('courses.index')],
                ['user-check','longpage.contact_enroll_title','longpage.contact_enroll_desc',route('login')],
                ['life-buoy','longpage.contact_help_title','longpage.contact_help_desc',route('information.show','faq')],
            ] as [$icon,$key,$note,$url])
                <a href="{{ $url }}" class="group rounded-3xl border border-slate-200 bg-[#f7f9fc] p-7 transition hover:border-[#d7aa59] hover:shadow-lg dark:border-slate-700 dark:bg-[#173657]">
                    <span class="grid h-14 w-14 place-items-center rounded-xl bg-[#f6e7c4] text-[#ac7626] dark:bg-[#423a27]"><x-workspace-icon :name="$icon" class="h-8 w-8"/></span>
                    <h3 class="mt-5 text-xl font-black text-[#10355f] dark:text-white">{{ __($key) }}</h3>
                    <p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($note) }}</p>
                    <span class="mt-5 inline-block text-xs font-black text-[#ac7626]">{{ __('longpage.explore') }} →</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section id="contact-form" class="scroll-mt-28 bg-[#f1f4f9] py-16 dark:bg-[#112c4b]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <p class="text-xs font-extrabold uppercase tracking-widest text-[#ae7b28]">{{ __('longpage.contact_inquiry_label') }}</p>
        <h2 class="mt-2 text-3xl font-black text-[#10335d] dark:text-white">{{ $appearance['form_heading'] }}</h2>
        <p class="mt-3 max-w-4xl text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __('longpage.contact_form_intro') }}</p>
        @if(session('contact_status'))<div role="status" class="mt-7 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm font-bold text-emerald-900 dark:border-emerald-700 dark:bg-[#164834] dark:text-emerald-200">{{ session('contact_status') }}</div>@endif
        @if($errors->any())<div role="alert" class="mt-7 rounded-xl border border-red-300 bg-red-50 p-5 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"><strong>{{ __('longpage.contact_errors') }}</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('contact.store') }}" class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-lg sm:p-9 dark:border-slate-700 dark:bg-[#153352]">@csrf
            <div class="hidden" aria-hidden="true"><label for="company_website">Leave this field empty</label><input tabindex="-1" autocomplete="off" id="company_website" name="company_website" value=""></div>
            <div class="grid gap-6 md:grid-cols-2">
                <div><label for="contact-name" class="mb-2 block text-sm font-bold">{{ __('longpage.contact_full_name') }}</label><input id="contact-name" name="name" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('name') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3.5 text-sm outline-none focus:border-[#c09a45] dark:border-slate-700 dark:bg-[#0a2645]"></div>
                <div><label for="contact-email" class="mb-2 block text-sm font-bold">{{ __('longpage.contact_email') }}</label><input id="contact-email" name="email" required type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3.5 text-sm outline-none focus:border-[#c09a45] dark:border-slate-700 dark:bg-[#0a2645]"></div>
                <div><label for="contact-topic" class="mb-2 block text-sm font-bold">{{ __('longpage.contact_topic') }}</label><select id="contact-topic" name="topic" required class="w-full rounded-xl border border-slate-300 bg-white p-3.5 text-sm dark:border-slate-700 dark:bg-[#0a2645]">
                    <option value="">{{ __('longpage.contact_choose_topic') }}</option>
                    @foreach(['courses','enrollment','technical','general'] as $option)<option value="{{ $option }}" @selected(old('topic')===$option)>{{ __('longpage.contact_topic_'.$option) }}</option>@endforeach
                </select></div>
                <div><label for="contact-subject" class="mb-2 block text-sm font-bold">{{ __('longpage.contact_subject') }}</label><input id="contact-subject" name="subject" required minlength="3" maxlength="160" value="{{ old('subject') }}" class="w-full rounded-xl border border-slate-300 bg-white p-3.5 text-sm dark:border-slate-700 dark:bg-[#0a2645]"></div>
            </div>
            <div class="mt-6"><label for="contact-message" class="mb-2 block text-sm font-bold">{{ __('longpage.contact_message') }}</label><textarea id="contact-message" name="message" rows="7" minlength="10" maxlength="4000" required class="w-full rounded-xl border border-slate-300 bg-white p-4 text-sm leading-8 dark:border-slate-700 dark:bg-[#0a2645]">{{ old('message') }}</textarea></div>
            <label class="mt-5 flex items-start gap-3 rounded-2xl border border-[#f0d6a3] bg-[#fffaef] p-5 text-xs leading-7 text-[#654c22] dark:border-[#665537] dark:bg-[#3b3325] dark:text-[#f9dba4]"><input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-1.5"><span>{{ __('longpage.contact_consent') }} <a href="{{ route('information.show','privacy') }}" target="_blank" rel="noopener" class="font-black underline">{{ __('longpage.privacy_draft') }}</a>.</span></label>
            <div class="mt-6 flex justify-end"><button type="submit" class="rounded-xl bg-[#123765] px-8 py-3.5 text-sm font-black text-white hover:bg-[#235083]">{{ __('longpage.send_an_inquiry') }}</button></div>
        </form>
        <p class="mt-5 max-w-5xl text-xs leading-8 text-slate-500 dark:text-slate-300">{{ $appearance['privacy_note'] }}</p>
    </div>
</section>

<section class="bg-white py-16 dark:bg-[#0e2b4a]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#11345f] dark:text-white">{{ __('longpage.contact_process') }}</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach([
                ['send','longpage.contact_step_send','longpage.contact_step_send_note'],
                ['shield-check','longpage.contact_step_review','longpage.contact_step_review_note'],
                ['clipboard-check','longpage.contact_step_track','longpage.contact_step_track_note'],
            ] as [$icon,$heading,$description])
                <div class="rounded-3xl border border-slate-200 bg-[#f7f9fc] p-7 dark:border-slate-700 dark:bg-[#183657]">
                    <x-workspace-icon :name="$icon" class="h-9 w-9 text-[#aa7629]"/>
                    <h3 class="mt-5 text-lg font-black">{{ __($heading) }}</h3><p class="mt-3 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($description) }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="border-t border-slate-200 bg-[#f1f4f9] py-16 dark:border-slate-800 dark:bg-[#102e4e]">
    <div class="mx-auto max-w-[1600px] px-4 sm:px-8 lg:px-12">
        <h2 class="text-3xl font-black text-[#10345f] dark:text-white">{{ $appearance['faq_heading'] }}</h2>
        <div class="mt-7 space-y-3">
            @foreach([['longpage.contact_faq_one','longpage.contact_faq_one_answer'],['longpage.contact_faq_two','longpage.contact_faq_two_answer'],['longpage.contact_faq_three','longpage.contact_faq_three_answer']] as [$q,$a])
                <details class="group rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-[#163456]">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-bold text-[#10335d] dark:text-white">{{ __($q) }} <x-workspace-icon name="chevron-down" class="h-5 w-5 shrink-0 transition group-open:rotate-180"/></summary>
                    <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-300">{{ __($a) }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-gradient-to-r from-[#0a2952] to-[#184675] py-14 text-white">
    <div class="mx-auto flex max-w-[1600px] flex-col justify-between gap-6 px-4 sm:px-8 lg:flex-row lg:items-center lg:px-12">
        <div><h2 class="text-3xl font-black">{{ __('longpage.contact_cta_title') }}</h2><p class="mt-3 max-w-3xl text-sm leading-8 text-[#d7e6f5]">{{ __('longpage.contact_cta_note') }}</p></div>
        <a href="{{ route('courses.index') }}" class="self-start rounded-xl bg-[#f1c36b] px-7 py-3.5 text-sm font-black text-[#0d315b] hover:bg-[#ffde96]">{{ __('longpage.browse_course_catalog') }}</a>
    </div>
</section>
@endsection
