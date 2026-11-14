<footer class="border-t border-slate-800 bg-slate-950 text-slate-200">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
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
    <div class="mx-auto flex max-w-7xl flex-col gap-3 border-t border-slate-800 px-4 py-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p>© {{ date('Y') }} Learn Cambodia. Original educational content and branding reserved.</p>
        <p>{{ \App\Support\Appearance::value('footer','footer_note') }}</p>
    </div>
</footer>
