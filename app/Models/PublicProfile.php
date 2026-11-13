<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PublicProfile extends Model
{
    protected $fillable = ['user_id','kind','display_name','headline','subjects','city','bio','show_learning_summary'];

    protected function casts(): array
    {
        return [
            'show_learning_summary'=>'boolean',
            'consented_at'=>'datetime',
            'adult_confirmed_at'=>'datetime',
            'reviewed_at'=>'datetime',
            'published_at'=>'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status','published')->whereNotNull('consented_at')
            ->whereNotNull('published_at')
            ->whereHas('user', fn(Builder $users) =>
                $users->whereColumn('users.role','public_profiles.kind'))
            ->where(fn(Builder $scoped) =>
                $scoped->where('kind','instructor')->orWhere(fn(Builder $student) =>
                    $student->where('kind','student')->whereNotNull('adult_confirmed_at')
                        ->whereRaw('1 = ?', [config('public_profiles.students_enabled') ? 1 : 0])));
    }
}
