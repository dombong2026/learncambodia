<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\CourseReviewSnapshot;
use App\Models\EditorMedia;
use App\Support\CourseReadiness;
use App\Support\EditorHtml;
use App\Support\FeaturedMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminCourseController extends Controller
{
    private const STEPS = ['details', 'media', 'curriculum', 'quizzes', 'review'];

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

    public function edit(Request $request, Course $course): View
    {
        $step = $request->query('step');

        return $this->form($course, in_array($step, self::STEPS, true) ? $step : 'details');
    }

    private function form(Course $course, string $step = 'details'): View
    {
        if ($step === 'media' || $step === 'review') {
            $course->load('featuredMedia');
        }
        if ($step === 'quizzes') {
            $course->load(['quizzes' => fn ($query) => $query->withCount('questions')]);
        }
        if ($step === 'review') {
            $course->load('category')->loadCount(['modules', 'lessons', 'quizzes']);
        }
        if ($step === 'curriculum') {
            $course->load(['modules' => fn ($query) => $query->withCount('lessons')->withSum('lessons', 'duration_minutes')]);
        }

        $module = $step === 'curriculum' && $course->exists
            ? ($course->modules->firstWhere('id', request()->integer('module')) ?? $course->modules->first())
            : null;
        $editor = $step === 'curriculum' ? request()->query('editor') : null;
        $module?->load(['lessons' => fn ($query) => $query->select('id', 'course_module_id', 'title', 'position', 'duration_minutes')]);
        $lesson = $editor === 'lesson' ? $module?->lessons()->find(request()->integer('lesson')) : null;

        return view('workspace.admin.course-form', [
            'role' => 'admin',
            'course' => $course,
            'step' => $step,
            'module' => $module,
            'editor' => $editor,
            'lesson' => $lesson,
            'categories' => $step === 'details' ? Category::orderBy('sort_order')->get() : collect(),
            'levels' => $step === 'details' ? CefrLevel::orderBy('sort_order')->get() : collect(),
            'reviewChecklist' => $step === 'review' ? CourseReadiness::checklist($course) : [],
        ]);
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'summary' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:25000'],
            'description_html' => ['nullable', 'string', 'max:120000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'cefr_level' => ['nullable', 'string', Rule::exists('cefr_levels', 'code')],
            'delivery_mode' => ['required', Rule::in(['online','onsite','hybrid'])],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        validator($data, [
            'slug' => ['required', 'string', 'max:180',
                Rule::unique('courses', 'slug')->ignore($course?->id)],
        ])->validate();

        if (filled($data['description_html'] ?? null)) {
            $html = EditorHtml::clean($data['description_html']);
            $text = EditorHtml::courseText($html);
            validator(['description' => $text], ['description' => ['string', 'max:25000']])->validate();
            $data['description'] = $text !== '' ? $text : null;
            $data['description_html'] = $html;
        }

        return $data + FeaturedMedia::validate($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $course = DB::transaction(function () use ($data, $request): Course {
            $course = Course::create($data + [
                'status' => 'draft', 'created_by' => $request->user()->id,
            ]);
            $this->saveDescriptionMedia($course, $request->user()->id);

            return $course;
        });

        return redirect()->route('admin.course-builder.edit', $request->input('continue') === '1'
            ? ['course' => $course, 'step' => 'media'] : ['course' => $course])
            ->with('status', 'Draft course created. Add modules and lessons before review.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $this->validated($request, $course);

        $this->saveDraft($course, $data, $request->user()->id);

        if ($request->input('continue') === '1') {
            return redirect()->route('admin.course-builder.edit', ['course' => $course, 'step' => 'media'])
                ->with('status', 'Course details saved.');
        }

        return back()->with('status', 'Course details saved.');
    }

    public function media(Request $request, Course $course): RedirectResponse
    {
        $request->validate(['featured_kind' => ['required', 'string']]);
        $this->saveDraft($course, FeaturedMedia::validate($request));

        return redirect()->route('admin.course-builder.edit', [
            'course' => $course, 'step' => $request->input('continue') === '1' ? 'curriculum' : 'media',
        ])->with('status', 'Featured media saved.');
    }

    private function saveDraft(Course $course, array $data, ?int $editorId = null): void
    {
        DB::transaction(function () use ($course, $data, $editorId): void {
            $locked = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(), 409, 'Only draft or rejected courses may be edited.');
            if (array_key_exists('description', $data) && !array_key_exists('description_html', $data)
                && $data['description'] !== $locked->description) {
                $data['description_html'] = null;
            }
            $locked->update($data);
            if ($editorId !== null && array_key_exists('description_html', $data)) {
                $this->saveDescriptionMedia($locked, $editorId);
            }
        });
    }

    private function saveDescriptionMedia(Course $course, int $editorId): void
    {
        $ids = EditorHtml::sourceIds($course->description_html);
        if ($ids !== []) {
            $existing = $course->descriptionMedia()->allRelatedIds();
            $allowed = EditorMedia::query()->whereIn('id', $ids)->where('context', 'course')
                ->where(fn ($media) => $media->where('uploaded_by', $editorId)->orWhereIn('id', $existing))
                ->orderBy('id')->lockForUpdate()->pluck('id');

            if ($allowed->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'description_html' => 'An inline file is unavailable. Remove it or upload it again.',
                ]);
            }
        }
        $course->descriptionMedia()->sync($ids);
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

        return redirect()->route('admin.course-builder.edit', ['course' => $course, 'step' => 'review'])
            ->with('status', 'Course submitted for independent administrator review.');
    }
}
