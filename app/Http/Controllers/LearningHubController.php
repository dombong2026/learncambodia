<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\PracticeAttempt;
use App\Models\PublicProfile;
use App\Models\User;
use App\Support\LearningAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class LearningHubController extends Controller
{
    private function search(Request $request): string
    {
        $data=$request->validate(['q'=>['nullable','string','max:80']]);
        return trim($data['q']??'');
    }

    private function articles(string $search='')
    {
        return ContentEntry::query()->where('type','post')->where('status','published')
            ->when($search!=='',fn($q)=>$q->where(fn($inner)=>$inner
                ->where('title','like','%'.$search.'%')
                ->orWhere('summary','like','%'.$search.'%')));
    }

    private function learner(Request $request): ?User
    {
        $user=$request->user();
        return $user?->role===User::ROLE_STUDENT ? $user : null;
    }

    public function library(Request $request): View
    {
        $search=$this->search($request);
        $articles=$this->articles($search)->orderByDesc('id')->paginate(9)->withQueryString();
        $courses=Course::discoverable()->with('category')->withCount('lessons')
            ->orderByDesc('published_at')->limit(6)->get();
        $categories=Category::published()->orderBy('sort_order')->limit(8)->get();
        return view('learning.library',compact('search','articles','courses','categories'));
    }

    public function reading(Request $request): View
    {
        $search=$this->search($request);
        $articles=$this->articles($search)->orderByDesc('id')->paginate(9)->withQueryString();
        $categories=Category::published()->orderBy('sort_order')->limit(8)->get();
        return view('learning.reading',compact('search','articles','categories'));
    }

    public function exams(Request $request): View
    {
        $search=$this->search($request);
        $courses=Course::discoverable()->whereHas('quizzes',fn($q)=>$q->has('questions'))
            ->when($search!=='',fn($q)=>$q->where(fn($s)=>$s
                ->where('title','like','%'.$search.'%')
                ->orWhereHas('category',fn($c)=>$c->where('name','like','%'.$search.'%'))))
            ->with('category')->withCount(['quizzes as practice_quizzes_count'=>
                fn($q)=>$q->has('questions')])
            ->orderBy('title')->paginate(9)->withQueryString();
        return view('learning.exams',compact('search','courses'));
    }

    public function exam(Course $course,Request $request): View
    {
        abort_unless(Course::discoverable()->whereKey($course->id)->exists(),404);
        $course->load('category')->loadCount('lessons');
        $quizzes=$course->quizzes()->has('questions')->withCount('questions')
            ->orderBy('position')->get();
        abort_if($quizzes->isEmpty(),404);
        $student=$this->learner($request);
        $enrollment=$student ? $course->enrollments()->where('student_id',$student->id)
            ->where('status','approved')->first() : null;
        $related=Course::discoverable()->where('category_id',$course->category_id)
            ->whereKeyNot($course->id)->with('category')->withCount('lessons')
            ->limit(4)->get();
        return view('learning.exam',compact('course','quizzes','enrollment','related'));
    }

    public function recommendations(Request $request): View
    {
        $student=$this->learner($request);
        $enrollments=$student
            ? CourseEnrollment::query()->where('student_id',$student->id)
                ->whereHas('course',fn($q)=>$q->published())
                ->with('course.category')->orderByDesc('id')->limit(12)->get()
            : collect();
        $courseIds=$enrollments->pluck('course_id')->all();
        $categoryIds=$enrollments->pluck('course.category_id')->filter()->unique()->values()->all();
        $suggestions=Course::discoverable()
            ->when($courseIds!==[],fn($q)=>$q->whereNotIn('id',$courseIds))
            ->when($categoryIds!==[],fn($q)=>$q->whereIn('category_id',$categoryIds))
            ->with('category')->withCount('lessons')->orderBy('title')->limit(9)->get();
        $completedLessons=$student ? LessonProgress::query()
            ->whereNotNull('completed_at')->whereHas('enrollment',fn($q)=>$q
                ->where('student_id',$student->id))->count() : 0;
        $attempts=$student ? PracticeAttempt::query()->whereHas('enrollment',fn($q)=>$q
                ->where('student_id',$student->id))->with('quiz.course')
                ->orderByDesc('submitted_at')->limit(5)->get() : collect();
        return view('learning.recommendations',compact(
            'student','enrollments','suggestions','completedLessons','attempts'
        ));
    }

    public function leaderboard(Request $request): View
    {
        $student=$this->learner($request);
        $approvedCount=$student ? CourseEnrollment::query()
            ->where('student_id',$student->id)->where('status','approved')->count() : 0;
        $completeCount=$student ? LessonProgress::query()
            ->whereNotNull('completed_at')->whereHas('enrollment',fn($q)=>$q
                ->where('student_id',$student->id))->count() : 0;
        $practiceCount=$student ? PracticeAttempt::query()
            ->whereHas('enrollment',fn($q)=>$q->where('student_id',$student->id))->count() : 0;
        $passedCount=$student ? PracticeAttempt::query()->where('passed',true)
            ->whereHas('enrollment',fn($q)=>$q->where('student_id',$student->id))->count() : 0;
        $milestones=[
            ['icon'=>'book-open','label'=>'First approved enrollment','earned'=>$approvedCount>0],
            ['icon'=>'check-check','label'=>'First recorded lesson','earned'=>$completeCount>0],
            ['icon'=>'clipboard-check','label'=>'First practice submission','earned'=>$practiceCount>0],
            ['icon'=>'award','label'=>'First passing practice quiz','earned'=>$passedCount>0],
        ];
        return view('learning.leaderboard',compact(
            'student','approvedCount','completeCount','practiceCount','passedCount','milestones'
        ));
    }

    public function watch(Request $request): View
    {
        $student=$this->learner($request);
        $enrollments=$student ? CourseEnrollment::query()->where('student_id',$student->id)
            ->where('status','approved')
            ->whereHas('course',fn($q)=>$q->published())
            ->with('course.modules.lessons')->orderByDesc('id')->limit(8)->get() : collect();
        $courses=Course::discoverable()->with('category')->withCount('lessons')
            ->orderByDesc('published_at')->limit(6)->get();
        return view('learning.watch',compact('student','enrollments','courses'));
    }

    public function lesson(Request $request,CourseEnrollment $enrollment,CourseLesson $lesson): View
    {
        $course=LearningAccess::course($request->user(),$enrollment);
        LearningAccess::lesson($course,$lesson);
        $course->load('modules.lessons','quizzes');
        $completedIds=$enrollment->lessonProgress()->whereNotNull('completed_at')
            ->pluck('lesson_id')->all();
        $lessons=$course->modules->flatMap(fn($m)=>$m->lessons)->values();
        $position=$lessons->search(fn($entry)=>$entry->id===$lesson->id);
        $previous=$position!==false && $position>0 ? $lessons[$position-1] : null;
        $next=$position!==false && $position+1<$lessons->count() ? $lessons[$position+1] : null;
        return view('learning.lesson',compact(
            'course','lesson','enrollment','completedIds','previous','next'
        ));
    }

    public function studio(Request $request): View
    {
        $user=$request->user();
        $instructor=$user?->role===User::ROLE_INSTRUCTOR ? $user : null;
        $assignments=$instructor ? CourseInstructorAssignment::query()
            ->where('instructor_id',$instructor->id)->where('status','active')
            ->whereHas('course',fn($q)=>$q->published())->with('course')
            ->orderByDesc('assigned_at')->limit(8)->get() : collect();
        $homework=$instructor ? \App\Models\CourseHomeworkAssignment::query()
            ->where('author_id',$instructor->id)->with('course')
            ->orderByDesc('id')->limit(8)->get() : collect();
        return view('learning.studio',compact('instructor','assignments','homework'));
    }
}
