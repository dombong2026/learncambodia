<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicEvidenceReview extends Model
{
    protected $fillable = [
        'enrollment_id','requested_by','reviewed_by',
        'evidence_snapshot','status','review_note','reviewed_at',
    ];

    protected function casts(): array
    {
        return ['evidence_snapshot'=>'array','reviewed_at'=>'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }
}
