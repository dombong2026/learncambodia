@extends('layouts.workspace')
@section('title', 'My Library')
@section('heading', 'My Library')
@push('workspace_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush
@section('heading_action')
    <a class="lc-lib-button lc-lib-button--primary" href="{{ route('learning.library') }}">Browse library</a>
@endsection
@section('content')
<div class="lc-library lc-library--workspace lc-lib-form">
    <section class="lc-lib-admin-panel">
        <h2>My loans</h2>
        @if($loans->isNotEmpty())
            <div class="lc-lib-table-wrap">
                <table class="lc-lib-table">
                    <thead><tr><th scope="col">Item</th><th scope="col">Due</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                        @foreach($loans as $loan)
                            @php $active = !$loan->returned_at && $loan->due_at->isFuture(); @endphp
                            <tr>
                                <td>{{ $loan->item->title }}</td>
                                <td><time datetime="{{ $loan->due_at->toIso8601String() }}">{{ $loan->due_at->format('j M Y, H:i T') }}</time></td>
                                <td>{{ $loan->returned_at ? 'Returned' : ($active ? 'On loan' : 'Expired') }}</td>
                                <td>
                                    @if($active)
                                        <div class="lc-lib-actions">
                                            @if($loan->item->status === 'published')
                                                <a class="lc-lib-button" href="{{ route('library.show', $loan->item) }}">Open item</a>
                                            @endif
                                            <form method="post" action="{{ route('account.library.return', $loan) }}">@csrf @method('PATCH')
                                                <button class="lc-lib-button" type="submit">Return item</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="lc-lib-muted">Loan closed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-student-library-pagination :items="$loans" label="My loans pages"/>
        @else
            <p class="lc-lib-empty">You have no loans yet.</p>
        @endif
    </section>
    <section class="lc-lib-admin-panel">
        <h2>Saved items</h2>
        @if($saved->isNotEmpty())
            <div class="lc-lib-grid">
                @foreach($saved as $item)
                    <x-student-library-card :item="$item"/>
                @endforeach
            </div>
            <x-student-library-pagination :items="$saved" label="Saved item pages"/>
        @else
            <p class="lc-lib-empty">Save an item in the Library to find it here.</p>
        @endif
    </section>
</div>
@endsection
