@extends('layouts.workspace')
@section('title', 'Confirm MCP Action')
@section('heading', 'Confirm MCP Action')
@section('description', 'A LearnCambodia MCP session requested one destructive action. Verify the resource before approval.')

@section('content')
<div class="max-w-xl mx-auto rounded-xl border border-slate-300 dark:border-slate-700 p-6 bg-white dark:bg-slate-900">
    <h2 class="font-semibold text-lg mb-3">Confirm {{ $approval->operation }} on LearnCambodia</h2>
    <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
        This request originated from a short-lived MCP browser-admin session. Review the exact target below.
    </p>
    <dl class="text-sm space-y-2 mb-4">
        <div><dt class="font-semibold">Resource</dt><dd>{{ $approval->resource }}</dd></div>
        <div><dt class="font-semibold">Record ID</dt><dd>{{ $approval->resource_id }}</dd></div>
        <div><dt class="font-semibold">Requested operation</dt><dd>{{ $approval->operation }}</dd></div>
        <div><dt class="font-semibold">Impact</dt><dd>Move the selected record into the soft-deleted state. This can be reversed by an authorized administrator.</dd></div>
        <div><dt class="font-semibold">Reversible</dt><dd>Yes — this bridge does not permanently purge data.</dd></div>
        <div><dt class="font-semibold">Expires</dt><dd>{{ $approval->expires_at->format('Y-m-d H:i:s') }}</dd></div>
    </dl>
    <form action="{{ route('admin.system-settings.mcp.approvals.decide', ['approval'=>$approval->id]) }}" method="POST">
        @csrf
        <label class="block text-sm font-semibold mb-2" for="mcp-approval-word">Type CONFIRM to authorize this one action</label>
        <input id="mcp-approval-word" name="confirmation" autocomplete="off" placeholder="CONFIRM" maxlength="20" required
            class="w-full rounded-lg border border-slate-300 dark:border-slate-600 p-2 mb-3 dark:bg-slate-800">
        @error('confirmation')<p role="alert" class="text-red-600 text-sm mb-3">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-2">
            <button name="decision" value="approve" class="rounded-lg bg-red-700 hover:bg-red-800 text-white px-4 py-2" type="submit">Approve Action</button>
            <button name="decision" value="deny" class="rounded-lg border border-slate-300 dark:border-slate-600 px-4 py-2" type="submit" formnovalidate>Cancel</button>
        </div>
    </form>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-4">Approving does not execute the action. The requesting MCP session must submit a matching DELETE within ten minutes. A revoked or expired session cannot use this approval.</p>
</div>
@endsection
