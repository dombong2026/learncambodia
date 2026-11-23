<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\HomeCampaign;
use App\Support\EditorHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HomeCampaignController extends Controller
{
    public function builder(Request $request): View
    {
        $campaigns = HomeCampaign::query()->latest('updated_at')->get();
        $campaign = $request->filled('id')
            ? HomeCampaign::query()->findOrFail($request->integer('id'))
            : null;

        return view('workspace.admin.home-campaign-interactive', compact('campaigns', 'campaign') + ['role' => 'admin']);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:home_campaigns,id'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:600'],
            'kind' => ['required', 'in:quiz,exam'],
            'publication_intent' => ['required', 'in:draft,publish'],
            'modules_json' => ['required', 'string', 'max:250000'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $raw = json_decode($data['modules_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['modules_json' => 'The module data is invalid.']);
        }

        if (!is_array($raw) || !array_is_list($raw) || count($raw) > 25) {
            throw ValidationException::withMessages(['modules_json' => 'Add up to 25 valid modules.']);
        }
        $modules = $this->cleanModules($raw);
        $publishing = $data['publication_intent'] === 'publish';
        $campaign = isset($data['campaign_id'])
            ? HomeCampaign::query()->findOrFail($data['campaign_id'])
            : new HomeCampaign();

        if ($publishing && (count($modules) === 0 || !collect($modules)->contains(
            fn (array $module): bool => count($module['lessons']) > 0
        ))) {
            throw ValidationException::withMessages(['modules_json' => 'Add at least one lesson before publishing.']);
        }
        if ($publishing && !$request->hasFile('cover') && !$campaign->cover_path) {
            throw ValidationException::withMessages(['cover' => 'Add a Quiz or Exam cover image before publishing.']);
        }

        $oldCover = $campaign->cover_path;
        $newCover = $request->file('cover')?->store('home-campaigns', 'local');
        if (!$campaign->exists) {
            $base = Str::slug($data['title']) ?: 'campaign';
            $base = Str::limit($base, 165, '');
            $slug = $base;
            for ($suffix = 2; HomeCampaign::query()->where('slug', $slug)->exists(); $suffix++) {
                $slug = $base.'-'.$suffix;
            }
            $campaign->slug = $slug;
            $campaign->created_by = $request->user()->id;
        }

        $campaign->fill([
            'title' => trim($data['title']),
            'summary' => trim($data['summary'] ?? ''),
            'kind' => $data['kind'],
            'modules' => $modules,
            'cover_path' => $newCover ?: $oldCover,
            'status' => $publishing ? 'published' : 'draft',
            'published_at' => $publishing ? now() : null,
            'updated_by' => $request->user()->id,
        ]);
        $campaign->save();
        if ($newCover && $oldCover) Storage::disk('local')->delete($oldCover);

        return redirect()->route('admin.campaign.builder', ['id' => $campaign->id])
            ->with('status', $publishing ? 'Campaign published without an approval queue.' : 'Campaign saved as a draft.');
    }

    private function cleanModules(array $raw): array
    {
        $modules = [];
        $ids = [];
        foreach ($raw as $module) {
            if (!is_array($module)) $this->invalid('Modules must be objects.');
            $title = $this->short($module['title'] ?? '', 180);
            $lessons = $module['lessons'] ?? null;
            if (!is_array($lessons) || !array_is_list($lessons) || count($lessons) > 50) {
                $this->invalid('Each module can have up to 50 lessons.');
            }
            $cleanLessons = [];
            foreach ($lessons as $lesson) {
                if (!is_array($lesson)) $this->invalid('Lesson data is invalid.');
                $type = (string) ($lesson['type'] ?? 'reading');
                if (!in_array($type, ['reading', 'multiple_choice', 'matching', 'ordering'], true)) {
                    $this->invalid('Select a supported question type.');
                }
                $id = (string) ($lesson['id'] ?? '');
                if (!preg_match('/^[a-z0-9-]{8,64}$/D', $id) || isset($ids[$id])) $id = (string) Str::uuid();
                $ids[$id] = true;

                $item = [
                    'id' => $id,
                    'title' => $this->short($lesson['title'] ?? '', 180),
                    'type' => $type,
                    'content_html' => EditorHtml::clean($this->short($lesson['content_html'] ?? '', 50000), true) ?? '',
                    'explanation_html' => EditorHtml::clean($this->short($lesson['explanation_html'] ?? '', 20000), true) ?? '',
                    'feedback_correct' => $this->short($lesson['feedback_correct'] ?? '', 600),
                    'feedback_incorrect' => $this->short($lesson['feedback_incorrect'] ?? '', 600),
                    'media_kind' => in_array(($lesson['media_kind'] ?? ''), ['', 'image', 'audio', 'video', 'youtube'], true) ? ($lesson['media_kind'] ?? '') : '',
                    'media_url' => $this->safeMediaUrl($lesson['media_url'] ?? ''),
                ];

                if ($type === 'multiple_choice') {
                    $options = $lesson['options'] ?? [];
                    if (!is_array($options) || !array_is_list($options) || count($options) < 2 || count($options) > 6) {
                        $this->invalid('Multiple-choice lessons need 2–6 options.');
                    }
                    $item['options'] = array_map(fn ($value) => $this->short($value, 300), $options);
                    $key = filter_var($lesson['correct_index'] ?? null, FILTER_VALIDATE_INT);
                    if ($key === false || $key < 0 || $key >= count($options)) $this->invalid('Select a correct option.');
                    $item['correct_index'] = $key;
                } elseif ($type === 'matching') {
                    $pairs = $lesson['pairs'] ?? [];
                    if (!is_array($pairs) || !array_is_list($pairs) || count($pairs) < 2 || count($pairs) > 12) {
                        $this->invalid('Matching lessons need 2–12 pairs.');
                    }
                    $item['pairs'] = array_map(fn ($pair) => [
                        'left' => $this->short(is_array($pair) ? ($pair['left'] ?? '') : '', 300),
                        'right' => $this->short(is_array($pair) ? ($pair['right'] ?? '') : '', 300),
                    ], $pairs);
                    if (count(array_unique(array_column($item['pairs'], 'right'))) !== count($item['pairs'])) {
                        $this->invalid('Matching answers must be unique.');
                    }
                } elseif ($type === 'ordering') {
                    $steps = $lesson['sequence'] ?? [];
                    if (!is_array($steps) || !array_is_list($steps) || count($steps) < 2 || count($steps) > 12) {
                        $this->invalid('Ordering lessons need 2–12 movable blocks.');
                    }
                    $item['sequence'] = array_map(fn ($value) => $this->short($value, 300), $steps);
                    if (count(array_unique($item['sequence'])) !== count($item['sequence'])) {
                        $this->invalid('Ordering blocks must be unique.');
                    }
                }
                $cleanLessons[] = $item;
            }
            $modules[] = [
                'id' => (string) Str::uuid(),
                'title' => $title,
                'lessons' => $cleanLessons,
            ];
        }

        return $modules;
    }

    private function safeMediaUrl(mixed $url): string
    {
        $value = $this->short($url, 2048);
        if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || parse_url($value, PHP_URL_SCHEME) !== 'https')) {
            $this->invalid('Media links must be valid HTTPS URLs.');
        }
        return $value;
    }

    private function short(mixed $value, int $max): string
    {
        if (!is_string($value) && !is_numeric($value)) $this->invalid('A text field contains invalid data.');
        $value = trim((string) $value);
        if (mb_strlen($value) > $max) $this->invalid('Text exceeds its allowed length.');
        return $value;
    }

    private function invalid(string $reason): never
    {
        throw ValidationException::withMessages(['modules_json' => $reason]);
    }

    public function show(Request $request, HomeCampaign $campaign): View
    {
        abort_unless($campaign->status === 'published' || $request->user()?->isAdministrator(), 404);

        return view('learning.home-campaign', compact('campaign'));
    }

    public function cover(Request $request, HomeCampaign $campaign): BinaryFileResponse
    {
        abort_unless($campaign->status === 'published' || $request->user()?->isAdministrator(), 404);
        abort_unless($campaign->cover_path && Storage::disk('local')->exists($campaign->cover_path), 404);

        return response()->file(Storage::disk('local')->path($campaign->cover_path), [
            'Cache-Control' => $campaign->status === 'published' ? 'public, max-age=3600' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function check(Request $request, HomeCampaign $campaign, string $lessonId): JsonResponse
    {
        abort_unless($campaign->status === 'published' || $request->user()?->isAdministrator(), 404);
        $lesson = null;
        foreach ($campaign->modules ?? [] as $module) {
            foreach ($module['lessons'] ?? [] as $candidate) {
                if (($candidate['id'] ?? '') === $lessonId) { $lesson = $candidate; break 2; }
            }
        }
        abort_unless($lesson && $lesson['type'] !== 'reading', 404);
        $answer = $request->input('answer');
        $type = $lesson['type'];
        if ($type === 'multiple_choice') {
            $request->validate(['answer' => ['required', 'integer', 'min:0', 'max:5']]);
            $correct = (int) $answer === (int) $lesson['correct_index'];
            $expected = $lesson['options'][$lesson['correct_index']];
        } else {
            $request->validate(['answer' => ['required', 'array', 'min:2', 'max:12'], 'answer.*' => ['required', 'string', 'max:300']]);
            $expectedList = $type === 'matching'
                ? array_column($lesson['pairs'], 'right') : $lesson['sequence'];
            $correct = array_values($answer) === array_values($expectedList);
            $expected = $expectedList;
        }

        return response()->json([
            'correct' => $correct,
            'feedback' => ($correct ? $lesson['feedback_correct'] : $lesson['feedback_incorrect'])
                ?: ($correct ? 'Correct. Well done!' : 'Not quite. Review the explanation and try again.'),
            'explanation_html' => $lesson['explanation_html'] ?? '',
            'expected' => $correct ? null : $expected,
        ])->header('Cache-Control', 'no-store');
    }
}
