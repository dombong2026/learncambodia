@extends('layouts.workspace')

@section('heading', 'OpenAI API')

@section('content')
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <section class="workspace-panel space-y-5 p-5 sm:p-6" aria-labelledby="openai-settings-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="openai-settings-heading" class="text-lg font-extrabold">Connection settings</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold dark:bg-slate-800">{{ $configured ? 'API key saved' : 'API key required' }}</span>
            </div>
            @if($errors->any())
                <ul role="alert" class="space-y-1 text-sm text-red-700 dark:text-red-300">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            @if($confirmed)
                <form method="post" action="{{ route('admin.system-settings.openai.save') }}" class="space-y-5">
                    @csrf @method('PUT')
                    <label class="builder-field">
                        {{ $configured ? 'Replace API key' : 'OpenAI project API key' }}
                        <input type="password" name="api_key" maxlength="300" autocomplete="new-password" placeholder="{{ $configured ? 'Leave blank to keep saved key' : 'sk-proj-…' }}" spellcheck="false">
                    </label>
                    <fieldset>
                        <legend class="mb-3 text-sm font-bold">Enable in the dashboard</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach(\App\Support\ManagedOpenAiSettings::FEATURES as $key => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 p-3 text-sm font-semibold dark:border-slate-600">
                                    <input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $enabled, true))>{{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <button type="submit" class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Save settings</button>
                </form>
                @if($configured)
                    <div class="flex flex-wrap gap-3 border-t border-slate-300 pt-4 dark:border-slate-700">
                        <form method="post" action="{{ route('admin.system-settings.openai.test') }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-slate-400 px-4 py-2 text-sm font-semibold">Test connection</button>
                        </form>
                        <form method="post" action="{{ route('admin.system-settings.openai.remove') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-xl border border-red-400 px-4 py-2 text-sm font-semibold text-red-700 dark:text-red-300">Remove key and disable tools</button>
                        </form>
                    </div>
                @endif
            @else
                <a href="{{ route('admin.system-settings.openai.authorize') }}" class="inline-flex rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white">Unlock settings</a>
            @endif
        </section>
        <aside class="workspace-panel space-y-3 p-5 sm:p-6" aria-labelledby="openai-authoring-heading">
            <h2 id="openai-authoring-heading" class="text-lg font-extrabold">Authoring tools</h2>
            <dl class="space-y-3 text-sm leading-6">
                <div><dt class="font-bold">Exams, quizzes and placement tests</dt><dd>Draft multiple-choice questions with answers and explanations.</dd></div>
                <div><dt class="font-bold">Listening audio</dt><dd>Enable on its own to record passages for assessments. Preview the recording before attaching it.</dd></div>
                <div><dt class="font-bold">Courses and lessons</dt><dd>Draft summaries, full course descriptions and lesson content in the Course Builder.</dd></div>
            </dl>
            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Editors review each draft and save it through the usual editor. Requests use the API project’s billing.</p>
            <a class="text-sm font-semibold underline" href="https://platform.openai.com/api-keys">OpenAI API keys</a>
        </aside>
    </div>
@endsection
