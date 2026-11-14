<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'slug', 'title', 'summary', 'body',
        'status', 'created_by', 'updated_by',
    ];

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(PostTerm::class, 'content_entry_post_term');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
