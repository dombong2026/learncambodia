<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyGoal extends Model
{
    protected $fillable = ['student_id', 'enrollment_id', 'title', 'target_date', 'completed_at'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(StudyTask::class, 'goal_id')->orderBy('due_on')->orderBy('id');
    }
}
