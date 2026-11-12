<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
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
}
