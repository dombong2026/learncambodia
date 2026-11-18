<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonComment;
use App\Models\LessonSupportMessage;
use App\Models\User;
use App\Support\InstructorAccess;
use App\Support\LearningAccess;
use App\Support\WorkspaceNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class LessonConversationController extends Controller
{
    public function comment(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        LearningAccess::lesson(LearningAccess::course($request->user(), $enrollment), $lesson);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:4000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $parent = isset($data['parent_id'])
            ? LessonComment::where('lesson_id', $lesson->id)->whereNull('parent_id')->findOrFail($data['parent_id'])
            : null;
        $comment = LessonComment::create([
            'lesson_id' => $lesson->id,
            'author_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'body' => $data['comment'],
        ]);

        $thread = $parent?->id ?? $comment->id;

        return $this->returnToLesson($request, $enrollment, $lesson, ['tab' => 'comment', 'thread' => $thread], 'thread-'.$thread);
    }

    public function react(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson, LessonComment $comment): RedirectResponse
    {
        LearningAccess::lesson(LearningAccess::course($request->user(), $enrollment), $lesson);
        abort_unless($comment->lesson_id === $lesson->id, 404);
        $data = $request->validate(['kind' => ['required', Rule::in(LessonComment::REACTIONS)], 'active' => ['required', 'boolean']]);
        $vote = ['comment_id' => $comment->id, 'user_id' => $request->user()->id, 'kind' => $data['kind']];
        if ($request->boolean('active')) {
            DB::table('lesson_comment_reactions')->upsert([$vote], ['comment_id', 'user_id', 'kind'], ['kind']);
        } else {
            DB::table('lesson_comment_reactions')->where($vote)->delete();
        }

        $thread = $comment->parent_id ?? $comment->id;

        return $this->returnToLesson($request, $enrollment, $lesson, ['tab' => 'comment', 'thread' => $thread], 'thread-'.$thread);
    }

    public function send(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        $course = LearningAccess::course($request->user(), $enrollment);
        LearningAccess::lesson($course, $lesson);
        $data = $request->validate([
            'instructor_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:4000'],
        ]);
        $instructor = User::where('role', User::ROLE_INSTRUCTOR)->findOrFail($data['instructor_id']);
        InstructorAccess::check($instructor, $course);

        $thread = LessonSupportMessage::thread($enrollment, $lesson, $instructor);
        $first = ! $thread->exists();
        $thread->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'instructor_id' => $instructor->id,
            'author_id' => $request->user()->id,
            'body' => $data['message'],
        ]);
        if ($first) {
            WorkspaceNotifications::send($instructor->id, 'New private lesson support conversation',
                'instructor.lesson-support.show', [$enrollment, $lesson]);
        }

        return $this->returnToLesson($request, $enrollment, $lesson, ['tab' => 'support', 'instructor' => $instructor->id]);
    }

    public function index(Request $request): View
    {
        $instructor = $request->user();
        $latestMessages = LessonSupportMessage::query()->where('instructor_id', $instructor->id)
            ->whereHas('enrollment', fn ($enrollments) => $enrollments
                ->where('status', 'approved')->whereHas('student', fn ($users) => $users->where('role', User::ROLE_STUDENT))
                ->whereHas('course', fn ($courses) => $courses->published()->whereHas('instructorAssignments',
                    fn ($assignments) => $assignments->where('instructor_id', $instructor->id)->where('status', 'active'))))
            ->selectRaw('MAX(id)')->groupBy('enrollment_id', 'lesson_id');

        $threads = LessonSupportMessage::whereIn('id', $latestMessages)
            ->with(['lesson:id,title', 'enrollment.course:id,title', 'enrollment.student:id,name'])
            ->latest('id')->paginate(25);

        return view('learning.instructor-support', compact('threads'));
    }

    public function show(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): View
    {
        $this->checkInstructor($request->user(), $enrollment, $lesson);
        $messages = LessonSupportMessage::thread($enrollment, $lesson, $request->user())
            ->with('author:id,name')->latest('id')->simplePaginate(30, ['*'], 'messages')->appends($request->except('fragment'));
        abort_if($messages->isEmpty() && $messages->onFirstPage(), 404);

        if ($request->boolean('fragment')) {
            return view('learning.partials.support-messages', compact('messages'));
        }

        return view('learning.instructor-support', compact('enrollment', 'lesson', 'messages'));
    }

    public function reply(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): RedirectResponse
    {
        $instructor = $request->user();
        $this->checkInstructor($instructor, $enrollment, $lesson);
        $thread = LessonSupportMessage::thread($enrollment, $lesson, $instructor);
        abort_unless($thread->exists(), 404);
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $thread->create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'instructor_id' => $instructor->id,
            'author_id' => $instructor->id,
            'body' => $data['message'],
        ]);

        WorkspaceNotifications::send($enrollment->student_id, 'Your instructor replied to a private lesson message',
            'account.lessons.watch', [$enrollment, $lesson, 'tab' => 'support', 'instructor' => $instructor->id]);

        return redirect()->route('instructor.lesson-support.show', [$enrollment, $lesson]);
    }

    private function returnToLesson(
        Request $request, CourseEnrollment $enrollment, CourseLesson $lesson, array $query, string $section = 'lesson-section'
    ): RedirectResponse {
        $viewer = $request->input('viewer');
        if (is_string($viewer) && strlen($viewer) <= 30) {
            $query['viewer'] = $viewer;
        }

        return redirect()->to(route('account.lessons.watch', [$enrollment, $lesson] + $query).'#'.$section);
    }

    private function checkInstructor(User $instructor, CourseEnrollment $enrollment, CourseLesson $lesson): void
    {
        abort_unless($enrollment->status === 'approved' && $enrollment->student?->role === User::ROLE_STUDENT, 404);
        $course = Course::published()->findOrFail($enrollment->course_id);
        InstructorAccess::check($instructor, $course);
        LearningAccess::lesson($course, $lesson);
    }
}
