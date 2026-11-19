@extends('layouts.workspace')
@section('heading', 'Issued Certificates')
@section('content')
    <section class="workspace-panel space-y-4 p-5">
        <h2 class="font-extrabold">Issue a certificate</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">A learner must complete every lesson and pass the course’s published exams. Choose an assigned template for the enrollment.</p>
        <form method="get" class="flex flex-wrap items-end gap-2">
            <label class="builder-field">Enrollment ID<input name="enrollment" type="number" min="1" value="{{ request('enrollment') }}"></label>
            <button class="rounded-lg border px-4 py-2.5 text-sm font-semibold">Find enrollment</button>
            @if(request()->filled('enrollment'))<a class="px-2 py-2 text-sm" href="{{ route('admin.certificates.index') }}">Reset</a>@endif
        </form>
        @forelse($enrollments as $enrollment)
            <form method="post" action="{{ route('admin.certificates.issue',$enrollment) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-300 p-4 dark:border-slate-700">
                @csrf
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $enrollment->student->name }}</p><p class="text-sm">{{ $enrollment->course->title }} · #{{ $enrollment->id }}</p></div>
                <label class="builder-field">Template
                    <select name="template_id" required class="rounded-lg border px-3 py-2">
                        @foreach($enrollment->course->certificateTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                    </select>
                </label>
                <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Issue certificate</button>
            </form>
        @empty
            <p class="text-sm">No approved enrollments with an assigned template.</p>
        @endforelse
        {{ $enrollments->links() }}
    </section>
    <section class="workspace-panel mt-5 space-y-4 p-5">
        <h2 class="font-extrabold">Issuance history</h2>
        @forelse($certificates as $certificate)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-300 p-4 dark:border-slate-700">
                <div class="min-w-0 flex-1"><p class="font-semibold">{{ $certificate->snapshot['learner'] }} · {{ $certificate->snapshot['assessment'] ?? $certificate->snapshot['course'] }}</p><p class="text-sm">{{ $certificate->snapshot['type'] }} · {{ $certificate->revoked_at ? 'Revoked' : 'Issued' }} · {{ $certificate->created_at->toDateString() }}</p></div>
                <a class="rounded-lg border px-3 py-2 text-sm font-semibold" href="{{ route('certificates.show',$certificate->code) }}">View certificate</a>
                @unless($certificate->revoked_at)<form method="post" action="{{ route('admin.certificates.revoke',$certificate) }}">@csrf @method('PATCH')<button class="rounded-lg border px-3 py-2 text-sm font-semibold">Revoke</button></form>@endunless
            </div>
        @empty
            <p class="text-sm">No certificates have been issued.</p>
        @endforelse
        {{ $certificates->links() }}
    </section>
@endsection
