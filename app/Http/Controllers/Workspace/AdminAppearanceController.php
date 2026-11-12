<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Support\Appearance;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAppearanceController extends Controller
{
    public function index(): View
    {
        $sections = collect(array_keys(Appearance::SECTIONS))
            ->map(fn ($section) => [
                'key' => $section,
                'approved' => AppearanceRevision::approved()->where('section', $section)->exists(),
                'pending' => AppearanceRevision::where('section', $section)
                    ->where('status', 'pending_review')->exists(),
            ]);

        return view('workspace.admin.appearance-index', [
            'role' => 'admin', 'sections' => $sections,
        ]);
    }

    public function edit(string $section): View
    {
        Appearance::fields($section);

        $revision = AppearanceRevision::query()->where('section', $section)
            ->where('status', 'draft')->orderByDesc('id')->first();

        return view('workspace.admin.appearance-form', [
            'role' => 'admin', 'section' => $section, 'revision' => $revision,
            'settings' => $revision?->settings ?? Appearance::approved($section),
            'fields' => Appearance::fields($section),
        ]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $data = $request->validate(Appearance::rules($section));
        foreach (Appearance::fields($section) as $field => $kind) {
            if (str_starts_with($kind, 'text:')) {
                $data[$field] = (string) ($data[$field] ?? '');
            }
        }

        DB::transaction(function () use ($section, $data, $request): void {
            abort_if(AppearanceRevision::query()->where('section', $section)
                ->where('status', 'pending_review')->exists(), 409,
                'Resolve the pending appearance review before drafting another version.');

            AppearanceRevision::create([
                'section' => $section,
                'settings' => $data,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('admin.appearance.edit', $section)
            ->with('status', 'Appearance draft saved; public pages are unchanged.');
    }

    public function submit(Request $request, AppearanceRevision $revision): RedirectResponse
    {
        DB::transaction(function () use ($request, $revision): void {
            $locked = AppearanceRevision::query()->whereKey($revision->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'draft', 409);
            abort_if(AppearanceRevision::where('section', $locked->section)
                ->where('status', 'pending_review')->exists(), 409);

            $locked->update(['status' => 'pending_review', 'submitted_at' => now()]);
            ApprovalAudit::record('appearance', $locked->id, $request->user(),
                'submitted', 'draft', 'pending_review');
        });

        return redirect()->route('admin.appearance.index')
            ->with('status', 'Appearance changes submitted for independent review.');
    }
}
