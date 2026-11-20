<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\LessonNote;
use App\Models\LessonComment;
use App\Models\LessonSupportMessage;
use App\Models\PracticeAttempt;
use App\Models\PublicProfile;
use App\Models\User;
use App\Support\AccountPreview;
use App\Support\LearningAccess;
use App\Support\LessonMaterials;
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
        return ContentEntry::query()->publishedArticles()
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
        $courses=Course::discoverable()->with(['category', 'featuredMedia'])->withCount('lessons')
            ->orderByDesc('published_at')->limit(6)->get();
        $categories=Category::visibleInCatalog()->orderBy('sort_order')->limit(8)->get();
        return view('learning.library',compact('search','articles','courses','categories'));
    }

    public function reading(Request $request): View
    {
        $search=$this->search($request);
        $articles=$this->articles($search)->orderByDesc('id')->paginate(9)->withQueryString();
        $categories=Category::visibleInCatalog()->orderBy('sort_order')->limit(8)->get();
        return view('learning.reading',compact('search','articles','categories'));
    }

    public function exams(Request $request): View
    {
        $search=$this->search($request);
        $courses=Course::discoverable()->where(fn ($query) => $query->whereHas('quizzes', fn ($quizzes) => $quizzes->has('questions'))
            ->orWhereHas('exams', fn ($exams) => $exams->where('published', true)))
            ->when($search!=='',fn($q)=>$q->where(fn($s)=>$s
                ->where('title','like','%'.$search.'%')
                ->orWhereHas('category',fn($c)=>$c->where('name','like','%'.$search.'%'))))
            ->with(['category', 'featuredMedia'])->withCount(['quizzes as practice_quizzes_count'=>
                fn($q)=>$q->has('questions'), 'exams as course_exams_count' => fn ($exams) => $exams->where('published', true)])
            ->orderBy('title')->paginate(9)->withQueryString();
        return view('learning.exams',compact('search','courses'));
    }

    public function exam(Course $course,Request $request): View
    {
        abort_unless(Course::discoverable()->whereKey($course->id)->exists(),404);
        $course->load('category')->loadCount('lessons');
        $quizzes=$course->quizzes()->has('questions')->withCount('questions')
            ->orderBy('position')->get();
        $exams = $course->exams()->where('published', true)->with('type')->get(['id', 'course_id', 'exam_type_id', 'title', 'time_limit']);
        abort_if($quizzes->isEmpty() && $exams->isEmpty(), 404);
        $student=$this->learner($request);
        $enrollment=$student ? $course->enrollments()->where('student_id',$student->id)
            ->where('status','approved')->first() : null;
        $related=Course::discoverable()->where('category_id',$course->category_id)
            ->whereKeyNot($course->id)->with(['category', 'featuredMedia'])->withCount('lessons')
            ->limit(4)->get();
        return view('learning.exam',compact('course','quizzes','enrollment','related','exams'));
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
            ->with(['category', 'featuredMedia'])->withCount('lessons')->orderBy('title')->limit(9)->get();
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
        $courses=Course::discoverable()->with(['category', 'featuredMedia'])->withCount('lessons')
            ->orderByDesc('published_at')->limit(6)->get();
        return view('learning.watch',compact('student','enrollments','courses'));
    }

    public function lesson(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): View
    {
        $student = $request->user();
        $course = LearningAccess::course($student, $enrollment);
        LearningAccess::lesson($course, $lesson);
        $tab = $request->query('tab', 'details');
        if (!in_array($tab, ['details', 'comment', 'resources', 'support'], true)) {
            $tab = 'details';
        }

        $messages = $instructors = collect();
        $instructor = null;
        if ($tab === 'support') {
            $assigned = $course->instructorAssignments()->where('status', 'active')->select('instructor_id');
            $instructors = User::where('role', User::ROLE_INSTRUCTOR)->whereIn('id', $assigned)
                ->orderBy('name')->orderBy('id')->get(['id', 'name']);
            $id = $request->integer('instructor');
            $instructor = $id ? $instructors->firstWhere('id', $id) : $instructors->first();
            abort_if($id && !$instructor, 404);
            if ($instructor) {
                $messages = LessonSupportMessage::thread($enrollment, $lesson, $instructor)
                    ->with('author:id,name')->latest('id')->simplePaginate(30, ['*'], 'messages')
                    ->appends($request->except('fragment'));
            }
            if ($instructor && $request->boolean('fragment')) {
                return view('learning.partials.support-messages', compact('messages'));
            }
        }

        $comments = null;
        if ($tab === 'comment') {
            $comments = LessonComment::where('lesson_id', $lesson->id)->whereNull('parent_id')
                ->withDiscussionState($student)
                ->with(['replies' => fn ($replies) => $replies->withDiscussionState($student)->orderBy('id')])
                ->when($request->integer('thread'), fn ($query, $id) => $query->orderByRaw('CASE WHEN lesson_comments.id = ? THEN 0 ELSE 1 END', [$id]))
                ->latest('id')->paginate(15, ['*'], 'discussion')->withQueryString();
        }

        if ($request->isMethod('GET')) {
            $enrollment->markStarted();
        }
        $completedIds = LearningAccess::curriculumProgress($course, $enrollment);
        $lessons = $course->modules->flatMap(fn ($module) => $module->lessons)->values();
        $position = $lessons->search(fn ($entry) => $entry->id === $lesson->id);
        $previous = $position > 0 ? $lessons->get($position - 1) : null;
        $next = $lessons->get($position + 1);

        $materials = LessonMaterials::for($lesson);
        if (filled($lesson->quiz_questions)) {
            $materials['viewers']['quiz'] = ['type' => 'quiz', 'label' => 'Lesson quiz'];
        }
        $exams = $course->exams()->where('published', true)->orderBy('title')
            ->get(['id', 'public_id', 'course_id', 'kind', 'title', 'attempt_limit', 'time_limit', 'instructions_html']);
        foreach ($exams as $exam) {
            $materials['viewers']['exam-'.$exam->id] = ['type' => 'exam', 'label' => $exam->title, 'exam' => $exam];
        }

        $viewerKey = $request->query('viewer', $materials['defaultViewer']);
        abort_unless(is_string($viewerKey) && isset($materials['viewers'][$viewerKey]), 404);
        $viewer = $materials['viewers'][$viewerKey];
        $quizCompletedIds = $enrollment->lessonQuizAttempts()->distinct()->pluck('lesson_id')->all();
        $quizHistory = $quizAttempt = $quizRetry = null;
        if ($viewerKey === 'quiz') {
            $query = $request->validate([
                'attempt' => ['nullable', 'integer', 'min:1'],
                'retry' => ['nullable', 'integer', 'min:1'],
            ]);
            $quizAttempts = $enrollment->lessonQuizAttempts()->where('lesson_id', $lesson->id)->latest('id');
            $quizHistory = (clone $quizAttempts)->simplePaginate(8, ['*'], 'quiz_page')->withQueryString();
            if (isset($query['retry'])) {
                $quizRetry = $quizAttempts->findOrFail($query['retry']);
                abort_if($quizRetry->missedQuestions() === [], 404);
            } elseif (isset($query['attempt'])) {
                $quizAttempt = $quizAttempts->findOrFail($query['attempt']);
            } elseif (!$request->boolean('start')) {
                $quizAttempt = $quizAttempts->first();
            }
        }
        $canSavePlace = ! $request->session()->has(AccountPreview::KEY);
        if ($canSavePlace && $request->isMethod('GET') && ! $request->boolean('fragment')) {
            $enrollment->rememberLesson($lesson);
        }
        $document = $viewer['type'] === 'document' ? LessonMaterials::document($viewer['file']) : [];
        $attempts = $viewer['type'] === 'exam' ? $enrollment->examAttempts()
            ->where('course_exam_id', $viewer['exam']->id)->latest('id')->get(['id', 'score', 'submitted_at', 'expires_at']) : collect();
        $courseResources = $tab === 'resources' ? $course->resources()->with('media')->get() : collect();
        $quizzes = $tab === 'resources' ? $course->quizzes()->has('questions')->get(['id', 'title']) : collect();
        $assignments = $tab === 'resources' ? CourseHomeworkAssignment::published()
            ->where('course_id', $course->id)->orderBy('due_at')->get(['id', 'title']) : collect();
        $studyNote = $tab === 'details' ? LessonNote::where('student_id', $student->id)->where('enrollment_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)->first() : null;

        return view('learning.lesson', compact(
            'course', 'lesson', 'enrollment', 'completedIds', 'previous', 'next', 'position',
            'materials', 'viewerKey', 'viewer', 'tab', 'document', 'attempts', 'quizzes', 'assignments',
            'studyNote', 'comments', 'instructors', 'instructor', 'messages', 'canSavePlace',
            'quizAttempt', 'quizRetry', 'quizHistory', 'quizCompletedIds', 'courseResources'
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
