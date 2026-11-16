<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssessmentAccess extends Model
{
    protected $table = 'assessment_access';

    protected $fillable = ['course_exam_id', 'student_id', 'quote', 'status', 'reference', 'reviewed_by'];

    protected function casts(): array
    {
        return ['quote' => 'array'];
    }

    public function matches(array $quote): bool
    {
        return $this->quote['model'] === $quote['model']
            && ($quote['model'] !== 'membership' || $this->quote['plan'] === $quote['plan']);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }
}
