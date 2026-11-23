<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageCampaignItem extends Model
{
    protected $fillable = ['title', 'slug', 'kind', 'status', 'draft', 'published',
        'version', 'cover_asset', 'published_at', 'created_by', 'updated_by',
        'draft_title', 'draft_kind', 'draft_cover_asset'];

    protected function casts(): array
    {
        return [
            'draft' => 'array', 'published' => 'array', 'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }
}
