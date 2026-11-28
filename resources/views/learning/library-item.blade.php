@extends('layouts.public')
@section('title', $item->title.' · Digital Library')
@push('public_styles')
    <link rel="stylesheet" href="{{ asset('css/student-library.css?v=1') }}">
@endpush

@section('content')
<div class="lc-library">
    <div class="lc-lib-frame lc-lib-section">
        <nav class="lc-lib-actions lc-lib-detail-nav" aria-label="Library navigation">
            <a class="lc-lib-button" href="{{ route('learning.library') }}">Back to Library</a>
            @if($student)<a class="lc-lib-button" href="{{ route('account.library.index') }}">My Library</a>@endif
        </nav>
        @if(session('status')) <p class="lc-lib-message" role="status">{{ session('status') }}</p> @endif
        @foreach($errors->all() as $message) <p class="lc-lib-message lc-lib-error" role="alert">{{ $message }}</p> @endforeach
        <article class="lc-lib-details">
            <x-student-library-cover :item="$item"/>
            <div>
                <div class="lc-lib-meta">
                    <span>{{ $item->format() }}</span>
                    <span>{{ \App\Models\LibraryItem::KINDS[$item->kind] }}</span>
                    <span>{{ \App\Models\LibraryItem::ACCESS[$item->access] }}</span>
                </div>
                <h1>{{ $item->title }}</h1>
                @if($item->author)<p class="lc-lib-by">{{ $item->author }}</p>@endif
                @if($item->topic)<p class="lc-lib-by">{{ $item->topic }}</p>@endif
                @if($item->description)<p class="lc-lib-description">{{ $item->description }}</p>@endif
                @if($loan)
                    <p class="lc-lib-message">Due <time datetime="{{ $loan->due_at->toIso8601String() }}">{{ $loan->due_at->format('j M Y, H:i T') }}</time></p>
                @elseif($item->access === 'loan')
                    <p class="lc-lib-by">{{ $available > 0 ? $available.' '.Str::plural('copy', $available).' available' : 'All copies are checked out' }} · {{ $item->loan_days }}-day loan</p>
                @endif

                <div class="lc-lib-actions">
                    @if($canAccess && $item->readable())
                        <a class="lc-lib-button lc-lib-button--primary" href="{{ route('library.reader', $item) }}">Read online</a>
                    @endif
                    @if($canAccess && $item->canDownload())
                        <a class="lc-lib-button {{ $item->readable() ? '' : 'lc-lib-button--primary' }}" href="{{ route('library.download', $item) }}">Download {{ $item->format() }}</a>
                    @endif
                    @if($student)
                        @if($item->access === 'loan' && !$loan && $available > 0)
                            <form action="{{ route('account.library.borrow', $item) }}" method="post">@csrf
                                <button class="lc-lib-button lc-lib-button--primary">Borrow for {{ $item->loan_days }} days</button>
                            </form>
                        @endif
                        @if($loan)
                            <form action="{{ route('account.library.return', $loan) }}" method="post">@csrf @method('PATCH')
                                <button class="lc-lib-button">Return item</button>
                            </form>
                        @endif
                        <form action="{{ route($saved ? 'account.library.unsave' : 'account.library.save', $item) }}" method="post">@csrf
                            @if($saved) @method('DELETE') @endif
                            <button class="lc-lib-button">{{ $saved ? 'Remove from saved' : 'Save to My Library' }}</button>
                        </form>
                    @elseif(!auth()->check())
                        <a class="lc-lib-button" href="{{ \App\Support\ReadingReturn::authUrl(request(), 'login') }}">
                            {{ $item->access === 'loan' ? 'Sign in to borrow' : ($item->access === 'students' ? 'Sign in to access' : 'Sign in to save') }}
                        </a>
                    @elseif($item->access !== 'open')
                        <p class="lc-lib-by">Available to active student accounts.</p>
                    @endif
                </div>
            </div>
        </article>
    </div>
</div>
@endsection
