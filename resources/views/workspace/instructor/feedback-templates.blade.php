@extends('layouts.workspace')
@section('title','Feedback Templates')
@section('heading','Feedback Templates')
@section('description','Private reusable feedback drafts. Each grading decision still requires instructor review.')
@section('content')
<section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">Create a feedback template</h2>
    <form method="POST" action="{{ route('instructor.feedback-templates.store') }}" class="mt-5 space-y-3">@csrf
        <label for="template-title" class="block text-xs font-bold">Template title</label><input id="template-title" name="title" required maxlength="120" class="w-full rounded-xl border p-3 dark:bg-slate-950">
        <label for="template-body" class="block text-xs font-bold">Reusable guidance</label><textarea id="template-body" name="body" rows="4" required minlength="5" maxlength="3000" class="w-full rounded-xl border p-3 dark:bg-slate-950"></textarea>
        <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save private template</button>
    </form>
</section>
<div class="mt-6 grid gap-5 lg:grid-cols-2">
@forelse($templates as $template)
    <article class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('instructor.feedback-templates.update',$template) }}" class="space-y-3">@csrf @method('PATCH')
            <label for="feedback-title-{{ $template->id }}" class="block text-xs font-bold">Title</label><input id="feedback-title-{{ $template->id }}" name="title" value="{{ $template->title }}" maxlength="120" required class="w-full rounded-xl border p-3 dark:bg-slate-950">
            <label for="feedback-body-{{ $template->id }}" class="block text-xs font-bold">Text</label><textarea id="feedback-body-{{ $template->id }}" name="body" rows="5" maxlength="3000" required class="w-full rounded-xl border p-3 text-sm leading-7 dark:bg-slate-950">{{ $template->body }}</textarea>
            <button class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white">Save changes</button>
        </form>
        <form method="POST" action="{{ route('instructor.feedback-templates.destroy',$template) }}" class="mt-3">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Delete template</button></form>
    </article>
@empty<p class="text-sm text-slate-500">No feedback templates saved.</p>@endforelse
</div><div class="mt-5">{{ $templates->links() }}</div>
@endsection
