<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasPublicId;

    public const FEATURED_FIELDS = ['featured_kind', 'featured_media_id', 'featured_value', 'featured_alt'];

    protected $fillable = [
        ...self::FEATURED_FIELDS,
        'category_id', 'slug', 'title', 'summary', 'description', 'description_html',
        'cefr_level', 'delivery_mode', 'public_requirement', 'content_language', 'pricing', 'status', 'published_at',
        'created_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'review_note',
        'source_course_id', 'revision_number', 'superseded_at',
    ];

    protected function casts(): array
    {
        return ['pricing' => 'array', 'published_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class);
    }

    public function descriptionMedia(): BelongsToMany
    {
        return $this->belongsToMany(EditorMedia::class, 'course_description_media');
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

    public function exams(): HasMany
    {
        return $this->hasMany(CourseExam::class);
    }

    public function certificateTemplates(): BelongsToMany
    {
        return $this->belongsToMany(CertificateTemplate::class, 'course_certificate_templates');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(CourseQuiz::class)->orderBy('position')->orderBy('id');
    }

    public function instructorAssignments(): HasMany
    {
        return $this->hasMany(CourseInstructorAssignment::class);
    }

    public function homeworkAssignments(): HasMany
    {
        return $this->hasMany(CourseHomeworkAssignment::class);
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
