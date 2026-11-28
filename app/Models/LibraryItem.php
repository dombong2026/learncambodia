<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LibraryItem extends Model
{
    public const KINDS = ['book' => 'Book', 'document' => 'Document', 'resource' => 'Study resource'];

    public const ACCESS = ['open' => 'Public access', 'students' => 'Student access', 'loan' => 'Borrow to read'];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

    public const FORMATS = [
        'application/pdf' => 'PDF',
        'text/plain' => 'TXT',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PPTX',
        'application/epub+zip' => 'EPUB',
    ];

    public const READABLE = ['application/pdf', 'text/plain'];

    protected $fillable = ['title', 'author', 'topic', 'description', 'kind', 'access', 'status', 'allow_download', 'loan_days', 'copies'];

    protected function casts(): array
    {
        return ['allow_download' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class, 'file_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class, 'cover_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'library_saves')->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeMatching(Builder $query, string $term): Builder
    {
        return $query->when($term !== '', fn ($items) => $items->where(function ($match) use ($term): void {
            $needle = '%'.$term.'%';
            $match->whereLike('title', $needle)->orWhereLike('author', $needle)
                ->orWhereLike('topic', $needle)->orWhereLike('description', $needle);
        }));
    }

    public function format(): string
    {
        return self::FORMATS[$this->file->mime];
    }

    public function readable(): bool
    {
        return in_array($this->file->mime, self::READABLE, true);
    }

    public static function isStudent(?User $user): bool
    {
        return $user?->role === User::ROLE_STUDENT && $user->isActiveAccount();
    }

    public function hasAccess(?User $user, ?LibraryLoan $loan): bool
    {
        return $this->status === 'published' && match ($this->access) {
            'open' => true,
            'students' => self::isStudent($user),
            'loan' => self::isStudent($user) && $loan !== null,
            default => false,
        };
    }

    public function canDownload(): bool
    {
        return $this->allow_download && $this->access !== 'loan';
    }
}
