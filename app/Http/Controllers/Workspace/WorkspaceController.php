<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\User;
use App\Support\WorkspaceMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function admin(): View
    {
        return view('workspace.overview', [
            'role' => 'admin',
            'heading' => 'Administration Overview',
            'description' => 'Review school operations, published pathways and draft courses.',
            'stats' => [
                ['label' => 'Learning categories', 'value' => Category::count()],
                ['label' => 'Registered accounts', 'value' => User::count()],
                ['label' => 'Draft courses', 'value' => Course::where('status', 'draft')->count()],
                ['label' => 'Published courses', 'value' => Course::published()->count()],
            ],
        ]);
    }

    public function student(): View
    {
        return view('workspace.overview', [
            'role' => 'student',
            'heading' => 'My Learning Overview',
            'description' => 'Browse English learning categories, track your future studies, and explore CEFR.',
            'stats' => [
                ['label' => 'Learning categories', 'value' => Category::published()->count()],
                ['label' => 'CEFR levels', 'value' => CefrLevel::count()],
            ],
        ]);
    }

    public function developer(Request $request): View
    {
        return view('workspace.overview', [
            'role' => 'developer',
            'heading' => 'Developer Overview',
            'description' => 'Manage personal API credentials and browse the read-only API reference.',
            'stats' => [
                ['label' => 'Active tokens', 'value' => $request->user()->tokens()->where('expires_at', '>', now())->count()],
                ['label' => 'API endpoints', 'value' => 3],
            ],
        ]);
    }

    public function module(Request $request, string $section, string $item): View
    {
        $role = $request->user()->role;
        $entry = WorkspaceMenu::find($role, $section, $item);

        abort_unless($entry !== null, 404);

        // Taxonomy links are active read-only module previews; other modules
        // are explicitly marked unimplemented until their workflows are accepted.
        $categoryCode = $section === 'language-test' ? strtoupper($item) : null;
        $category = $categoryCode && strlen($categoryCode) > 2
            ? Category::where('code', $categoryCode)->first()
            : null;
        $level = $categoryCode && strlen($categoryCode) === 2
            ? CefrLevel::where('code', $categoryCode)->first()
            : null;

        return view('workspace.module', compact('role', 'entry', 'section', 'item', 'category', 'level'));
    }
}
