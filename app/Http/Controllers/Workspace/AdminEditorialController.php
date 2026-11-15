<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\PostTerm;
use App\Models\EditorMedia;
use App\Support\EditorHtml;
use App\Support\ApprovalAudit;
use Illuminate\Support\Facades\DB;
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
        $this->type($kind);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => new ContentEntry(), 'editing' => false,
            'categories' => $kind === 'posts' ? PostTerm::where('kind','category')->orderBy('name')->get() : collect(),
            'tags' => $kind === 'posts' ? PostTerm::where('kind','tag')->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $type = $this->type($kind);
        $validated = $this->validateEntry($request, $type);

        $terms = $this->validatedTerms($request, $type);
        $entry = DB::transaction(function () use ($validated, $type, $request, $terms): ContentEntry {
            $entry = ContentEntry::create($validated + [
                'type' => $type,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            EditorMedia::claim($validated['body_html'] ?? null, $request->user(), 'editorial', $entry->id);
            if ($type === 'post') $entry->terms()->sync($terms);
            return $entry;
        });

        return redirect()->route('admin.editorial.edit', ['kind' => $kind, 'entry' => $entry])
            ->with('status', 'Draft created. It is not public.');
    }

    public function edit(string $kind, ContentEntry $entry): View
    {
        abort_unless($entry->type === $this->type($kind), 404);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => $entry, 'editing' => true,
            'categories' => $kind === 'posts' ? PostTerm::where('kind','category')->orderBy('name')->get() : collect(),
            'tags' => $kind === 'posts' ? PostTerm::where('kind','tag')->orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($entry->type === $this->type($kind), 404);
        abort_unless(in_array($entry->status, ['draft','rejected'], true), 409,
            'Submitted and published content cannot be edited.');

        $validated = $this->validateEntry($request, $entry->type, $entry);
        $terms = $this->validatedTerms($request, $entry->type);
        DB::transaction(function () use ($entry, $validated, $terms, $request): void {
            EditorMedia::claim($validated['body_html'] ?? null, $request->user(), 'editorial', $entry->id);
            $entry->update($validated + ['updated_by' => $request->user()->id]);
            if ($entry->type === 'post') $entry->terms()->sync($terms);
        });

        return back()->with('status', 'Draft updated. It remains unpublished.');
    }

    public function preview(string $kind, ContentEntry $entry): View
    {
        abort_unless($entry->type === $this->type($kind), 404);
        return view('workspace.admin.editorial-preview', compact('entry','kind'));
    }

    public function destroy(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($entry->type === $this->type($kind), 404);
        abort_unless(in_array($entry->status, ['draft','rejected','published'], true), 409);
        DB::transaction(function () use ($request, $entry): void {
            $before = $entry->status;
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
        $slug = Str::slug((string) $request->input('slug', ''));

        if ($slug === '') {
            $slug = Str::slug((string) $request->input('title', ''));
        }

        $clean = EditorHtml::clean($request->input('body_html'));
        $request->merge(['slug' => $slug, 'body_html' => $clean]);
        if ($clean !== null && trim((string) $request->input('body', '')) === '') {
            $request->merge(['body' => EditorHtml::text($clean)]);
        }

        return $request->validate([
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
        ]);
    }
}
