@extends('layouts.workspace')
@section('title', 'User #'.$account->public_id)
@section('heading', 'User #'.$account->public_id)
@section('heading_action')
    <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">User Manager</a>
@endsection
@section('content')
    <dl class="workspace-panel grid gap-5 p-6 sm:grid-cols-2">
        @foreach(['Name' => $account->name, 'Email' => $account->email, 'Role' => ucfirst($account->role), 'Joined' => $account->created_at?->format('Y-m-d')] as $label => $value)
            <div>
                <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                <dd class="mt-1 break-words">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endsection
