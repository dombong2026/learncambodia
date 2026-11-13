<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCurriculumController extends Controller
{
    private function mutate(Course $course, callable $action): void
    {
        DB::transaction(function () use ($course, $action): void {
            $locked = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(), 409,
                'The curriculum is locked while pending approval or published.');
            $action($locked);
        });
    }

    private function module(Course $course, CourseModule $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    private function lesson(CourseModule $module, CourseLesson $lesson): void
    {
        abort_unless($lesson->course_module_id === $module->id, 404);
    }

    private function moduleRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:700'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
    }

    private function lessonRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:50000'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'is_preview' => ['required', 'boolean'],
        ]);
    }

    public function storeModule(Request $request, Course $course): RedirectResponse
    {
        $data = $this->moduleRules($request);
        $this->mutate($course, fn ($locked) => $locked->modules()->create($data));

        return back()->with('status', 'Curriculum module added.');
    }

    public function updateModule(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->module($course, $module);
        $data = $this->moduleRules($request);
        $this->mutate($course, fn () => $module->update($data));

        return back()->with('status', 'Module updated.');
    }

    public function destroyModule(Course $course, CourseModule $module): RedirectResponse
    {
        $this->module($course, $module);
        $this->mutate($course, fn () => $module->delete());

        return back()->with('status', 'Module and its draft lessons deleted.');
    }

    public function storeLesson(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->module($course, $module);
        $data = $this->lessonRules($request);
        $this->mutate($course, fn () => $module->lessons()->create($data));

        return back()->with('status', 'Lesson added.');
    }

    public function updateLesson(Request $request, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        $this->module($course, $module);
        $this->lesson($module, $lesson);
        $data = $this->lessonRules($request);
        $this->mutate($course, fn () => $lesson->update($data));

        return back()->with('status', 'Lesson updated.');
    }

    public function destroyLesson(Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        $this->module($course, $module);
        $this->lesson($module, $lesson);
        $this->mutate($course, fn () => $lesson->delete());

        return back()->with('status', 'Draft lesson deleted.');
    }
}
