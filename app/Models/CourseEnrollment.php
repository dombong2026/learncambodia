<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEnrollment extends Model
{
    protected $fillable = ['course_id', 'student_id', 'status', 'pricing_quote', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['pricing_quote' => 'array', 'reviewed_at' => 'datetime', 'started_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class, 'enrollment_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class, 'enrollment_id');
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'course_enrollment_id');
    }

    public function markStarted(): void
    {
        if ($this->started_at !== null) return;

        self::whereKey($this->id)->where('status', 'approved')->whereNull('started_at')
            ->update(['started_at' => now()]);
    }

    public function rememberLesson(CourseLesson $lesson): void
    {
        self::whereKey($this->id)->where('status', 'approved')
            ->where(fn ($query) => $query->whereNull('last_opened_lesson_id')
                ->orWhere('last_opened_lesson_id', '!=', $lesson->id))
            ->update(['last_opened_lesson_id' => $lesson->id]);
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'enrollment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
