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
    private function mutate(Course $course, callable $action): void
    {
        DB::transaction(function () use ($course,$action): void {
            $locked=Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isMutable(),409,
                'The curriculum is locked while pending approval or published.');
            $action($locked);
        });
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
        $this->mutate($course,function($locked) use ($data): void {
            abort_if($locked->modules()->count()>=100,422,'Module count limit reached.');
            $locked->modules()->create($data);
        });
        return back()->with('status','Curriculum module added.');
    }

    public function updateModule(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $data=$this->moduleRules($request);
        $this->mutate($course,fn()=> $module->update($data));
        return back()->with('status','Module updated.');
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
        return back()->with('status','Module and its draft lessons deleted; revision history retained.');
    }

    public function storeLesson(Request $request,Course $course,CourseModule $module): RedirectResponse
    {
        $this->module($course,$module);
        $data=$this->lessonRules($request);
        $this->mutate($course,function($locked) use($request,$module,$data): void {
            abort_if($module->lessons()->count()>=200,422,'Lesson count limit reached.');
            $lesson=$module->lessons()->create($data);
            CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'created');
        });
        return back()->with('status','Lesson added and revision recorded.');
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
        return back()->with('status','Lesson updated and revision recorded.');
    }

    public function destroyLesson(Request $request,Course $course,CourseModule $module,CourseLesson $lesson): RedirectResponse
    {
        $this->module($course,$module);
        $this->lesson($module,$lesson);
        $this->mutate($course,function($locked) use($request,$lesson): void {
            CurriculumLessonRevision::capture($locked,$lesson,$request->user(),'deleted');
            $lesson->delete();
        });
        return back()->with('status','Draft lesson deleted; revision history retained.');
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
        return back()->with('status','Draft lesson duplicated; independent review still required.');
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
        return back()->with('status','Draft module copied; full academic review remains required.');
    }
}
