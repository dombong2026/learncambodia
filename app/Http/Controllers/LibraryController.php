<?php

namespace App\Http\Controllers;

use App\Models\LibraryItem;
use App\Models\LibraryLoan;
use App\Support\LibraryFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class LibraryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(array_keys(LibraryItem::KINDS))],
            'access' => ['nullable', Rule::in(array_keys(LibraryItem::ACCESS))],
            'sort' => ['nullable', Rule::in(['latest', 'title'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $items = LibraryItem::published()->matching($search)->with('file')
            ->withCount(['loans as active_loans_count' => fn ($loans) => $loans->active()])
            ->when($filters['kind'] ?? null, fn ($items, $kind) => $items->where('kind', $kind))
            ->when($filters['access'] ?? null, fn ($items, $access) => $items->where('access', $access))
            ->when(($filters['sort'] ?? '') === 'title', fn ($items) => $items->orderBy('title'))
            ->latest('id')->paginate(12)->withQueryString()->fragment('collection');

        if ($items->currentPage() > $items->lastPage()) {
            return redirect()->to($items->url($items->lastPage()));
        }

        return view('learning.library-catalog', compact('items', 'search', 'filters'));
    }

    public function show(Request $request, LibraryItem $item): View
    {
        $this->published($item);
        $student = LibraryItem::isStudent($request->user());
        $loan = $this->currentLoan($request, $item);
        $canAccess = $item->hasAccess($request->user(), $loan);
        $saved = $student && $item->savedBy()->whereKey($request->user()->id)->exists();
        $available = $item->access === 'loan' ? max(0, $item->copies - $item->loans()->active()->count()) : null;

        return view('learning.library-item', compact('item', 'student', 'loan', 'canAccess', 'saved', 'available'));
    }

    public function reader(Request $request, LibraryItem $item): View
    {
        abort_unless($this->published($item)->readable(), 404);
        $loan = $this->currentLoan($request, $item);
        abort_unless($item->hasAccess($request->user(), $loan), 403);

        return view('learning.library-reader', [
            'item' => $item,
            'source' => route('library.content', $item),
            'back' => route('library.show', $item),
            'download' => $item->canDownload() ? route('library.download', $item) : null,
            'accessUntil' => $loan?->due_at->toIso8601String(),
        ]);
    }

    public function content(Request $request, LibraryItem $item): Response
    {
        abort_unless($this->published($item)->readable(), 404);
        abort_unless($item->hasAccess($request->user(), $this->currentLoan($request, $item)), 403);

        return LibraryFiles::response($request, $item->file, inline: true);
    }

    public function download(Request $request, LibraryItem $item): Response
    {
        abort_unless($this->published($item)->canDownload()
            && $item->hasAccess($request->user(), $this->currentLoan($request, $item)), 403);

        return LibraryFiles::response($request, $item->file);
    }

    public function cover(Request $request, LibraryItem $item): Response
    {
        abort_unless($this->published($item)->cover, 404);

        return LibraryFiles::response($request, $item->cover, inline: true);
    }

    private function published(LibraryItem $item): LibraryItem
    {
        abort_unless($item->status === 'published', 404);

        return $item;
    }

    private function currentLoan(Request $request, LibraryItem $item): ?LibraryLoan
    {
        if ($item->access !== 'loan' || !LibraryItem::isStudent($request->user())) {
            return null;
        }

        return $item->loans()->active()->where('user_id', $request->user()->id)->first();
    }
}
