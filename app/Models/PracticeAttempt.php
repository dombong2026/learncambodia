<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeAttempt extends Model
{
    protected $fillable = [
        'course_quiz_id', 'enrollment_id', 'correct_count',
        'question_count', 'score_percent', 'passed', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(CourseQuiz::class, 'course_quiz_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PracticeAttemptAnswer::class, 'attempt_id');
    }
}
