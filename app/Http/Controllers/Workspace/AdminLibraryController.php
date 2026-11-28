<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\EditorMedia;
use App\Models\LibraryItem;
use App\Models\LibraryLoan;
use App\Support\LibraryFiles;
use App\Support\ManagedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(LibraryItem::STATUSES))],
        ]);
        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? '';
        $items = LibraryItem::matching($search)->with('file')
            ->withCount(['loans as active_loans_count' => fn ($loans) => $loans->active()])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('workspace.admin.library.index', compact('items', 'search', 'status'));
    }

    public function create(): View
    {
        return view('workspace.admin.library.form', ['item' => new LibraryItem]);
    }

    public function edit(LibraryItem $item): View
    {
        return view('workspace.admin.library.form', ['item' => $item->load('file')]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new LibraryItem);
    }

    public function update(Request $request, LibraryItem $item): RedirectResponse
    {
        return $this->save($request, $item);
    }

    public function loans(): View
    {
        $loans = LibraryLoan::with(['item', 'user:id,name'])
            ->orderByRaw('CASE WHEN returned_at IS NULL AND due_at > ? THEN 0 ELSE 1 END', [now()])
            ->latest('id')->paginate(25)->withQueryString();

        return view('workspace.admin.library.loans', compact('loans'));
    }

    public function reader(LibraryItem $item): View
    {
        abort_unless($item->readable(), 404);

        return view('learning.library-reader', [
            'item' => $item,
            'source' => route('admin.library.file', [$item, 'inline' => 1]),
            'back' => route('admin.library.edit', $item),
            'download' => route('admin.library.file', $item),
        ]);
    }

    public function file(Request $request, LibraryItem $item): Response
    {
        $inline = $request->boolean('inline');
        abort_if($inline && !$item->readable(), 404);

        return LibraryFiles::response($request, $item->file, inline: $inline);
    }

    private function save(Request $request, LibraryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'author' => ['nullable', 'string', 'max:150'],
            'topic' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', Rule::in(array_keys(LibraryItem::KINDS))],
            'access' => ['required', Rule::in(array_keys(LibraryItem::ACCESS))],
            'status' => ['required', Rule::in(array_keys(LibraryItem::STATUSES))],
            'allow_download' => ['nullable', 'boolean'],
            'loan_days' => ['required_if:access,loan', 'nullable', 'integer', 'between:1,90'],
            'copies' => ['required_if:access,loan', 'nullable', 'integer', 'between:1,1000'],
            'file' => [$item->exists ? 'nullable' : 'required', 'file', 'max:40960',
                'mimetypes:'.implode(',', array_keys(LibraryItem::FORMATS))],
            'cover' => ['nullable', 'image', 'max:5120', 'mimetypes:'.implode(',', LibraryFiles::COVER_MIMES)],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
        $upload = $request->file('file');
        $cover = $request->file('cover');
        $download = $data['access'] !== 'loan' && $request->boolean('allow_download');
        $this->validateAccess($data, $upload?->getMimeType() ?? $item->file?->mime, $download);

        $files = [];
        try {
            if ($upload) {
                $files['file'] = LibraryFiles::store($upload, array_keys(LibraryItem::FORMATS), 'file');
            }
            if ($cover) {
                $files['cover'] = LibraryFiles::store($cover, LibraryFiles::COVER_MIMES, 'cover');
            }
            $item = DB::transaction(function () use ($request, $item, $data, $files, $download): LibraryItem {
                $entry = $item->exists
                    ? LibraryItem::query()->lockForUpdate()->findOrFail($item->id)
                    : new LibraryItem(['copies' => 1, 'loan_days' => 14]);

                $this->validateAccess($data, $files['file']['mime'] ?? $entry->file?->mime, $download);

                $entry->public_id ??= (string) Str::ulid();
                $entry->fill([
                    ...collect($data)->only(['title', 'author', 'topic', 'description', 'kind', 'access', 'status'])->all(),
                    'allow_download' => $download, 'copies' => $data['copies'] ?? 1, 'loan_days' => $data['loan_days'] ?? 14,
                ]);

                if ($entry->access === 'loan' && $entry->loans()->active()->count() > $entry->copies) {
                    throw ValidationException::withMessages(['copies' => 'Copy count cannot be lower than the number of active loans.']);
                }

                foreach ($files as $relation => $file) {
                    $entry->{$relation}()->associate(EditorMedia::create($file + ['uploaded_by' => $request->user()->id]));
                }

                if (!$request->hasFile('cover') && $request->boolean('remove_cover')) {
                    $entry->cover()->dissociate();
                }

                $entry->save();

                return $entry;
            }, 3);
        } catch (Throwable $error) {
            foreach ($files as $file) {
                try {
                    ManagedFiles::disk()->delete($file['path']);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }
            throw $error;
        }

        return redirect()->route('admin.library.edit', $item)->with('status', 'Library item saved.');
    }

    private function validateAccess(array $data, ?string $mime, bool $download): void
    {
        if (in_array($mime, LibraryItem::READABLE, true)) {
            return;
        }
        if ($data['access'] === 'loan') {
            throw ValidationException::withMessages(['file' => 'Borrowable items require a PDF or TXT for the online reader.']);
        }
        if ($data['status'] === 'published' && !$download) {
            throw ValidationException::withMessages(['allow_download' => 'Enable downloads to publish this file format.']);
        }
    }
}
