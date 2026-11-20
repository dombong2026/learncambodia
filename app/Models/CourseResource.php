<?php

namespace App\Models;

use App\Support\ManagedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CourseResource extends Model
{
    protected $fillable = ['editor_media_id', 'title', 'position'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(EditorMedia::class, 'editor_media_id');
    }

    public function download(Request $request): Response
    {
        $file = $this->media;

        return ManagedFiles::response($request, $file->path, [
            'Content-Type' => $file->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], downloadName: $file->original_name);
    }
}
