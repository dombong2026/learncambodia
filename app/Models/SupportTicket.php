<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportTicket extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id','kind','subject','priority','status','assigned_to'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class,'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }
}
