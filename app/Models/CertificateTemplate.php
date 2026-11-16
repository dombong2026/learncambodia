<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CertificateTemplate extends Model
{
    protected $fillable = ['certificate_type_id', 'name', 'heading', 'body', 'issuer', 'accent', 'orientation'];

    public function courses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_certificate_templates');
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }
}
