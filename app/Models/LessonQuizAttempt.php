<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LessonQuizAttempt extends Model
{
    protected $fillable = ['lesson_id', 'retry_of_id', 'questions', 'answers', 'correct_count'];

    protected $hidden = ['questions', 'answers'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'answers' => 'array', 'correct_count' => 'integer'];
    }

    public function missedQuestions(): array
    {
        return array_values(array_filter($this->questions,
            fn (array $question, int $index) => $question['correct'] !== ($this->answers[$index] ?? null),
            ARRAY_FILTER_USE_BOTH));
    }
}
