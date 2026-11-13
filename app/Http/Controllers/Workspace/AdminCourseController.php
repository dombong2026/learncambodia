<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\CourseReviewSnapshot;
use App\Support\CourseReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseController extends Controller
{
    public function index(): View
    {
        return view('workspace.admin.courses', [
            'role' => 'admin',
            'courses' => Course::with('category')->withCount('modules')
                ->orderByDesc('id')->paginate(10),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Course());
    }

    public function edit(Course $course): View
    {
        return $this->form($course->load('modules.lessons'));
    }

    private function form(Course $course): View
    {
        return view('workspace.admin.course-form', [
            'role' => 'admin',
            'course' => $course,
            'categories' => Category::orderBy('sort_order')->get(),
            'levels' => CefrLevel::orderBy('sort_order')->get(),
            'reviewChecklist' => $course->exists ? CourseReadiness::checklist($course) : [],
        ]);
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'summary' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:25000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'cefr_level' => ['nullable', 'string', Rule::exists('cefr_levels', 'code')],
            'delivery_mode' => ['required', Rule::in(['online','onsite','hybrid'])],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        validator($data, [
            'slug' => ['required', 'string', 'max:180',
                Rule::unique('courses', 'slug')->ignore($course?->id)],
        ])->validate();

        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $course = Course::create($data + [
            'status' => 'draft', 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.course-builder.edit', $course)
            ->with('status', 'Draft course created. Add modules and lessons before review.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $this->validated($request, $course);

        DB::transaction(function () use ($course, $data): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(), 409, 'Only draft or rejected courses may be edited.');
            $locked->update($data);
        });

        return back()->with('status', 'Course details saved. Publication requires review.');
    }

    public function submit(Request $request, Course $course): RedirectResponse
    {
        DB::transaction(function () use ($request, $course): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(), 409);

            $checklist=CourseReadiness::checklist($locked);
            abort_unless(!in_array(false,$checklist,true), 422,
                'Complete the academic readiness checklist before review.');
            $fingerprint=CourseReadiness::fingerprint($locked);

            $from = $locked->status;
            $locked->update([
                'status' => 'pending_review',
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'published_at' => null,
            ]);
            CourseReviewSnapshot::create([
                'course_id'=>$locked->id,'submitted_by'=>$request->user()->id,
                'checklist'=>$checklist,'fingerprint'=>$fingerprint,'created_at'=>now(),
            ]);
            \App\Support\ApprovalAudit::record('course', $locked->id,
                $request->user(), 'submitted', $from, 'pending_review');
        });

        return redirect()->route('admin.course-builder.edit', $course)
            ->with('status', 'Course submitted for independent administrator review.');
    }
}
