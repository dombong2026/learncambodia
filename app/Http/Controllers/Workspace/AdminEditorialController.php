<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\Category;
use App\Models\PostTerm;
use App\Models\EditorMedia;
use App\Models\PolicyRevision;
use App\Support\EditorHtml;
use App\Support\ApprovalAudit;
use App\Support\FeaturedMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEditorialController extends Controller
{
    private const TYPES = [
        'pages' => 'page',
        'posts' => 'post',
        'policies' => 'policy',
        'faqs' => 'faq',
        'help-articles' => 'help_article',
    ];

    private function type(string $kind): string
    {
        abort_unless(array_key_exists($kind, self::TYPES), 404);

        return self::TYPES[$kind];
    }

    public function index(Request $request, string $kind): View
    {
        $type = $this->type($kind);
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $query = trim($filter['q'] ?? '');

        $entries = ContentEntry::query()->where('type', $type)
            ->when($query !== '', fn ($builder) => $builder->where('title', 'like', '%'.$query.'%'))
            ->with('terms')->orderByDesc('updated_at')->paginate(10)->withQueryString();

        return view('workspace.admin.editorial-index', compact('entries', 'kind', 'query'));
    }

    public function create(string $kind): View
    {
        $type = $this->type($kind);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => new ContentEntry(['type' => $type]), 'editing' => false,
            'categories' => $kind === 'posts' ? PostTerm::where('kind','category')->orderBy('name')->get() : collect(),
            'learningCategories' => $kind === 'posts' ? Category::published()->orderBy('sort_order')->get() : collect(),
            'tags' => $kind === 'posts' ? PostTerm::where('kind','tag')->orderBy('name')->get() : collect(),
        ]);
    }

    public function slug(Request $request, string $kind): JsonResponse
    {
        $this->type($kind);
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);

        return response()->json(['slug' => Str::slug($data['title'])]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $type = $this->type($kind);
        $intent = $this->publicationIntent($request, $type);
        $validated = $this->validateEntry($request, $type);
        $terms = $this->validatedTerms($request, $type);

        $entry = DB::transaction(function () use ($validated, $type, $request, $terms, $intent): ContentEntry {
            $entry = ContentEntry::create($validated + [
                'type' => $type,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->saveContent($request, $entry, $validated, $terms, $intent === 'publish' ? 'published' : 'draft');

            return $entry;
        });

        return redirect()->route('admin.editorial.edit', ['kind' => $kind, 'entry' => $entry])
            ->with('status', $entry->status === 'published' ? 'Published.' : 'Draft saved.');
    }

    public function edit(string $kind, ContentEntry $entry): View
    {
        abort_unless($entry->type === $this->type($kind), 404);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => $entry, 'editing' => true,
            'categories' => $kind === 'posts' ? PostTerm::where('kind','category')->orderBy('name')->get() : collect(),
            'learningCategories' => $kind === 'posts' ? Category::where(fn ($query) => $query->published()->orWhereKey($entry->category_id))->orderBy('sort_order')->get() : collect(),
            'tags' => $kind === 'posts' ? PostTerm::where('kind','tag')->orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($entry->type === $this->type($kind), 404);
        $intent = $this->publicationIntent($request, $entry->type);
        $validated = $this->validateEntry($request, $entry->type, $entry);
        $terms = $this->validatedTerms($request, $entry->type);

        $status = DB::transaction(function () use ($entry, $validated, $terms, $request, $intent): string {
            $current = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $editableDraft = in_array($current->status, ['draft', 'rejected'], true);
            abort_unless($editableDraft || ($current->status === 'published' && in_array($intent, ['save', 'draft'], true))
                || ($current->status === 'pending_review' && $intent === 'publish'), 409);

            $next = match ($intent) {
                'publish' => 'published',
                'draft' => 'draft',
                default => $current->status,
            };

            $this->saveContent($request, $current, $validated, $terms, $next);

            return $next;
        });

        return redirect()->route('admin.editorial.edit', ['kind' => $kind, 'entry' => $entry])
            ->with('status', $status === 'published' ? 'Published changes saved.' : 'Draft saved.');
    }

    private function publicationIntent(Request $request, string $type): ?string
    {
        $validated = $request->validate([
            'publication_intent' => ['sometimes', 'required', Rule::in(['draft', 'publish', 'save'])],
        ]);
        $intent = $validated['publication_intent'] ?? null;
        abort_if($intent !== null && !in_array($type, ContentEntry::DIRECT_PUBLICATION_TYPES, true), 422);

        return $intent;
    }

    private function saveContent(Request $request, ContentEntry $entry, array $data, array $terms, string $status): void
    {
        $actor = $request->user();
        $before = $entry->status;
        $entry->fill($data);

        if ($status === 'published' && $before !== 'published') {
            $entry->published_at = now();
        }
        $entry->status = $status;

        if ($entry->type === 'policy' && $before === 'published' && $entry->isDirty()) {
            $entry->policyRevisions()->create([
                'created_by' => $entry->getOriginal('updated_by') ?? $entry->created_by,
                'snapshot' => collect($entry->getRawOriginal())->only([
                    'title', 'slug', 'summary', 'body', 'body_html', 'seo_title', 'seo_description',
                    'featured_kind', 'featured_media_id', 'featured_value', 'featured_alt',
                    'published_at', 'policy_version', 'policy_effective_on',
                ])->all(),
            ]);
        }

        EditorMedia::claim($entry->body_html, $actor, 'editorial', $entry->id);
        $entry->updated_by = $actor->id;
        $entry->save();
        if ($entry->type === 'post') {
            $entry->terms()->sync($terms);
        }
        if ($before !== $status && ($before === 'published' || $status === 'published')) {
            ApprovalAudit::record('editorial_'.$entry->type, $entry->id, $actor,
                $status === 'published' ? 'published' : 'unpublished', $before, $status);
        }
    }

    public function policyHistory(ContentEntry $entry): View
    {
        abort_unless($entry->type === 'policy', 404);
        $revisions = $entry->policyRevisions()->with('author')->latest('id')->paginate(10);

        return view('workspace.admin.policy-history', compact('entry', 'revisions'));
    }

    public function policyRevision(ContentEntry $entry, PolicyRevision $revision): View
    {
        abort_unless($entry->type === 'policy' && $revision->content_entry_id === $entry->id, 404);
        $snapshot = new ContentEntry($revision->snapshot);

        return view('workspace.admin.policy-history', compact('entry', 'revision', 'snapshot'));
    }

    public function preview(string $kind, ContentEntry $entry): View
    {
        abort_unless($entry->type === $this->type($kind), 404);
        return view('workspace.admin.editorial-preview', compact('entry','kind'));
    }

    public function destroy(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($entry->type === $this->type($kind), 404);
        DB::transaction(function () use ($request, $entry): void {
            $entry = ContentEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $before = $entry->status;
            abort_unless(in_array($before, ['draft', 'rejected', 'published'], true), 409);
            $entry->delete();
            ApprovalAudit::record('editorial_'.$entry->type, $entry->id,
                $request->user(), 'deleted', $before, 'deleted');
        });
        return redirect()->route('admin.editorial.index', ['kind'=>$kind])
            ->with('status', 'Entry removed from listings and public pages.');
    }

    private function validatedTerms(Request $request, string $type): array
    {
        if ($type !== 'post') return [];
        $data = $request->validate([
            'category_ids' => ['sometimes','array','max:40'],
            'category_ids.*' => ['integer',Rule::exists('post_terms','id')->where('kind','category')],
            'tag_ids' => ['sometimes','array','max:60'],
            'tag_ids.*' => ['integer',Rule::exists('post_terms','id')->where('kind','tag')],
        ]);
        return array_values(array_unique(array_merge($data['category_ids'] ?? [], $data['tag_ids'] ?? [])));
    }

    private function validateEntry(Request $request, string $type, ?ContentEntry $entry = null): array
    {
        $request->validate([
            'slug' => ['nullable', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:200'],
            'body_html' => ['nullable', 'string', 'max:120000'],
        ]);
        $slug = Str::slug((string) $request->input('slug', ''));

        if ($slug === '') {
            $slug = Str::slug((string) $request->input('title', ''));
        }

        $clean = EditorHtml::clean($request->input('body_html'));
        $request->merge(['slug' => $slug, 'body_html' => $clean]);
        if ($clean !== null && trim((string) $request->input('body', '')) === '') {
            $request->merge(['body' => EditorHtml::text($clean)]);
        }

        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:200', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('content_entries', 'slug')
                    ->where('type', $type)->ignore($entry?->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:25000'],
            'body_html' => ['nullable', 'string', 'max:120000'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            ...($type === 'post' ? ['category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')]] : []),
            ...($type === 'policy' ? [
                'policy_version' => ['sometimes', 'nullable', 'string', 'max:40'],
                'policy_effective_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            ] : []),
        ]);

        return in_array($type, ContentEntry::DIRECT_PUBLICATION_TYPES, true)
            ? $data + FeaturedMedia::validate($request)
            : $data;
    }
}
