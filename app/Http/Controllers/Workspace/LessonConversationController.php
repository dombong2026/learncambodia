<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
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
        $instructor = User::where('role', User::ROLE_INSTRUCTOR)->where('account_status', 'active')
            ->findOrFail($data['instructor_id']);
        InstructorAccess::check($instructor, $course);

        $this->writeMessage($enrollment, $lesson, $instructor, $request->user(), $data['message']);

        return $this->returnToLesson($request, $enrollment, $lesson, [
            'tab' => 'support', 'instructor' => $instructor->id, 'lang' => app()->getLocale(),
        ])->with('status', __('course_support.sent'));
    }

    public function index(Request $request): View
    {
        $instructor = $request->user();
        $latestMessages = LessonSupportMessage::query()->where('instructor_id', $instructor->id)
            ->whereHas('enrollment', fn ($enrollments) => $enrollments
                ->where('status', 'approved')->whereHas('student', fn ($users) => $users
                    ->where('role', User::ROLE_STUDENT)->where('account_status', 'active'))
                ->whereHas('course', fn ($courses) => $courses->published()->whereHas('instructorAssignments',
                    fn ($assignments) => $assignments->where('instructor_id', $instructor->id)->where('status', 'active'))))
            ->selectRaw('MAX(id)')->groupBy('enrollment_id');

        $threads = LessonSupportMessage::whereIn('id', $latestMessages)
            ->with(['lesson:id,title', 'enrollment.course:id,title', 'enrollment.student:id,name'])
            ->latest('id')->paginate(25);

        return view('learning.instructor-support', compact('threads'));
    }

    public function show(Request $request, CourseEnrollment $enrollment, CourseLesson $lesson): View
    {
        $this->checkInstructor($request->user(), $enrollment, $lesson);
        $messages = LessonSupportMessage::thread($enrollment, $request->user())
            ->with(['author:id,name', 'lesson:id,title'])->latest('id')->simplePaginate(30, ['*'], 'messages')->appends($request->except('fragment'));
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
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $this->writeMessage($enrollment, $lesson, $instructor, $instructor, $data['message']);

        return to_route('instructor.lesson-support.show', [$enrollment, $lesson, 'lang' => app()->getLocale()])
            ->with('status', __('course_support.sent'));
    }

    private function writeMessage(
        CourseEnrollment $enrollment, CourseLesson $lesson, User $instructor, User $author, string $body
    ): void {
        DB::transaction(function () use ($enrollment, $lesson, $instructor, $author, $body): void {
            $locked = CourseEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($locked->status === 'approved', 403);
            $assignment = CourseInstructorAssignment::query()
                ->where('course_id', $locked->course_id)->where('instructor_id', $instructor->id)
                ->where('status', 'active')->lockForUpdate()->first();
            abort_unless($assignment, 403);

            $thread = LessonSupportMessage::thread($locked, $instructor);
            $reply = $author->id === $instructor->id;
            abort_if($reply && !$thread->exists(), 404);
            $thread->create([
                'enrollment_id' => $locked->id,
                'lesson_id' => $lesson->id,
                'instructor_id' => $instructor->id,
                'author_id' => $author->id,
                'body' => $body,
            ]);

            if ($reply) {
                WorkspaceNotifications::send($locked->student_id, __('course_support.notification_reply'),
                    'account.lessons.watch', [$locked, $lesson, 'tab' => 'support', 'instructor' => $instructor->id]);
            } else {
                WorkspaceNotifications::send($instructor->id, __('course_support.notification_message'),
                    'instructor.lesson-support.show', [$locked, $lesson]);
            }
        });
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
        abort_unless($enrollment->status === 'approved' && $enrollment->student?->role === User::ROLE_STUDENT
            && $enrollment->student->isActiveAccount(), 404);
        $course = Course::published()->findOrFail($enrollment->course_id);
        InstructorAccess::check($instructor, $course);
        LearningAccess::lesson($course, $lesson);
    }
}
