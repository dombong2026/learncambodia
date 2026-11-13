@extends('layouts.workspace')
@section('title', 'Usage & Limits')
@section('heading', 'Usage & Limits')
@section('description', 'Current access rules for your developer credentials.')
@section('content')
<div class="max-w-3xl rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-xl font-extrabold">API access policy</h2>
    <p class="mt-4 text-sm leading-8 text-slate-600 dark:text-slate-400">The API is read-only in this milestone. Each developer is limited to ten active tokens, with 30 days maximum token lifetime, and catalogue requests are limited to 60 per minute. Historical usage analytics are not yet collected; no fabricated request totals are shown here.</p>
    <a href="{{ route('developer.apis') }}" class="mt-5 inline-block text-sm font-semibold text-blue-700 hover:underline dark:text-blue-400">View API endpoints →</a>
</div>
@endsection
