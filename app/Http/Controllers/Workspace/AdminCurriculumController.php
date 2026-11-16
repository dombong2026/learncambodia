<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CurriculumLessonRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        return $request->validate([
            'title'=>['required','string','max:180'],
            'content'=>['required','string','max:50000'],
            'position'=>['required','integer','between:1,999'],
            'duration_minutes'=>['required','integer','between:1,600'],
            'is_preview'=>['required','boolean'],
        ]);
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
            $lesson->fill($data);
            if($lesson->isDirty()){
                $lesson->save();
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
                'content'=>$lesson->content,'position'=>$position,
                'duration_minutes'=>$lesson->duration_minutes,'is_preview'=>false,
            ]);
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
                    'title'=>$lesson->title,'content'=>$lesson->content,
                    'position'=>$lesson->position,'duration_minutes'=>$lesson->duration_minutes,
                    'is_preview'=>false,
                ]);
                CurriculumLessonRevision::capture($locked,$duplicate,$request->user(),'copied');
            }
        });
        return $this->saved($request, $course, 'Draft module copied; full academic review remains required.', ['module' => $module->id]);
    }
}
