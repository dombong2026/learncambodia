<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkSubmission extends Model
{
    protected $fillable = [
        'assignment_id','enrollment_id','body','version','status',
        'submitted_at','graded_by','graded_at','score','feedback',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime','graded_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CourseHomeworkAssignment::class, 'assignment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(HomeworkSubmissionVersion::class, 'submission_id')
            ->orderBy('version');
    }
}
