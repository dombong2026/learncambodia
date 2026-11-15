<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentEntry extends Model
{
    use SoftDeletes;

    public const DIRECT_PUBLICATION_TYPES = ['page', 'post', 'policy', 'help_article'];

    protected $fillable = [
        'type', 'slug', 'title', 'summary', 'body', 'body_html', 'seo_title', 'seo_description',
        'status', 'created_by', 'updated_by', 'published_at',
        'featured_kind', 'featured_media_id', 'featured_value', 'featured_alt',
        'policy_version', 'policy_effective_on',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'policy_effective_on' => 'date'];
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class);
    }

    public function policyRevisions(): HasMany
    {
        return $this->hasMany(PolicyRevision::class);
    }

    public static function publishedLegalPolicies(): array
    {
        return self::query()->where('type', 'policy')->where('status', 'published')
            ->whereIn('slug', ['privacy', 'terms'])->pluck('title', 'slug')->all();
    }

    public function publicUrl(): string
    {
        return match ($this->type) {
            'page' => route('pages.show', $this->slug),
            'post' => route('articles.show', $this->slug),
            'policy' => route('policies.show', $this->slug),
            'help_article' => route('help.show', ['kind' => 'articles', 'entry' => $this->slug]),
            'faq' => route('help.show', ['kind' => 'faqs', 'entry' => $this->slug]),
        };
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(PostTerm::class, 'content_entry_post_term');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
