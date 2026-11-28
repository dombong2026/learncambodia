<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonSupportMessage extends Model
{
    protected $fillable = ['enrollment_id', 'lesson_id', 'instructor_id', 'author_id', 'body'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function scopeThread(Builder $query, CourseEnrollment $enrollment, User $instructor): Builder
    {
        return $query->where('instructor_id', $instructor->id)
            ->where('enrollment_id', $enrollment->id);
    }
}
