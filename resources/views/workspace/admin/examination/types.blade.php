@extends('layouts.workspace')
@php
    $heading = match($kind) {
        'exam-types' => 'Exam Types',
        'quiz-types' => 'Quiz Types',
        default => 'Certificate Types',
    };
@endphp
@section('title', $heading)
@section('heading', $heading)
@section('heading_action')
    <a class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white" href="#add-type">Add type</a>
@endsection
@section('content')
    @if($errors->any())<p role="alert" class="mb-4 text-red-600">{{ $errors->first() }}</p>@endif
    <div class="grid items-start gap-4 lg:grid-cols-[22rem_1fr]">
        <form id="add-type" action="{{ route('admin.'.$kind.'.store') }}" method="post" class="workspace-panel space-y-4 p-5">
            @csrf
            <h2 class="font-extrabold">Add type</h2>
            <label class="builder-field">Name<input name="name" required maxlength="100"></label>
            <label class="builder-field">Description<textarea name="description" maxlength="500" rows="4"></textarea></label>
            <button class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Add type</button>
        </form>
        <div class="space-y-3">
            @forelse($types as $type)
                <details class="workspace-panel p-5">
                    <summary class="font-semibold">{{ $type->name }}</summary>
                    <form action="{{ route('admin.'.$kind.'.update', $type) }}" method="post" class="mt-4 space-y-4">
                        @csrf @method('PATCH')
                        <label class="builder-field">Name<input name="name" value="{{ $type->name }}" @readonly($kind === 'certificate-types' && $type->kind) required maxlength="100"></label>
                        <label class="builder-field">Description<textarea name="description" maxlength="500" rows="3">{{ $type->description }}</textarea></label>
                        <button class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold">Save type</button>
                    </form>
                </details>
            @empty
                <p class="workspace-panel p-5">No types yet.</p>
            @endforelse
            {{ $types->links() }}
        </div>
    </div>
@endsection
