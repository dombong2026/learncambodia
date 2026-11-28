@extends('layouts.workspace')
@section('title', 'Library Loan Register')
@section('heading', 'Library Loan Register')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush
@section('heading_action')
    <a class="lc-lib-button" href="{{ route('admin.library.index') }}">Library Manager</a>
@endsection
@section('content')
<div class="lc-library lc-library--workspace">
    <section class="lc-lib-admin-panel">
        <h2>Loans</h2>
        @if($loans->isNotEmpty())
            <div class="lc-lib-table-wrap">
                <table class="lc-lib-table">
                    <thead><tr><th scope="col">Item</th><th scope="col">Student</th><th scope="col">Due</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach($loans as $loan)
                            <tr>
                                <td><a href="{{ route('admin.library.edit', $loan->item) }}">{{ $loan->item->title }}</a></td>
                                <td>{{ $loan->user->name }}</td>
                                <td><time datetime="{{ $loan->due_at->toIso8601String() }}">{{ $loan->due_at->format('j M Y, H:i T') }}</time></td>
                                <td>{{ $loan->returned_at ? 'Returned' : ($loan->due_at->isFuture() ? 'On loan' : 'Expired') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-student-library-pagination :items="$loans" label="Loan register pages"/>
        @else
            <p class="lc-lib-empty">No loans have been recorded.</p>
        @endif
    </section>
</div>
@endsection
