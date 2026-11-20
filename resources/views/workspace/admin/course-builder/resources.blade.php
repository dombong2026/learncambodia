@extends('layouts.workspace')
@section('title', 'Course Resources · '.$course->title)
@section('workspace_compact', '1')
@section('content')
<div class="course-builder space-y-4">
    <header class="builder-header">
        <a class="builder-back" href="{{ route('admin.course-builder.edit', $course) }}">← Course Builder</a>
        <h1 class="mt-3 break-words text-2xl font-extrabold">Course Resources</h1>
        <p class="mt-2 text-sm">{{ $course->title }} · Edition {{ $course->revision_number }}</p>
    </header>

    @if($errors->any())
        <ul role="alert" class="builder-errors">
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <section class="builder-panel" aria-labelledby="upload-course-resource">
        <header class="builder-panel-heading">
            <span class="builder-panel-icon"><x-workspace-icon name="paperclip" class="h-5 w-5" /></span>
            <div>
                <h2 class="text-xl font-extrabold" id="upload-course-resource">Upload a resource</h2>
                <p class="mt-1 text-sm">Enrolled learners can download these files from the course overview and lesson Resources.</p>
            </div>
        </header>
        <form class="builder-panel-content space-y-4" method="POST" action="{{ route('admin.course-builder.resources.store', $course) }}" enctype="multipart/form-data">
            @csrf
            <label class="builder-field">Resource title
                <input name="title" maxlength="180" required>
            </label>
            <label class="builder-field">File
                <input type="file" name="file" required accept=".pdf,.txt,.doc,.docx,.xls,.xlsx,.mp3,.ogg,.wav,.png,.jpg,.jpeg">
            </label>
            <button class="builder-button builder-primary" type="submit">Upload resource</button>
        </form>
    </section>

    <section class="builder-panel" aria-labelledby="course-resource-list">
        <header class="builder-panel-heading">
            <div>
                <h2 class="text-xl font-extrabold" id="course-resource-list">Saved resources</h2>
                <p class="mt-1 text-sm">Lower position numbers appear first.</p>
            </div>
        </header>
        <div class="builder-panel-content space-y-4">
            @forelse($resources as $resource)
                <article class="builder-editor">
                    <a class="builder-back break-all" href="{{ route('admin.course-builder.resources.download', [$course, $resource]) }}">{{ $resource->media->original_name }}</a>
                    <form class="mt-3 space-y-3" method="POST" action="{{ route('admin.course-builder.resources.update', [$course, $resource]) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="builder-field">Resource title<input name="title" maxlength="180" required value="{{ $resource->title }}"></label>
                            <label class="builder-field">Position<input name="position" type="number" min="1" max="999999" required value="{{ $resource->position }}"></label>
                            <label class="builder-field">Replace file<input name="file" type="file" accept=".pdf,.txt,.doc,.docx,.xls,.xlsx,.mp3,.ogg,.wav,.png,.jpg,.jpeg"></label>
                        </div>
                        <button class="builder-button builder-primary" type="submit">Save resource</button>
                    </form>
                    <form class="mt-2" method="POST" action="{{ route('admin.course-builder.resources.destroy', [$course, $resource]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="builder-button" type="submit" aria-label="Remove {{ $resource->title }}">Remove resource</button>
                    </form>
                </article>
            @empty
                <p class="builder-empty">No course resources have been uploaded yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
