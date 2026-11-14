<footer class="border-t border-slate-800 bg-slate-950 text-slate-200">
    <div data-footer-columns class="mx-auto grid max-w-7xl grid-cols-2 gap-x-5 gap-y-10 px-4 py-12 sm:gap-x-8 sm:px-6 md:grid-cols-4 lg:gap-x-10 lg:px-8">
        <div><h2 class="mb-5 text-base font-bold text-white"><x-brand-logo surface="dark" height="h-8" imageClass="max-w-[195px]" /></h2><ul class="space-y-3 text-sm text-slate-400">
            <li><a class="hover:text-white" href="{{ route('information.show','about') }}">About Us <span lang="km"> (អំពីយើង)</span></a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','instructors') }}">Our Instructors</a></li>
            <li><a class="hover:text-white" href="{{ route('articles.index') }}">{{ __('frontend.articles') }}</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','careers') }}">Careers / Teach With Us</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','partners') }}">Partners &amp; Schools</a></li>
        </ul></div>
        <div><h2 class="mb-5 text-base font-bold text-white">English Courses</h2><ul class="space-y-3 text-sm text-slate-400">
            <li><a class="hover:text-white" href="{{ route('cefr.index') }}#A1">Beginner (A1–A2)</a></li>
            <li><a class="hover:text-white" href="{{ route('cefr.index') }}#B1">Intermediate (B1–B2)</a></li>
            <li><a class="hover:text-white" href="{{ route('cefr.index') }}#C1">Advanced (C1–C2)</a></li>
            <li><a class="hover:text-white" href="{{ route('categories.index',['q'=>'Business English']) }}">Business English</a></li>
            <li><a class="hover:text-white" href="{{ route('categories.index',['q'=>'EAP']) }}">Academic English (EAP)</a></li>
        </ul></div>
        <div><h2 class="mb-5 text-base font-bold text-white">Exams &amp; Teaching</h2><ul class="space-y-3 text-sm text-slate-400">
            <li><a class="hover:text-white" href="{{ route('categories.index',['q'=>'IELTS']) }}">IELTS Preparation</a></li>
            <li><a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'TOEFL']) }}">TOEFL</a> <span aria-hidden="true">&amp;</span> <a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'TOEIC']) }}">TOEIC</a></li>
            <li><a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'PTE']) }}">PTE</a> <span aria-hidden="true">&amp;</span> <a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'DET']) }}">Duolingo (DET)</a></li>
            <li><a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'TEFL']) }}">TEFL</a> <span aria-hidden="true">&amp;</span> <a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'TESOL']) }}">TESOL</a></li>
            <li><a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'CELTA']) }}">CELTA</a> <span aria-hidden="true">&amp;</span> <a class="hover:text-white hover:underline focus-visible:rounded-sm" href="{{ route('categories.index',['q'=>'TKT']) }}">TKT</a></li>
        </ul></div>
        <div><h2 class="mb-5 text-base font-bold text-white">Student Support</h2><ul class="space-y-3 text-sm text-slate-400">
            <li><a class="hover:text-white" href="{{ route('information.show','placement') }}">Placement Test Information</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','scores') }}">Exam Score Guide</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','faq') }}">FAQs</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','contact') }}">Contact Us</a></li>
            <li><a class="hover:text-white" href="{{ route('information.show','privacy') }}">Privacy</a> <span aria-hidden="true">&amp;</span> <a class="hover:text-white" href="{{ route('information.show','terms') }}">Terms</a></li>
        </ul></div>
    </div>
    @php
        // Real social sharing actions, not unverified brand profiles. Share the
        // public homepage only, never a visitor's URL with private query data.
        $shareHome = rawurlencode(route('home'));
        $shareText = rawurlencode('LearnCambodia — '.route('home'));
    @endphp
    <div data-footer-bottom class="mx-auto flex max-w-7xl flex-col gap-5 border-t border-slate-800 px-4 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p class="leading-6">© {{ date('Y') }} Learn Cambodia. All Rights Reserved.</p>
        <nav data-footer-social aria-label="Share LearnCambodia" class="flex shrink-0 items-center gap-2">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareHome }}" target="_blank" rel="noopener noreferrer nofollow" aria-label="Share LearnCambodia on Facebook" title="Share on Facebook" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-white/5 text-slate-300 transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-white/10 hover:text-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true" focusable="false"><path d="M13.75 21v-7.8h2.7l.4-3.25h-3.1V7.9c0-.93.25-1.56 1.58-1.56H17V3.43A21 21 0 0 0 14.54 3c-2.45 0-4.13 1.5-4.13 4.27v2.68H7.7v3.25h2.71V21z"/></svg>
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ $shareHome }}" target="_blank" rel="noopener noreferrer nofollow" aria-label="Share LearnCambodia on X" title="Share on X" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-white/5 text-slate-300 transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-white/10 hover:text-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m4 3 16 18M20 3 4 21M4 3h5M15 21h5"/></svg>
            </a>
            <a href="https://t.me/share/url?url={{ $shareHome }}&amp;text=LearnCambodia" target="_blank" rel="noopener noreferrer nofollow" aria-label="Share LearnCambodia on Telegram" title="Share on Telegram" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-white/5 text-slate-300 transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-white/10 hover:text-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M22 3 2 11l7.3 2.5L12 21l4-5.4L22 3Z"/><path d="M9.3 13.5 22 3"/></svg>
            </a>
            <a href="https://api.whatsapp.com/send?text={{ $shareText }}" target="_blank" rel="noopener noreferrer nofollow" aria-label="Share LearnCambodia on WhatsApp" title="Share on WhatsApp" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-white/5 text-slate-300 transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-white/10 hover:text-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.4 11.6a8.4 8.4 0 0 1-12.2 7.5L3 21l1.8-5.1a8.4 8.4 0 1 1 15.6-4.3Z"/><path d="M8.8 8c-.3.2-.9 1-.7 1.9.4 2.1 3.6 5 5.7 5.4.8.2 1.7-.3 2-.7l-2-1.2-.9.9a8.9 8.9 0 0 1-3.1-3l.8-.8L8.8 8Z"/></svg>
            </a>
        </nav>
    </div>
</footer>
