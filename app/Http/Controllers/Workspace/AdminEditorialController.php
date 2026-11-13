<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
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
            ->orderByDesc('updated_at')->paginate(10)->withQueryString();

        return view('workspace.admin.editorial-index', compact('entries', 'kind', 'query'));
    }

    public function create(string $kind): View
    {
        $this->type($kind);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => new ContentEntry(), 'editing' => false,
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $type = $this->type($kind);
        $validated = $this->validateEntry($request, $type);

        $entry = ContentEntry::create($validated + [
            'type' => $type,
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.editorial.edit', ['kind' => $kind, 'entry' => $entry])
            ->with('status', 'Draft created. It is not public.');
    }

    public function edit(string $kind, ContentEntry $entry): View
    {
        abort_unless($entry->type === $this->type($kind), 404);

        return view('workspace.admin.editorial-form', [
            'kind' => $kind, 'entry' => $entry, 'editing' => true,
        ]);
    }

    public function update(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($entry->type === $this->type($kind), 404);

        $validated = $this->validateEntry($request, $entry->type, $entry);
        $entry->update($validated + ['updated_by' => $request->user()->id]);

        return back()->with('status', 'Draft updated. It remains unpublished.');
    }

    private function validateEntry(Request $request, string $type, ?ContentEntry $entry = null): array
    {
        $slug = Str::slug((string) $request->input('slug', ''));

        if ($slug === '') {
            $slug = Str::slug((string) $request->input('title', ''));
        }

        $request->merge(['slug' => $slug]);

        return $request->validate([
            'slug' => [
                'required', 'string', 'max:200', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('content_entries', 'slug')
                    ->where('type', $type)->ignore($entry?->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:25000'],
        ]);
    }
}
