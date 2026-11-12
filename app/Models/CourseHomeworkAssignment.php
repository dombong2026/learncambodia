<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseHomeworkAssignment extends Model
{
    protected $fillable = [
        'course_id','author_id','title','instructions','rubric','due_at',
        'status','submitted_for_review_at','reviewed_by','reviewed_at',
        'review_note','published_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'submitted_for_review_at' => 'datetime',
            'reviewed_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class, 'assignment_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status','published')
            ->whereNotNull('published_at')->where('published_at','<=',now());
    }

    public function isEditable(): bool
    {
        return in_array($this->status,['draft','rejected'],true);
    }
}
