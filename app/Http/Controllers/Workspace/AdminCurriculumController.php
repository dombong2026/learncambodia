<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CurriculumLessonRevision;
use App\Models\EditorMedia;
use App\Support\EditorHtml;
use App\Support\LessonQuiz;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminCurriculumController extends Controller
{
    private function mutate(Course $course, callable $action): mixed
    {
        return DB::transaction(function () use ($course,$action) {
            $locked=Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(),409,
                'The curriculum is locked while pending approval or published.');
            return $action($locked);
        });
    }

    private function saved(Request $request, Course $course, string $message, array $selection = []): RedirectResponse
    {
        $parameters = ['course' => $course, 'step' => 'quizzes'];
        if ($request->input('continue') !== '1') {
            $parameters = ['course' => $course, 'step' => 'curriculum', ...$selection];
        }

        return redirect()->route('admin.course-builder.edit', $parameters)->with('status', $message);
    }

    private function module(Course $course,CourseModule $module): void
    {
        abort_unless($module->course_id===$course->id,404);
    }

    private function lesson(CourseModule $module,CourseLesson $lesson): void
    {
        abort_unless($lesson->course_module_id===$module->id,404);
    }

    private function moduleRules(Request $request): array
    {
        return $request->validate([
            'title'=>['required','string','max:180'],
            'summary'=>['nullable','string','max:700'],
            'position'=>['required','integer','between:1,999'],
        ]);
    }

    private function lessonRules(Request $request): array
    {
        $data = $request->validate([
            'title'=>['required','string','max:180'],
            'content'=>['nullable','string','max:50000'],
            'content_html'=>['nullable','string','max:200000'],
            'position'=>['required','integer','between:1,999'],
            'duration_minutes'=>['required','integer','between:1,600'],
            'is_preview'=>['required','boolean'],
        ]);

        if (filled($data['content_html'] ?? null)) {
            $data['content_html'] = EditorHtml::clean($data['content_html'], lesson: true);
            $data['content'] = EditorHtml::courseText($data['content_html']);
            if (blank($data['content']) && preg_match('/ data-youtube="[A-Za-z0-9_-]{11}"/', $data['content_html'] ?? '')) {
                $data['content'] = 'YouTube video';
            }
            if (blank($data['content']) && EditorHtml::sourceIds($data['content_html'], lesson: true) !== []) {
                $data['content'] = 'Lesson media';
            }
        }
        validator($data, ['content' => ['required', 'string', 'max:50000']])->validate();

        if ($request->has('lesson_quiz_present')) {
            $data['quiz_questions'] = LessonQuiz::validateQuestions($request);
        }

        return $data;
    }

    private function syncMedia(CourseLesson $lesson, int $editorId): void
    {
        $ids = EditorHtml::sourceIds($lesson->content_html, lesson: true);
        if ($ids !== []) {
            $existing = $lesson->media()->allRelatedIds();
            $allowed = EditorMedia::query()->whereIn('id', $ids)->where('context', 'lesson')
                ->where(fn ($media) => $media->where('uploaded_by', $editorId)->orWhereIn('id', $existing))
                ->orderBy('id')->lockForUpdate()->pluck('id');

            if ($allowed->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'content_html' => 'An inline file is unavailable. Remove it or upload it again.',
                ]);
            }
        }

        $lesson->media()->sync($ids);
    }

    public function reorderModules(Request $request, Course $course): JsonResponse
    {
        return $this->mutate($course, fn (Course $locked) =>
            $this->reorder($request, $locked->modules())
        );
    }

    public function reorderLessons(Request $request, Course $course, CourseModule $module): JsonResponse
    {
        return $this->mutate($course, function (Course $locked) use ($request, $module): JsonResponse {
            $module = $locked->modules()->findOrFail($module->id);

            return $this->reorder($request, $module->lessons(), $locked);
        });
    }

    private function reorder(Request $request, HasMany $siblings, ?Course $course = null): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'list', 'min:2', 'max:200'],
            'order.*' => ['required', 'integer', 'distinct', 'min:1'],
            'expected' => ['required', 'array', 'list', 'size:'.count($request->array('order'))],
            'expected.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);
        $items = $siblings->get()->keyBy('id');
        $expected = array_map('intval', $data['expected']);
        $order = array_map('intval', $data['order']);

        abort_unless($items->keys()->all() === $expected, 409, 'The curriculum order has changed. Refresh the page and try again.');
        if (array_diff($order, $expected) !== []) {
            throw ValidationException::withMessages(['order' => 'Choose items from this curriculum list.']);
        }

        foreach ($order as $index => $id) {
            $item = $items->get($id);
            $item->position = $index + 1;
            if ($item->isDirty('position')) {
                $item->save();
                if ($course !== null) {
                    CurriculumLessonRevision::capture($course, $item, $request->user(), 'reordered');
                }
            }
        }

        return response()->json(['order' => $order]);
    }

    public function storeModule(Request $request,Course $course): RedirectResponse
    {
        $data=$this->moduleRules($request);
        $module = $this->mutate($course,function($locked) use ($data): CourseModule {
            abort_if($locked->modules()->count()>=100,422,'Module count limit reached.');
            return $locked->modules()->create($data);
        });
        return $this->saved($request, $course, 'Curriculum module added.', ['module' => $module->id]);
    }

    public function updateModule(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $data=$this->moduleRules($request);
        $this->mutate($course,fn()=> $module->update($data));
        return $this->saved($request, $course, 'Module updated.', ['module' => $module->id, 'editor' => 'module']);
    }

    public function destroyModule(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $this->mutate($course,function($locked) use($request,$module): void {
            foreach($module->lessons as $lesson){
                CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'deleted');
            }
            $module->delete();
        });
        return $this->saved($request, $course, 'Module and its draft lessons deleted; revision history retained.');
    }

    public function storeLesson(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $data=$this->lessonRules($request);
        $lesson = $this->mutate($course,function($locked) use($request,$module,$data): CourseLesson {
            abort_if($module->lessons()->count()>=200,422,'Lesson count limit reached.');
            $lesson=$module->lessons()->create($data);
            $this->syncMedia($lesson, $request->user()->id);
            CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'created');
            return $lesson;
        });
        return $this->saved($request, $course, 'Lesson added and revision recorded.', ['module' => $module->id, 'editor' => 'lesson', 'lesson' => $lesson->id]);
    }

    public function updateLesson(Request $request,Course $course,CourseModule $module,CourseLesson $lesson): RedirectResponse
    {
        $this->module($course,$module);
        $this->lesson($module,$lesson);
        $data=$this->lessonRules($request);
        $this->mutate($course,function($locked) use($request,$lesson,$data): void {
            $lesson->refresh();
            if (!array_key_exists('content_html', $data) && $data['content'] !== $lesson->content) {
                $lesson->content_html = null;
            }
            $lesson->fill($data);
            if($lesson->isDirty()){
                $contentChanged = $lesson->isDirty('content_html');
                $lesson->save();
                if ($contentChanged) {
                    $this->syncMedia($lesson, $request->user()->id);
                }
                CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'updated');
            }
        });
        return $this->saved($request, $course, 'Lesson updated and revision recorded.', ['module' => $module->id, 'editor' => 'lesson', 'lesson' => $lesson->id]);
    }

    public function destroyLesson(Request $request,Course $course,CourseModule $module,CourseLesson $lesson): RedirectResponse
    {
        $this->module($course,$module);
        $this->lesson($module,$lesson);
        $this->mutate($course,function($locked) use($request,$lesson): void {
            CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'deleted');
            $lesson->delete();
        });
        return $this->saved($request, $course, 'Draft lesson deleted; revision history retained.', ['module' => $module->id]);
    }

    public function duplicateLesson(Request $request,Course $course,CourseModule $module,CourseLesson $lesson): RedirectResponse
    {
        $this->module($course,$module);
        $this->lesson($module,$lesson);
        $this->mutate($course,function($locked) use($request,$module,$lesson): void {
            abort_if($module->lessons()->count()>=200,422,'Lesson count limit reached.');
            $position=1+(int)$module->lessons()->max('position');
            abort_if($position>999,422,'Lesson order limit reached.');
            $copy=$module->lessons()->create([
                'title'=>mb_substr($lesson->title,0,160).' (copy)',
                'content'=>$lesson->content,'content_html'=>$lesson->content_html,
                'quiz_questions'=>$lesson->quiz_questions,'position'=>$position,
                'duration_minutes'=>$lesson->duration_minutes,'is_preview'=>false,
            ]);
            $copy->media()->attach($lesson->media()->allRelatedIds());
            CurriculumLessonRevision::capture($locked,$copy,$request->user(),'copied');
        });
        return $this->saved($request, $course, 'Draft lesson duplicated; independent review still required.', ['module' => $module->id]);
    }

    public function duplicateModule(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $this->mutate($course,function($locked) use($request,$module): void {
            abort_if($locked->modules()->count()>=100,422,'Module count limit reached.');
            $position=1+(int)$locked->modules()->max('position');
            abort_if($position>999,422,'Module order limit reached.');
            $copy=$locked->modules()->create([
                'title'=>mb_substr($module->title,0,160).' (copy)',
                'summary'=>$module->summary,'position'=>$position,
            ]);
            foreach($module->lessons as $lesson){
                $duplicate=$copy->lessons()->create([
                    'title'=>$lesson->title,'content'=>$lesson->content,'content_html'=>$lesson->content_html,
                    'quiz_questions'=>$lesson->quiz_questions,
                    'position'=>$lesson->position,'duration_minutes'=>$lesson->duration_minutes,
                    'is_preview'=>false,
                ]);
                $duplicate->media()->attach($lesson->media()->allRelatedIds());
                CurriculumLessonRevision::capture($locked,$duplicate,$request->user(),'copied');
            }
        });
        return $this->saved($request, $course, 'Draft module copied; full academic review remains required.', ['module' => $module->id]);
    }
}
