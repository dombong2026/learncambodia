@extends('layouts.workspace')
@section('title', 'Library Manager')
@section('heading', 'Library Manager')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush
@section('heading_action')
    <a class="lc-lib-button lc-lib-button--primary" href="{{ route('admin.library.create') }}">Upload resource</a>
@endsection
@section('content')
<div class="lc-library lc-library--workspace">
    <section class="lc-lib-admin-panel">
        <div class="lc-lib-head">
            <h2>Collection</h2>
            <div class="lc-lib-actions">
                <a class="lc-lib-button" href="{{ route('admin.library.loans') }}">Loan register</a>
                <a class="lc-lib-button" href="{{ route('learning.library') }}">View public Library</a>
            </div>
        </div>
        <form class="lc-lib-find lc-lib-find--admin" action="{{ route('admin.library.index') }}" method="get" role="search">
            <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
            <div class="lc-lib-find-query">
                <label for="manager-search">Search collection</label>
                <input id="manager-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Title, author, or topic">
            </div>
            <div>
                <label for="manager-status">Status</label>
                <select id="manager-status" name="status"><option value="">All statuses</option>
                    @foreach(\App\Models\LibraryItem::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="lc-lib-button" type="submit">Filter</button>
        </form>
        @if($items->isNotEmpty())
            <div class="lc-lib-table-wrap">
                <table class="lc-lib-table">
                    <thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Access</th><th scope="col">Loans</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td><strong>{{ $item->title }}</strong><small>{{ $item->format() }} · {{ \App\Models\LibraryItem::KINDS[$item->kind] }}{{ $item->author ? ' · '.$item->author : '' }}</small></td>
                                <td>{{ \App\Models\LibraryItem::STATUSES[$item->status] }}</td>
                                <td>{{ \App\Models\LibraryItem::ACCESS[$item->access] }}</td>
                                <td>{{ $item->access === 'loan' ? $item->active_loans_count.' / '.$item->copies : '—' }}</td>
                                <td><a class="lc-lib-button" href="{{ route('admin.library.edit', $item) }}">Edit<span class="sr-only"> {{ $item->title }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-student-library-pagination :items="$items"/>
        @else
            <p class="lc-lib-empty">{{ $search !== '' || $status !== '' ? 'No items match these filters.' : 'Upload a book, document, or study resource to start the collection.' }}</p>
        @endif
    </section>
</div>
@endsection
