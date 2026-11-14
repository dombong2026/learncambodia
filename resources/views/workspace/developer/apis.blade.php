@extends('layouts.workspace')
@section('title', 'API Reference')
@section('heading', 'API Reference')
@section('description', 'Versioned, read-only Learn Cambodia catalogue endpoints secured with scoped developer tokens.')
@section('content')
<div class="grid gap-5 xl:grid-cols-2">
    <section class="workspace-panel rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Available endpoints</h2>
        <p class="mt-2 text-sm leading-7 text-slate-500">All requests require Authorization: Bearer TOKEN. Only read-scoped developer tokens are currently supported.</p>
        <div class="mt-6 space-y-3">
            @foreach(['/api/v1/categories' => 'Published categories with optional group filter', '/api/v1/cefr-levels' => 'CEFR A1–C2 descriptors', '/api/v1/courses' => 'Published courses only'] as $endpoint => $description)
                <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><p class="break-all font-mono text-sm font-semibold text-blue-700 dark:text-blue-400"><span class="mr-2 text-xs text-emerald-700">GET</span>{{ $endpoint }}</p><p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p></div>
            @endforeach
        </div>
    </section>
    <section class="workspace-panel rounded-3xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-xl font-extrabold">Usage guidelines</h2>
        <div class="mt-5 space-y-4 text-sm leading-7 text-slate-600 dark:text-slate-400">
            <p><strong>API version:</strong> v1 · <strong>Token ability:</strong> catalog:read.</p>
            <p><strong>Pagination:</strong> per_page 1–50, default 10. JSON pagination metadata is included.</p>
            <p><strong>Rate limiting:</strong> 60 requests per minute per authenticated route context.</p>
            <p><strong>Expires:</strong> Every new token has a maximum lifetime of 30 days; tokens can be revoked any time.</p>
            <p><strong>Access:</strong> No user-data, financial-data or write endpoints are exposed in this first milestone.</p>
            <p><strong>Security:</strong> Store tokens server-side, never in public frontend code or source control.</p>
        </div>
        <a href="{{ route('developer.tokens.index') }}" class="mt-6 inline-block rounded-full bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">Manage access tokens</a>
    </section>
</div>
@endsection
