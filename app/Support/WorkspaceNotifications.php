<?php

namespace App\Support;

use App\Models\AcademicEvidenceReview;
use App\Models\AppearanceRevision;
use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseHomeworkAssignment;
use App\Models\CourseInstructorAssignment;
use App\Models\HomeworkSubmission;
use App\Models\PublicProfile;
use App\Models\User;
use App\Notifications\WorkspaceNotice;
use Illuminate\Support\Facades\Notification;

final class WorkspaceNotifications
{
    public static function send(?int $userId, string $message, string $route, array $parameters = []): void
    {
        if ($userId !== null && ($user = User::find($userId))) {
            $user->notify(new WorkspaceNotice($message, route($route, $parameters, absolute: false)));
        }
    }

    public static function administrators(string $message, string $route, array $parameters = [], ?int $except = null): void
    {
        $notification = new WorkspaceNotice($message, route($route, $parameters, absolute: false));

        User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])->when($except, fn ($query) => $query->whereKeyNot($except))
            ->chunkById(100, fn ($users) => Notification::sendNow($users, $notification));
    }

    public static function approval(string $type, int $id, User $actor, string $action, string $status): void
    {
        if ($status === 'pending_review' || ($type === 'enrollment' && $action === 'requested')) {
            $destination = match ($type) {
                'course' => ['Course submitted for review', 'admin.reviews.index', ['focus' => 'courses']],
                'appearance' => ['Appearance change submitted for review', 'admin.reviews.index', ['focus' => 'appearance']],
                'enrollment' => ['New enrollment request', 'admin.reviews.index', ['focus' => 'enrollments']],
                'academic_evidence' => ['Academic evidence submitted for review', 'admin.academic.evidence', []],
                'public_profile' => ['Public profile submitted for review', 'admin.public-profiles.index', []],
                'homework_assignment' => ['Assignment submitted for review', 'admin.homework.reviews', []],
                'editorial_post' => ['Post submitted for review', 'admin.editorial.reviews', ['kind' => 'posts']],
                'editorial_faq' => ['FAQ submitted for review', 'admin.editorial.reviews', ['kind' => 'faqs']],
                'editorial_help_article' => ['Help article submitted for review', 'admin.editorial.reviews', ['kind' => 'help-articles']],
                default => null,
            };
            if ($destination) {
                self::administrators(...[...$destination, $actor->id]);
            }
            return;
        }

        if (in_array($action, ['approve', 'reject', 'approved', 'rejected', 'decline', 'grade', 'return'], true)) {
            $outcome = match ($action) {
                'grade' => 'graded', 'return' => 'returned', 'decline' => 'declined',
                'reject', 'rejected' => 'rejected', default => 'approved',
            };
            $course = $type === 'course' ? Course::find($id) : null;
            $destination = match ($type) {
                'course' => [$course?->created_by, "Course $outcome", 'admin.course-builder.edit', ['course' => $course]],
                'appearance' => [AppearanceRevision::find($id)?->created_by, "Appearance change $outcome", 'admin.reviews.index', ['focus' => 'appearance']],
                'enrollment' => [CourseEnrollment::find($id)?->student_id, "Enrollment request $outcome", 'account.enrollments.show', ['enrollment' => $id]],
                'homework_assignment' => [CourseHomeworkAssignment::find($id)?->author_id, "Assignment $outcome", 'instructor.homework.index', []],
                'homework_submission' => [HomeworkSubmission::find($id)?->enrollment?->student_id, "Homework $outcome", 'account.homework.result', ['submission' => $id]],
                'public_profile' => self::publicProfile($id, $outcome),
                'editorial_post', 'editorial_faq', 'editorial_help_article' => [
                    ContentEntry::find($id)?->created_by, "Editorial review $outcome", 'admin.editorial.index',
                    ['kind' => ['editorial_post' => 'posts', 'editorial_faq' => 'faqs', 'editorial_help_article' => 'help-articles'][$type]],
                ],
                default => null,
            };
            if ($destination) {
                self::send(...$destination);
            }
            return;
        }

        if ($type === 'academic_evidence' && in_array($action, ['reviewed', 'needs_revision'], true)) {
            self::send(AcademicEvidenceReview::find($id)?->requested_by,
                $action === 'reviewed' ? 'Academic evidence reviewed' : 'Academic evidence needs revision', 'admin.academic.evidence');
        }

        if ($type === 'instructor_assignment' && in_array($action, ['assigned', 'revoked'], true)) {
            $assignment = CourseInstructorAssignment::find($id);
            self::send($assignment?->instructor_id, $action === 'assigned' ? 'New teaching assignment' : 'Teaching assignment revoked',
                'instructor.courses.index');
        }

        if ($type === 'homework_submission' && $action === 'submitted') {
            $courseId = HomeworkSubmission::find($id)?->assignment?->course_id;
            if ($courseId) {
                foreach (CourseInstructorAssignment::where('course_id', $courseId)->where('status', 'active')->pluck('instructor_id') as $teacher) {
                    self::send($teacher, 'New homework submission', 'instructor.grading.show', ['submission' => $id]);
                }
            }
        }
    }

    private static function publicProfile(int $id, string $outcome): array
    {
        $profile = PublicProfile::find($id);
        $prefix = $profile?->kind === 'instructor' ? 'instructor' : 'account';

        return [$profile?->user_id, "Public profile $outcome", "$prefix.public-profile.edit", []];
    }
}
