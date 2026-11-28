<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\LibraryItem;
use App\Models\LibraryLoan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class StudentLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $id = $this->studentId($request);
        $saved = LibraryItem::published()->with('file')->whereHas('savedBy', fn ($users) => $users->whereKey($id))
            ->latest('id')->paginate(12, pageName: 'saved_page')->withQueryString();
        $loans = LibraryLoan::with('item')->where('user_id', $id)
            ->orderByRaw('CASE WHEN returned_at IS NULL AND due_at > ? THEN 0 ELSE 1 END', [now()])
            ->latest('id')->paginate(10, pageName: 'loan_page')->withQueryString();

        return view('workspace.student.library', compact('saved', 'loans'));
    }

    public function save(Request $request, LibraryItem $item): RedirectResponse
    {
        $id = $this->studentId($request);
        abort_unless($item->status === 'published', 404);
        $now = now();
        DB::table('library_saves')->upsert([
            'user_id' => $id, 'library_item_id' => $item->id, 'created_at' => $now, 'updated_at' => $now,
        ], ['user_id', 'library_item_id'], ['updated_at']);

        return back()->with('status', 'Saved to My Library.');
    }

    public function unsave(Request $request, LibraryItem $item): RedirectResponse
    {
        $item->savedBy()->detach($this->studentId($request));

        return back()->with('status', 'Removed from saved items.');
    }

    public function borrow(Request $request, LibraryItem $item): RedirectResponse
    {
        $id = $this->studentId($request);
        $loan = DB::transaction(function () use ($item, $id): LibraryLoan {
            $item = LibraryItem::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->status === 'published' && $item->access === 'loan', 404);
            $existing = $item->loans()->active()->where('user_id', $id)->first();
            if ($existing) {
                return $existing;
            }
            if ($item->loans()->active()->count() >= $item->copies) {
                throw ValidationException::withMessages(['loan' => 'All digital copies are checked out. Please try again later.']);
            }

            return $item->loans()->create(['user_id' => $id, 'due_at' => now()->addDays($item->loan_days)]);
        }, 3);

        return back()->with('status', 'Borrowed until '.$loan->due_at->format('j M Y, H:i T').'.');
    }

    public function returnItem(Request $request, LibraryLoan $loan): RedirectResponse
    {
        abort_unless($loan->user_id === $this->studentId($request), 404);
        DB::transaction(function () use ($loan): void {
            LibraryItem::query()->lockForUpdate()->findOrFail($loan->library_item_id);
            LibraryLoan::whereKey($loan->id)->active()->update(['returned_at' => now()]);
        }, 3);

        return back()->with('status', 'Loan closed.');
    }

    private function studentId(Request $request): int
    {
        abort_unless(LibraryItem::isStudent($request->user()), 403);

        return $request->user()->id;
    }
}
