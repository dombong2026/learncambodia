<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\AppearanceRevision;
use App\Models\Course;
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

        $pendingRevision = AppearanceRevision::query()->where('section', $section)
            ->where('status', 'pending_review')->latest('id')->first();

        return view($section === 'home' ? 'workspace.admin.home-studio-settings' : 'workspace.admin.appearance-form', [
            'approvedVersion' => $section === 'home' ? (AppearanceRevision::approved()->where('section','home')->max('id') ?? 0) : 0,
            'pendingRevision' => $pendingRevision,
            'role' => 'admin', 'section' => $section, 'revision' => $revision,
            'settings' => array_replace(Appearance::approved($section), $revision?->settings ?? []),
            'fields' => Appearance::fields($section),
        ]);
    }

    public function activateHomeLayout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'home_layout' => ['required', 'in:classic,sunrise'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $activation = AppearanceRevision::create([
                'section' => 'home_layout',
                'settings' => ['home_layout' => $data['home_layout']],
                'status' => 'approved',
                'created_by' => $request->user()->id,
                'review_note' => 'Direct administrator homepage layout activation.',
            ]);

            AppearanceRevision::query()->approved()
                ->where('section', 'home_layout')
                ->where('id', '<', $activation->id)
                ->update(['status' => 'superseded']);

            ApprovalAudit::record(
                'appearance',
                $activation->id,
                $request->user(),
                'activate',
                null,
                'approved',
                'Homepage layout activated: '.$data['home_layout']
            );
        });

        return redirect()->route('admin.appearance.edit', 'home')
            ->with('status', ucfirst($data['home_layout']).' homepage is now active.');
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
            if (AppearanceRevision::query()->where('section', $section)
                ->where('status', 'pending_review')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'appearance' => 'A revision for this section is awaiting independent review. Your new draft was not saved.',
                ])->redirectTo(route('admin.appearance.edit', $section));
            }

            if ($section === 'home') {
                $source = AppearanceRevision::where('section','home')->where('status','draft')->latest('id')->lockForUpdate()->first()
                    ?? AppearanceRevision::approved()->where('section','home')->latest('id')->lockForUpdate()->first();
                if ((string)$request->input('home_settings_version') !== (string)($source?->id ?? 0)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['appearance'=>'Home appearance changed. Reload the page to save the current version.']);
                }
                $data['campaign_playlist'] = $source?->settings['campaign_playlist'] ?? '[]';
                $data['home_layout'] = $data['home_layout']
                    ?? $source?->settings['home_layout']
                    ?? Appearance::value('home', 'home_layout');

            }

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
