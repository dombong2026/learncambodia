<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;

use Illuminate\Database\Eloquent\Model;

final class CourseExam extends Model
{
    use HasPublicId;

    protected $fillable = ['course_id', 'kind', 'live_feedback', 'pricing', 'exam_type_id', 'title', 'instructions_html', 'questions', 'time_limit', 'passing_percent', 'attempt_limit', 'shuffle', 'gamified', 'published'];

    protected function casts(): array
    {
        return ['pricing' => 'array', 'questions' => 'array', 'shuffle' => 'boolean', 'gamified' => 'boolean', 'published' => 'boolean', 'live_feedback' => 'boolean'];
    }

    public function publicUrl(): string
    {
        return route($this->kind === 'quiz' ? 'public.quizzes.show' : 'public.exams.show', $this);
    }

    public function course(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(EditorMedia::class, 'course_exam_media');
    }

    protected $hidden = ['questions'];
}
