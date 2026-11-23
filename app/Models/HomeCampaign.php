<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeCampaign extends Model
{
    protected $fillable = [
        'title', 'slug', 'kind', 'summary', 'cover_path', 'modules',
        'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['modules' => 'array', 'published_at' => 'datetime'];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
