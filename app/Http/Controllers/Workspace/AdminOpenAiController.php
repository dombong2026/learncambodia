<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\ManagedOpenAiSettings;
use App\Support\OpenAiAuthoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminOpenAiController extends Controller
{
    public function edit(Request $request): View
    {
        $saved = ManagedOpenAiSettings::saved();

        return view('workspace.admin.openai.settings', [
            'configured' => filled($saved['key']), 'enabled' => $saved['features'],
            'confirmed' => time() - (int) $request->session()->get('auth.password_confirmed_at', 0) <= (int) config('auth.password_timeout'),
        ]);
    }

    public function authorizeSettings(): RedirectResponse
    {
        return to_route('admin.system-settings.openai.edit');
    }

    public function save(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->only('api_key', 'features'), [
            'api_key' => ['nullable', 'string', 'max:300', 'regex:/^sk-[A-Za-z0-9_-]{20,}$/D'],
            'features' => ['sometimes', 'array', 'max:5'],
            'features.*' => ['required', Rule::in(array_keys(ManagedOpenAiSettings::FEATURES)), 'distinct'],
        ], ['api_key.regex' => 'Enter an OpenAI project API key.']);
        if ($validator->fails()) {
            // General Laravel validation redirects can flash the request body.
            return back()->withErrors($validator);
        }

        $data = $validator->validated();
        $key = $data['api_key'] ?? ManagedOpenAiSettings::saved()['key'];
        $enabled = $data['features'] ?? [];
        if (!filled($key) && $enabled !== []) {
            return back()->withErrors(['api_key' => 'Add an API key before enabling authoring tools.']);
        }
        ManagedOpenAiSettings::save(['key' => $key, 'features' => $enabled]);

        return back()->with('status', 'OpenAI settings saved.');
    }

    public function remove(): RedirectResponse
    {
        ManagedOpenAiSettings::save(['key' => null, 'features' => []]);

        return back()->with('status', 'OpenAI key removed and authoring tools disabled.');
    }

    public function test(OpenAiAuthoring $openAi): RedirectResponse
    {
        $settings = ManagedOpenAiSettings::saved();
        $key = $settings['key'];
        if (!filled($key)) {
            return back()->withErrors(['openai' => 'Save an API key before testing the connection.']);
        }

        if ($settings['features'] === ['audio']) {
            $openAi->audio($key, 'Listening audio is ready.');

            return back()->with('status', 'OpenAI connection verified. Listening audio was generated successfully.');
        }

        $openAi->test($key);

        return back()->with('status', 'OpenAI connection verified. The text model responded successfully.');
    }

    public function draft(Request $request, OpenAiAuthoring $openAi): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', Rule::in(['exam', 'quiz', 'test', 'course'])],
            'task' => ['required', Rule::in(['questions', 'summary', 'description', 'lesson', 'audio'])],
            'topic' => ['required', 'string', 'max:1800'],
            'level' => ['required', Rule::in(['Mixed', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'])],
            'count' => ['required_if:task,questions', 'integer', 'between:1,10'],
        ]);
        $course = $data['context'] === 'course';
        $isAudio = $data['task'] === 'audio';
        $allowedTasks = $course ? ['summary', 'description', 'lesson'] : ['questions', 'audio'];
        abort_unless(in_array($data['task'], $allowedTasks, true), 422, 'This action is unavailable for the editor.');
        $settings = ManagedOpenAiSettings::saved();
        $feature = $isAudio ? 'audio' : $data['context'];
        if (!ManagedOpenAiSettings::enabled($settings, $feature)) {
            throw ValidationException::withMessages(['openai' => 'Enable this OpenAI tool in System Settings first.']);
        }

        $result = match ($data['task']) {
            'questions' => ['questions' => $openAi->questions($settings['key'], $data['context'], $data['topic'], $data['level'], $data['count'])],
            'audio' => $openAi->audio($settings['key'], $data['topic']),
            default => ['text' => $openAi->course($settings['key'], $data['task'], $data['topic'], $data['level'])],
        };

        return response()->json($result);
    }
}
