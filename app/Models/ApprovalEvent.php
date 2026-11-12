<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['subject_type', 'subject_id', 'actor_id', 'action',
        'from_status', 'to_status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
