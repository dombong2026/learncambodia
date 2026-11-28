<?php

namespace App\Support;

use App\Models\EditorMedia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class LibraryFiles
{
    public const COVER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function store(UploadedFile $file, array $mimes, string $field): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, $mimes, true)) {
            throw ValidationException::withMessages([$field => 'This file format is unsupported.']);
        }

        $path = ManagedFiles::store($file, 'editor-media/student-library');
        abort_unless($path, 503, 'The library file could not be stored.');
        // Derive the extension from the detected format. The supplied filename is display text only.
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $extensions[$mime] ?? strtolower(\App\Models\LibraryItem::FORMATS[$mime]);
        $stem = pathinfo(str_replace('\\', '/', $file->getClientOriginalName()), PATHINFO_FILENAME);
        $name = Str::limit(trim(preg_replace('/[^\p{L}\p{M}\p{N} ._()\x{2013}\x{2014}-]+/u', ' ', $stem) ?? ''), 140, '');

        return [
            'path' => $path, 'mime' => $mime, 'size' => $file->getSize(),
            'original_name' => ($name ?: 'library-resource').'.'.$extension,
            'context' => 'student-library',
        ];
    }

    public static function response(Request $request, EditorMedia $media, bool $inline = false): Response
    {
        $fallbackName = preg_match('/^[\x20-\x7e]+$/D', $media->original_name)
            ? $media->original_name : 'library-resource.'.pathinfo($media->original_name, PATHINFO_EXTENSION);
        $headers = [
            'Content-Type' => $media->mime.($media->mime === 'text/plain' ? '; charset=UTF-8' : ''),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Content-Disposition' => $inline ? 'inline' : HeaderUtils::makeDisposition(
                'attachment', $media->original_name, $fallbackName,
            ),
        ];

        return ManagedFiles::response($request, $media->path, $headers, seekable: true);
    }
}
