<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonComment extends Model
{
    public const REACTIONS = ['like', 'helpful'];

    protected $fillable = ['lesson_id', 'author_id', 'parent_id', 'body'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(LessonCommentReaction::class, 'comment_id');
    }

    public function scopeWithDiscussionState(Builder $query, User $viewer): Builder
    {
        $query->with('author:id,name');

        foreach (self::REACTIONS as $kind) {
            $query->withCount(['reactions as '.$kind.'_count' => fn ($votes) => $votes->where('kind', $kind)])
                ->withExists(['reactions as viewer_'.$kind => fn ($votes) => $votes->where('kind', $kind)->where('user_id', $viewer->id)]);
        }

        return $query;
    }
}
