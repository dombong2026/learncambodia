@extends('layouts.workspace')
@php($manager = match($kind) { 'quiz' => 'admin.quizzes-manager', 'exam' => 'admin.exams', default => 'admin.courses' })
@section('title', 'Assign to user')
@section('heading', 'Assign to user')
@section('description', $record->title)
@section('heading_action')
    <a class="learning-manager-control" href="{{ route($manager.'.index') }}">Back to list</a>
@endsection
@section('content')
@foreach(['manager', 'user', 'reference'] as $field)
    @error($field)<p class="learning-manager-error" role="alert">{{ $message }}</p>@enderror
@endforeach
@if(!$available)
    <p class="learning-manager-error">Activate the approved course to assign users.</p>
@endif
@php($search = \App\Support\LearningTable::search(request()))
<section class="learning-manager" aria-label="Eligible users">
    <div class="learning-manager-toolbar">
        <span class="learning-manager-count">Eligible users</span>
        <form method="get" role="search" class="learning-manager-search">
            <label class="learning-manager-search-field">
                <span class="sr-only">Search users</span>
                <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name, email, or user ID">
            </label>
            <button type="submit" class="learning-manager-control learning-manager-primary">Search</button>
        </form>
    </div>
    <div class="learning-manager-scroll">
        <table>
            <thead><tr><th scope="col">User</th><th scope="col">ID</th><th scope="col">Role</th><th scope="col">Action</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <th scope="row" class="learning-manager-title">{{ $user->name }}<small>{{ $user->email }}</small></th>
                    <td><span class="learning-manager-id">{{ $user->public_id }}</span></td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>
                        @if($assigned->contains($user->id))
                            <span class="learning-manager-status" data-tone="positive">Assigned</span>
                        @elseif($available)
                            <form method="post" action="{{ route($manager.'.assign', $record) }}" class="flex flex-wrap items-center justify-end gap-2">
                                @csrf
                                <input type="hidden" name="user" value="{{ $user->public_id }}">
                                @if($requiresReference->contains($user->id))
                                    <input class="learning-manager-reference" type="text" name="reference" placeholder="Verification reference" aria-label="Payment or membership verification reference for {{ $user->name }}" maxlength="160" required>
                                @endif
                                <button class="learning-manager-control learning-manager-primary" type="submit" aria-label="Assign to {{ $user->name }}">Assign</button>
                            </form>
                        @else
                            <span>Unavailable</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="learning-manager-empty">No eligible users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="p-4">{{ $users->links() }}</div>@endif
</section>
@endsection
