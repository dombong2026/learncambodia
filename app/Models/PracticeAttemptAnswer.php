<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeAttemptAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['attempt_id', 'question_id', 'selected_option', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CourseQuizQuestion::class, 'question_id');
    }
}
