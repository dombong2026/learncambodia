<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseReviewSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_id','submitted_by','checklist','fingerprint',
        'reviewed_by','decision','reviewed_at','created_at',
    ];

    protected function casts(): array
    {
        return ['checklist'=>'array','created_at'=>'datetime','reviewed_at'=>'datetime'];
    }
}
