<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExamAttempt extends Model
{
    protected $fillable = ['course_exam_id', 'course_enrollment_id', 'student_id', 'guest_browser', 'grading', 'snapshot', 'answers', 'score', 'passed', 'expires_at', 'submitted_at'];

    protected function casts(): array
    {
        return ['grading' => 'array', 'snapshot' => 'array', 'answers' => 'array', 'passed' => 'boolean', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    protected $hidden = ['snapshot', 'answers', 'grading', 'guest_browser', 'guest_email'];

    public function isSpecialized(): bool
    {
        return $this->course_enrollment_id === null && ($this->snapshot['viewer_mode'] ?? null) === 'specialized';
    }

    public function resultRoute(): string
    {
        return $this->isSpecialized() ? 'specialized-exams.attempts.show' : 'account.assessment-attempts.show';
    }


    public function exam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }

    public function enrollment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
    }
    public function student(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
