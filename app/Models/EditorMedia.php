<?php

namespace App\Models;

use App\Support\EditorHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EditorMedia extends Model
{
    protected $table = 'editor_media';

    protected $fillable = ['uploaded_by', 'context', 'path', 'mime', 'original_name', 'size', 'content_entry_id', 'support_ticket_id'];

    public static function claim(?string $html, User $actor, string $context, int $ownerId): void
    {
        $ids = EditorHtml::mediaIds($html);
        if ($ids === []) return;

        $records = self::query()->whereIn('id', $ids)->lockForUpdate()->get();
        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages(['body_html' => 'One or more inserted media files are missing.']);
        }

        $column = $context === 'editorial' ? 'content_entry_id' : 'support_ticket_id';
        $other = $context === 'editorial' ? 'support_ticket_id' : 'content_entry_id';

        foreach ($records as $media) {
            if ($media->context !== $context || $media->{$other} !== null ||
                ($media->{$column} !== null && (int) $media->{$column} !== $ownerId) ||
                ($media->{$column} === null && (int) $media->uploaded_by !== $actor->id)) {
                throw ValidationException::withMessages(['body_html' => 'Inserted media is not available for this document.']);
            }
            if ($media->{$column} === null) {
                $media->{$column} = $ownerId;
                $media->save();
            }
        }
    }
}
