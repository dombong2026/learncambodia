@extends('layouts.workspace')
@section('title', 'Certificate Templates')
@section('heading', 'Certificate Templates')
@section('heading_action')
    <a class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="{{ route('admin.certificate-templates.create') }}">Create template</a>
@endsection
@section('content')
    <div class="space-y-3">
        @forelse($templates as $template)
            <article class="workspace-panel flex flex-wrap items-center justify-between gap-3 p-5">
                <div><h2 class="font-bold">{{ $template->name }}</h2><p class="text-sm">{{ $template->type->name }} · {{ $template->courses_count }} courses</p></div>
                <a class="rounded-lg border px-4 py-2 text-sm font-semibold" href="{{ route('admin.certificate-templates.edit', $template) }}">Edit template</a>
            </article>
        @empty
            <p class="workspace-panel p-6">Create a certificate type, then build a template and assign it to a course.</p>
        @endforelse
        {{ $templates->links() }}
    </div>
@endsection
