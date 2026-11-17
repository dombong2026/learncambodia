@extends('layouts.workspace')
@section('heading', 'Certificate Builder')
@section('content')
    @if($errors->any())
        <ul role="alert" class="mb-4 list-disc pl-5 text-red-600 dark:text-red-300">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <div class="grid items-start gap-5 xl:grid-cols-2">
        <form data-certificate-builder method="post" action="{{ $template->exists ? route('admin.certificate-templates.update',$template) : route('admin.certificate-templates.store') }}" class="workspace-panel space-y-4 p-5">
            @csrf @if($template->exists)@method('PATCH')@endif
            <label class="builder-field">Template name<input name="name" required maxlength="100" value="{{ old('name', $template->name) }}"></label>
            <label class="builder-field">Certificate type
                <select name="certificate_type_id" required class="rounded-lg border px-3 py-2">
                    <option value="">Choose a certificate type</option>
                    @foreach($types as $type)<option value="{{ $type->id }}" @selected((int)old('certificate_type_id',$template->certificate_type_id) === $type->id)>{{ $type->name }}</option>@endforeach
                </select>
                <a class="text-blue-700 dark:text-blue-300" href="{{ route('admin.certificate-types.index') }}">Manage certificate types</a>
            </label>
            <label class="builder-field">Heading<input name="heading" required maxlength="140" value="{{ old('heading', $template->heading ?? 'Certificate of Completion') }}"></label>
            <label class="builder-field">Certificate text<textarea name="body" required maxlength="700" rows="4">{{ old('body', $template->body ?? 'Awarded to {learner} for completing {course}.') }}</textarea></label>
            <p class="text-xs text-slate-500 dark:text-slate-400">Use {learner} and {course} to insert the recipient and course names.</p>
            <label class="builder-field">Issuer<input name="issuer" required maxlength="140" value="{{ old('issuer',$template->issuer ?? 'LearnCambodia') }}"></label>
            <div class="grid items-end gap-4 sm:grid-cols-2">
                <label class="builder-field">Accent<input name="accent" type="color" value="{{ old('accent',$template->accent ?? '#986310') }}"></label>
                <label class="builder-field">Layout
                    <select name="orientation" class="rounded-lg border px-3 py-2">
                        @foreach(['landscape','portrait'] as $orientation)<option @selected(old('orientation',$template->orientation) === $orientation) value="{{ $orientation }}">{{ ucfirst($orientation) }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="builder-field">Assigned courses
                <select name="courses[]" multiple size="6" class="rounded-lg border px-3 py-2">
                    @foreach($courses as $course)<option value="{{ $course->id }}" @selected(in_array($course->id, array_map('intval', (array) old('courses', $assigned)), true))>{{ $course->title }}</option>@endforeach
                </select>
            </label>
            <button class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Save template</button>
        </form>
        <section aria-labelledby="certificate-preview-title" class="workspace-panel p-5">
            <h2 id="certificate-preview-title" class="font-extrabold">Preview</h2>
            <div data-certificate-preview class="certificate-sheet mx-auto mt-4 p-6 text-center">
                <p class="text-2xl font-bold" data-certificate-heading>Certificate of Completion</p>
                <p class="mt-5 text-xl font-semibold">Sample Learner</p>
                <p class="mt-4 whitespace-pre-line" data-certificate-body></p>
                <p class="mt-5 text-sm font-semibold" data-certificate-issuer></p>
            </div>
        </section>
    </div>
@endsection
