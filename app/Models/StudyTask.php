<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyTask extends Model
{
    protected $fillable = ['goal_id', 'title', 'due_on', 'completed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(StudyGoal::class);
    }
}
