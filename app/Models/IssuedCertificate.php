<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class IssuedCertificate extends Model
{
    protected $fillable = ['course_enrollment_id', 'certificate_template_id', 'student_id', 'award_key', 'issued_by', 'code', 'snapshot', 'revoked_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'revoked_at' => 'datetime'];
    }

    public function enrollment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
    }
}
