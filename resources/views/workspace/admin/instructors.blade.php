@extends('layouts.workspace')
@section('title', 'Instructor Management')
@section('heading', 'Instructor Management')
@section('description', 'Assign operator-approved instructors to reviewed courses. Staff role provisioning requires an audited server operator; public registration cannot create instructor accounts.')
@section('content')
<div class="grid gap-5 xl:grid-cols-[1fr_1.15fr]">
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Assign teaching access</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">{{ $instructorCount }} instructor accounts provisioned. Teaching access is limited to the course selected below.</p>
        <form method="POST" action="{{ route('admin.instructors.assign') }}" class="mt-6 space-y-5">@csrf
            <div><label for="teacher" class="mb-2 block text-sm font-bold">Instructor</label>
                <select name="instructor_id" id="teacher" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Choose an instructor</option>
                    @foreach($instructors as $teacher)<option value="{{ $teacher->id }}" @selected(old('instructor_id')==$teacher->id)>{{ $teacher->name }} · {{ $teacher->email }}</option>@endforeach
                </select>@error('instructor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div><label for="course" class="mb-2 block text-sm font-bold">Academically approved course</label>
                <select name="course_id" id="course" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Choose a course</option>
                    @foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id')==$course->id)>{{ $course->title }} · Edition {{ $course->revision_number }}</option>@endforeach
                </select>@error('course_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs leading-6 text-slate-500">The selectors display the first 100 instructors and 100 approved courses, ordered by name/title. Administrators can use the directory as records grow.</p>
            <div class="flex justify-end"><button type="submit" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-bold text-white hover:bg-blue-800">Approve course assignment</button></div>
        </form>
    </section>
    <section class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Instructor provisioning</h2>
        <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Instructor accounts must first be created as standard users and approved by an authorized server operator. Administrator pages cannot self-assign an instructor or administrator role.</p>
        <div class="mt-5 rounded-2xl bg-slate-50 p-5 dark:bg-slate-950">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Server operator only</p>
            <code class="mt-3 block break-all text-xs leading-7">php artisan accounts:assign-role existing-instructor@example.com instructor --operator="Approved operator" --reason="Verified teaching appointment" --confirm</code>
        </div>
        <p class="mt-5 text-xs leading-6 text-slate-500">A later role change revokes active course assignments and API tokens. No external login provider is enabled.</p>
    </section>
</div>
<div class="mt-8">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-extrabold">Course assignments</h2>
        <form method="GET" action="{{ route('admin.instructors.index') }}" role="search" class="flex gap-2"><input type="search" name="q" value="{{ request('q') }}" maxlength="80" placeholder="Search name or email" class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900"><button type="submit" class="rounded-full border border-blue-500 px-4 py-2.5 text-sm font-bold text-blue-700 dark:text-blue-400">Search</button></form>
    </div>
    <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[740px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-900"><tr><th class="px-5 py-4">Instructor</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Assigned</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($assignments as $assignment)
                <tr><td class="px-5 py-4"><p class="font-bold">{{ $assignment->instructor?->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $assignment->instructor?->email }}</p></td><td class="px-5 py-4">{{ $assignment->course?->title }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ $assignment->assigned_at?->format('Y-m-d') }}</td><td class="px-5 py-4 font-semibold">{{ ucfirst($assignment->status) }}</td>
                    <td class="px-5 py-4 text-right">@if($assignment->status==='active')<form method="POST" action="{{ route('admin.instructors.revoke',$assignment) }}">@csrf @method('PATCH')<button type="submit" class="rounded-full border border-red-300 px-4 py-2 text-xs font-bold text-red-700">Revoke</button></form>@else<span class="text-xs text-slate-500">Reassign above</span>@endif</td></tr>
            @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No instructor course assignments yet.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $assignments->links() }}</div>
</div>
@endsection
