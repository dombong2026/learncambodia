<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    protected $fillable = [
        'category_id', 'slug', 'title', 'summary', 'description',
        'cefr_level', 'delivery_mode', 'status', 'published_at',
        'created_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'review_note',
        'source_course_id', 'revision_number', 'superseded_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position')->orderBy('id');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(CourseLesson::class, CourseModule::class, 'course_id', 'course_module_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(CourseQuiz::class)->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function isMutable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query->published()->whereNull('superseded_at');
    }

    public function sourceCourse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_course_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('category', fn (Builder $builder) => $builder->where('is_published', true));
    }
}
