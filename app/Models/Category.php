<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const SUBHEADER_CODES = ['ESL', 'GE', 'EAP', 'TOEIC', 'TOEFL', 'IELTS'];

    public const GROUPS = [
        'english-courses' => 'English Courses',
        'exam-preparation' => 'Exam Preparation',
        'teacher-development' => 'Teacher Development',
        'specialist-english' => 'Specialist English',
        'frameworks' => 'Language Frameworks',
    ];

    protected $fillable = ['code', 'slug', 'name', 'group', 'description', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(ContentEntry::class)->publishedArticles();
    }

    public function scopeForNavigation(Builder $query): Builder
    {
        return $query->visibleInCatalog()->whereIn('code', self::SUBHEADER_CODES)
            ->orderByRaw('case when code = ? then 0 else 1 end', ['ESL'])
            ->orderBy('sort_order')->orderBy('id')
            ->select(['id', 'slug', 'code', 'name']);
    }

    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        return $query->published()->where(function (Builder $content): void {
            $content->whereHas('courses', fn (Builder $courses) => $courses->discoverable())
                ->orWhereHas('articles');
        });
    }
}
