<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\HomepageCampaignItem;
use App\Support\EditorHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class HomepageCampaignManagerController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdministrator(), 403);
    }

    public function edit(Request $request): View
    {
        $this->authorizeAdmin($request);
        $campaign = $request->filled('id')
            ? HomepageCampaignItem::query()->findOrFail($request->integer('id')) : null;

        return view('workspace.admin.campaign-studio', [
            'role' => 'admin',
            'campaign' => $campaign,
            'rows' => $campaign?->draft ?? $campaign?->published ?? [],
            'version' => $campaign?->version ?? 0,
            'hasDraft' => $campaign?->draft !== null,
            'publishedAt' => $campaign?->published_at,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:homepage_campaign_items,id'],
            'version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:180'],
            'kind' => ['required', 'in:quiz,exam'],
            'cover_asset' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}\.(jpg|png|webp)$/D'],
            'action' => ['required', 'in:draft,publish'],
            'rows' => ['required', 'array', 'max:60'],
        ]);
        $rows = $this->cleanRows($data['rows'], $data['action'] === 'publish');
        $cover = $data['cover_asset'] ?? null;
        $this->verifyCover($cover);

        $campaign = DB::transaction(function () use ($request, $data, $rows, $cover) {
            $campaign = !empty($data['id'])
                ? HomepageCampaignItem::query()->lockForUpdate()->findOrFail($data['id'])
                : new HomepageCampaignItem();
            $version = (int) ($campaign->exists ? $campaign->version : 0);
            if ($version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This campaign changed in another tab. Reload before saving.']);
            }
            if (!$campaign->exists) {
                $campaign->created_by = $request->user()->id;
                $base = Str::slug($data['title']) ?: 'campaign';
                $base = mb_substr($base, 0, 170);
                $slug = $base;
                for ($suffix = 2; HomepageCampaignItem::where('slug', $slug)->exists(); $suffix++) {
                    $slug = mb_substr($base, 0, 160).'-'.$suffix;
                }
                $campaign->slug = $slug;
            }
            if ($data['action'] === 'publish' && $cover === null) {
                throw ValidationException::withMessages(['cover_asset' => 'Upload the 1920 × 1080 campaign cover before publishing.']);
            }

            $campaign->title = trim($data['title']);
            $campaign->kind = $data['kind'];
            $campaign->cover_asset = $cover;
            $campaign->updated_by = $request->user()->id;
            $campaign->version = $version + 1;
            if ($data['action'] === 'publish') {
                $campaign->published = $rows;
                $campaign->draft = null;
                $campaign->status = 'published';
                $campaign->published_at = now();
            } else {
                $campaign->draft = $rows;
                if (!$campaign->exists) $campaign->status = 'draft';
                // Saving a draft does not silently unpublish an existing published campaign.
            }
            $campaign->save();

            return $campaign;
        });

        return response()->json([
            'id' => $campaign->id,
            'version' => $campaign->version,
            'status' => $campaign->status,
            'message' => $data['action'] === 'publish'
                ? 'Campaign published to the homepage hero.'
                : 'Campaign draft saved.',
            'edit_url' => route('admin.appearance.campaign.edit', ['id' => $campaign->id, 'lang' => 'en']),
        ], headers: ['Cache-Control' => 'no-store']);
    }

    public function status(Request $request, HomepageCampaignItem $campaign): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['action' => ['required', 'in:draft,publish']]);
        DB::transaction(function () use ($request, $campaign, $data) {
            $item = HomepageCampaignItem::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($data['action'] === 'publish') {
                $rows = $item->draft ?? $item->published ?? [];
                $rows = $this->cleanRows($rows, true);
                $this->verifyCover($item->cover_asset);
                if (!$item->cover_asset) {
                    throw ValidationException::withMessages(['cover_asset' => 'Add a featured cover before publishing.']);
                }
                $item->published = $rows;
                $item->draft = null;
                $item->status = 'published';
                $item->published_at = now();
            } else {
                $item->draft = $item->draft ?? $item->published ?? [];
                $item->status = 'draft';
                $item->published_at = null;
            }
            $item->version++;
            $item->updated_by = $request->user()->id;
            $item->save();
        });
        return redirect()->route('admin.appearance.edit', ['section' => 'home', 'lang' => 'en'])
            ->with('status', $data['action'] === 'publish' ? 'Campaign published.' : 'Campaign moved to draft.');
    }

    public function play(HomepageCampaignItem $campaign): JsonResponse
    {
        abort_unless($campaign->status === 'published' && $campaign->published_at !== null, 404);
        $lessons = [];
        foreach ($campaign->published ?? [] as $rowIndex => $row) {
            if (!in_array($row['type'] ?? '', ['quiz', 'exam'], true)) continue;
            foreach ($row['questions'] ?? [] as $questionIndex => $question) {
                $lessons[] = [
                    'row' => $rowIndex,
                    'question' => $questionIndex,
                    'module' => $row['course'] ?? '',
                    'lesson' => $row['lesson'] ?? '',
                    'body' => EditorHtml::clean($row['body'] ?? '', true) ?? '',
                    'prompt' => $question['prompt'] ?? '',
                    'options' => array_values($question['options'] ?? []),
                ];
            }
        }
        return response()->json([
            'id' => $campaign->id,
            'title' => $campaign->title,
            'kind' => $campaign->kind,
            'questions' => $lessons,
        ], headers: ['Cache-Control' => 'no-store']);
    }

    public function check(Request $request, HomepageCampaignItem $campaign): JsonResponse
    {
        abort_unless($campaign->status === 'published' && $campaign->published_at !== null, 404);
        $data = $request->validate([
            'row' => ['required', 'integer', 'min:0', 'max:59'],
            'question' => ['required', 'integer', 'min:0', 'max:49'],
            'answer' => ['required', 'integer', 'min:0', 'max:5'],
        ]);
        $rows = $campaign->published ?? [];
        $row = $rows[$data['row']] ?? null;
        abort_unless($row && in_array($row['type'] ?? '', ['quiz','exam'], true), 404);
        $question = $row['questions'][$data['question']] ?? null;
        abort_unless($question && isset($question['options'][$data['answer']], $question['options'][$question['correct']]), 422);
        $correct = (int) $data['answer'] === (int) $question['correct'];
        return response()->json([
            'correct' => $correct,
            'answer' => $question['options'][$question['correct']],
            'feedback' => $question['feedback'] ?? '',
        ], headers: ['Cache-Control' => 'no-store']);
    }

    public function cover(Request $request, HomepageCampaignItem $campaign): BinaryFileResponse
    {
        abort_unless(
            ($campaign->status === 'published' && $campaign->published_at !== null)
            || $request->user()?->isAdministrator(), 404
        );
        $asset = $campaign->cover_asset;
        abort_unless($asset && preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $asset), 404);
        $file = storage_path('app/campaign-assets/'.$asset);
        abort_unless(is_file($file), 404);
        return response()->file($file, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $campaign->status === 'published'
                ? 'public, max-age=600' : 'private, no-store',
        ]);
    }

    private function verifyCover(?string $asset): void
    {
        if ($asset === null || $asset === '') return;
        $file = storage_path('app/campaign-assets/'.$asset);
        if (!preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $asset) || !is_file($file)) {
            throw ValidationException::withMessages(['cover_asset' => 'Upload a verified image first.']);
        }
        $size = @getimagesize($file);
        if (($size[0] ?? 0) !== 1920 || ($size[1] ?? 0) !== 1080) {
            throw ValidationException::withMessages(['cover_asset' => 'The cover must be 1920 × 1080 pixels.']);
        }
    }

    private function cleanRows(array $rows, bool $publish): array
    {
        if ($publish && count($rows) === 0) {
            throw ValidationException::withMessages(['rows' => 'Add at least one lesson before publishing.']);
        }
        $clean = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) throw ValidationException::withMessages(['rows' => 'Invalid lesson data.']);
            $type = $row['type'] ?? '';
            if (!in_array($type, ['quiz','exam','text','html','emoji','code','youtube','image','video'], true)) {
                throw ValidationException::withMessages(['rows' => 'Invalid lesson type.']);
            }
            $title = trim((string) ($row['lesson'] ?? ''));
            $course = trim((string) ($row['course'] ?? ''));
            if ($course === '' || mb_strlen($course)>140 || mb_strlen($title)>140 || ($publish && $title==='')) {
                throw ValidationException::withMessages(['rows' => 'Complete module and lesson titles before publishing.']);
            }
            $body = (string) ($row['body'] ?? '');
            if (mb_strlen($body)>20000) throw ValidationException::withMessages(['rows' => 'Lesson content too long.']);
            if ($type !== 'code') $body = EditorHtml::clean($body, true) ?? '';
            $url = trim((string) ($row['url'] ?? ''));
            $asset = trim((string) ($row['asset'] ?? ''));
            if (mb_strlen($url)>2048 || mb_strlen($asset)>75) {
                throw ValidationException::withMessages(['rows' => 'Invalid media URL length.']);
            }
            if ($asset !== '' && (!preg_match('/^[a-f0-9]{64}\.(jpg|png|webp|mp4|webm)$/D',$asset)
                || !is_file(storage_path('app/campaign-assets/'.$asset)))) {
                throw ValidationException::withMessages(['rows' => 'Select verified lesson media.']);
            }
            if ($url !== '' && !$asset && !preg_match('~^https://~i', $url)) {
                throw ValidationException::withMessages(['rows' => 'Media URLs must use HTTPS.']);
            }
            $questions = [];
            if (in_array($type, ['quiz','exam'], true)) {
                $input = $row['questions'] ?? [];
                if (!is_array($input) || count($input)>50 || ($publish && !$input)) {
                    throw ValidationException::withMessages(['rows' => 'Quiz and Exam lessons need valid questions.']);
                }
                foreach ($input as $q) {
                    if (!is_array($q) || !is_array($q['options'] ?? null)) {
                        throw ValidationException::withMessages(['rows' => 'Invalid question choices.']);
                    }
                    $options = array_values($q['options']);
                    $key = filter_var($q['correct'] ?? null, FILTER_VALIDATE_INT);
                    $prompt = trim((string) ($q['prompt'] ?? ''));
                    if (count($options)<2 || count($options)>6 || $key===false
                        || $key<0 || $key>=count($options) || mb_strlen($prompt)>1000
                        || ($publish && $prompt==='')) {
                        throw ValidationException::withMessages(['rows' => 'Complete each question and its answer key.']);
                    }
                    $cleanOptions = [];
                    foreach ($options as $choice) {
                        $value = trim((string) $choice);
                        if (mb_strlen($value)>500 || ($publish && $value==='')) {
                            throw ValidationException::withMessages(['rows' => 'Invalid answer choice.']);
                        }
                        $cleanOptions[] = $value;
                    }
                    $feedback = trim((string) ($q['feedback'] ?? ''));
                    if (mb_strlen($feedback)>1000) throw ValidationException::withMessages(['rows' => 'Answer explanation too long.']);
                    $questions[] = ['prompt'=>$prompt,'options'=>$cleanOptions,'correct'=>$key,'feedback'=>$feedback];
                }
            }
            $clean[] = [
                'course'=>$course,
                'lesson'=>$title,
                'type'=>$type,
                'body'=>$body,
                'url'=>$url,
                'asset'=>$asset,
                'language'=>mb_substr((string) ($row['language'] ?? ''),0,40),
                'questions'=>$questions,
                'feedback'=>mb_substr((string) ($row['feedback'] ?? ''),0,1000),
            ];
        }
        return $clean;
    }
}
