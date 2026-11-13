<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
use App\Models\CourseHomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\LessonProgress;
use App\Models\PracticeAttempt;
use App\Models\User;
use App\Support\WorkspaceMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function admin(): View
    {
        $insights = \App\Support\DashboardInsights::admin();
        $insights['showcase'] = \App\Support\DashboardShowcase::forRole('admin', $insights);
        return view('workspace.overview', $insights);
    }

    public function student(Request $request): View
    {
        $insights = \App\Support\DashboardInsights::student($request->user());
        $insights['showcase'] = \App\Support\DashboardShowcase::forRole('student', $insights, $request->user());
        return view('workspace.overview', $insights);
    }

    public function instructor(Request $request): View
    {
        $insights = \App\Support\DashboardInsights::instructor($request->user());
        $insights['showcase'] = \App\Support\DashboardShowcase::forRole('instructor', $insights, $request->user());
        return view('workspace.overview', $insights);
    }

    public function developer(Request $request): View
    {
        $insights = \App\Support\DashboardInsights::developer($request->user());
        $insights['showcase'] = \App\Support\DashboardShowcase::forRole('developer', $insights, $request->user());
        return view('workspace.overview', $insights);
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
