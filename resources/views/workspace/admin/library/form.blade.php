@extends('layouts.workspace')
@section('title', $item->exists ? 'Edit library item' : 'Upload library item')
@section('heading', $item->exists ? 'Edit library item' : 'Upload library item')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush
@section('heading_action')
    <a class="lc-lib-button" href="{{ route('admin.library.index') }}">Library Manager</a>
@endsection
@section('content')
<div class="lc-library lc-library--workspace">
    @if($errors->any())
        <div class="lc-lib-message lc-lib-error" role="alert">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <form class="lc-lib-form" action="{{ $item->exists ? route('admin.library.update', $item) : route('admin.library.store') }}" method="post" enctype="multipart/form-data" data-library-form>
        @csrf
        @if($item->exists) @method('PATCH') @endif

        <section class="lc-lib-admin-panel">
            <h2>Item details</h2>
            <div class="lc-lib-fields">
                <label class="lc-lib-wide">Title<input name="title" value="{{ old('title', $item->title) }}" maxlength="180" required></label>
                <label>Author or organization<input name="author" value="{{ old('author', $item->author) }}" maxlength="150"></label>
                <label>Topic<input name="topic" value="{{ old('topic', $item->topic) }}" maxlength="100"></label>
                <label>Material type
                    <select name="kind" required>
                        @foreach(\App\Models\LibraryItem::KINDS as $value => $label)
                            <option value="{{ $value }}" @selected(old('kind', $item->kind ?? 'resource') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="lc-lib-wide">Description<textarea name="description" rows="5" maxlength="5000">{{ old('description', $item->description) }}</textarea></label>
            </div>
        </section>

        <section class="lc-lib-admin-panel">
            <h2>Files</h2>
            <div class="lc-lib-fields">
                <label>{{ $item->exists ? 'Replace file' : 'Library file' }}
                    <input type="file" name="file" accept=".pdf,.txt,.docx,.xlsx,.pptx,.epub" @required(!$item->exists)>
                    <small>PDF, TXT, DOCX, XLSX, PPTX, or EPUB. Maximum 40 MB.</small>
                </label>
                <label>{{ $item->cover_id ? 'Replace cover' : 'Cover image' }}
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <small>JPEG, PNG, or WebP. Maximum 5 MB.</small>
                </label>
                @if($item->exists)
                    <div class="lc-lib-actions">
                        @if($item->readable())<a href="{{ route('admin.library.preview', $item) }}" class="lc-lib-button">Preview reader</a>@endif
                        <a href="{{ route('admin.library.file', $item) }}" class="lc-lib-button">Download current file</a>
                    </div>
                    @if($item->cover_id)
                        <label class="lc-lib-check"><input name="remove_cover" type="checkbox" value="1" @checked(old('remove_cover'))> Remove existing cover</label>
                    @endif
                @endif
            </div>
        </section>

        <section class="lc-lib-admin-panel">
            <h2>Publication and access</h2>
            <div class="lc-lib-fields">
                <label>Publication status
                    <select name="status" required>
                        @foreach(\App\Models\LibraryItem::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $item->status ?? 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Access
                    <select name="access" data-library-access required>
                        @foreach(\App\Models\LibraryItem::ACCESS as $value => $label)
                            <option value="{{ $value }}" @selected(old('access', $item->access ?? 'students') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-loan-option>Loan duration (days)<input type="number" name="loan_days" min="1" max="90" value="{{ old('loan_days', $item->loan_days ?? 14) }}"></label>
                <label data-loan-option>Digital copies<input type="number" name="copies" min="1" max="1000" value="{{ old('copies', $item->copies ?? 1) }}"></label>
                <label class="lc-lib-wide lc-lib-check" data-download-option><input name="allow_download" type="checkbox" value="1" @checked(old('allow_download', $item->allow_download))> Allow downloads</label>
                <p class="lc-lib-wide lc-lib-muted">PDF and TXT support online reading. Loans use the online reader. Office files and EPUB require downloads.</p>
            </div>
        </section>
        <div class="lc-lib-actions">
            <button class="lc-lib-button lc-lib-button--primary" type="submit">Save library item</button>
            @if($item->status === 'published')<a class="lc-lib-button" href="{{ route('library.show', $item) }}">View published item</a>@endif
        </div>
    </form>
</div>
<script type="module" src="{{ asset('js/student-library-admin.js?v=1') }}"></script>
@endsection
