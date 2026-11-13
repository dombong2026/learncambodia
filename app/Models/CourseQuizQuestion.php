<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseQuizQuestion extends Model
{
    protected $fillable = [
        'course_quiz_id', 'prompt', 'option_a', 'option_b',
        'option_c', 'option_d', 'correct_option', 'explanation', 'position',
    ];

    protected $hidden = ['correct_option'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(CourseQuiz::class, 'course_quiz_id');
    }
}
