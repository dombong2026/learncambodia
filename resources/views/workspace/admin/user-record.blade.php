@extends('layouts.workspace')
@section('title', 'User #'.$account->public_id)
@section('heading', 'User #'.$account->public_id)
@section('heading_action')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">User Manager</a>
        @if(\App\Support\AdminAccess::canEdit(auth()->user(), $account) && $account->account_status !== 'deleted')
            <a href="{{ route('admin.users.access.edit', $account) }}" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Edit</a>
        @endif
    </div>
@endsection
@section('content')
    <dl class="workspace-panel grid gap-5 p-6 sm:grid-cols-2">
        @foreach(['Name' => $account->name, 'Email' => $account->email, 'Role' => \App\Support\AdminAccess::ROLES[$account->role] ?? $account->role, 'Status' => ucfirst($account->account_status), 'Phone' => $account->phone ?? '—', 'Phone verified' => $account->phone_verified_at ? 'Verified' : 'Pending', 'Email verified' => $account->hasVerifiedEmail() ? 'Verified' : 'Pending', 'Joined' => $account->created_at?->format('Y-m-d')] as $label => $value)
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                <dd class="mt-1 break-words">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    @if($account->isAdministrator())
        <section class="workspace-panel mt-5 p-6" aria-labelledby="user-permissions-heading">
            <h2 id="user-permissions-heading" class="text-lg font-bold">Administrator permissions</h2>
            <ul class="mt-3 list-inside list-disc space-y-2 text-sm">
                @forelse(\App\Support\AdminAccess::grants($account) as $permission)
                    <li>{{ \App\Support\AdminAccess::PERMISSIONS[$permission] }}</li>
                @empty
                    <li>Overview and personal settings only</li>
                @endforelse
            </ul>
        </section>
    @endif
@endsection
