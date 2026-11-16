<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExamAttempt extends Model
{
    protected $fillable = ['course_exam_id', 'course_enrollment_id', 'snapshot', 'answers', 'score', 'passed', 'expires_at', 'submitted_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'answers' => 'array', 'passed' => 'boolean', 'expires_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    protected $hidden = ['snapshot', 'answers'];
    public function enrollment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
    }
}
