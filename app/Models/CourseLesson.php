<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseLesson extends Model
{
    protected $fillable = [
        'course_module_id', 'title', 'content', 'content_html', 'quiz_questions', 'position', 'duration_minutes', 'is_preview',
    ];

    protected $hidden = ['quiz_questions'];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean', 'quiz_questions' => 'array'];
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(EditorMedia::class, 'course_lesson_media');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }
}
