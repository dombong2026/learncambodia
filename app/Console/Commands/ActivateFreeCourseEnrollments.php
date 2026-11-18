<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivateFreeCourseEnrollments extends Command
{
    protected $signature = 'learncambodia:activate-free-enrollments';

    protected $description = 'Activate pending enrollments for currently available free courses without a paid quote';

    public function handle(): int
    {
        $candidates = CourseEnrollment::query()
            ->where('status', 'pending')
            ->whereNull('pricing_quote')
            ->whereHas('student', fn ($query) => $query->where('role', User::ROLE_STUDENT))
            ->whereHas('course', fn ($query) => $query->discoverable()
                ->where(fn ($price) => $price->whereNull('pricing')->orWhere('pricing->model', 'free')))
            ->lazyById();

        $activated = 0;
        foreach ($candidates as $candidate) {
            $activated += (int) DB::transaction(fn () => $this->activate($candidate), attempts: 3);
        }

        $this->info("FREE_COURSE_ENROLLMENTS_ACTIVATED=$activated");

        return self::SUCCESS;
    }

    private function activate(CourseEnrollment $candidate): bool
    {
        // Match the live enrollment flow's lock order, then recheck eligibility.
        $student = User::query()->where('role', User::ROLE_STUDENT)
            ->lockForUpdate()->find($candidate->student_id);
        $course = Course::discoverable()->lockForUpdate()->find($candidate->course_id);

        if (! $student || ! $course || ($course->pricing !== null && ($course->pricing['model'] ?? null) !== 'free')) {
            return false;
        }

        $enrollment = CourseEnrollment::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->whereNull('pricing_quote')
            ->lockForUpdate()->find($candidate->id);

        if (! $enrollment) {
            return false;
        }

        $enrollment->update(['status' => 'approved']);
        ApprovalAudit::record('enrollment', $enrollment->id, $student, 'auto_approved', 'pending', 'approved',
            'Automatically activated from the original free enrollment request.');

        return true;
    }
}
