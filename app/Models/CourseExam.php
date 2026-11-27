<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseExam extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = ['course_id', 'kind', 'live_feedback', 'pricing', 'exam_type_id', 'title', 'featured_kind', 'featured_media_id', 'featured_alt', 'featured_value', 'public_requirement', 'content_language', 'instructions_html', 'questions', 'time_limit', 'passing_percent', 'attempt_limit', 'shuffle', 'gamified', 'published', 'guest_enabled'];

    protected function casts(): array
    {
        return ['pricing' => 'array', 'questions' => 'array', 'shuffle' => 'boolean', 'gamified' => 'boolean', 'published' => 'boolean', 'guest_enabled' => 'boolean', 'live_feedback' => 'boolean', 'deactivated_at' => 'datetime'];
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class);
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

    public function managerLevel(): ?string
    {
        if ($this->course?->cefr_level) {
            return $this->course->cefr_level;
        }

        return preg_match('/^(A1|A2|B1|B2|C1|C2)\b/u', $this->type?->name ?? '', $level) ? $level[1] : null;
    }

    public function managerStatus(): string
    {
        return $this->published ? 'Published' : ($this->deactivated_at ? 'Inactive' : 'Draft');
    }

    protected $hidden = ['questions'];
}
