<?php

namespace App\Support;

use App\Models\EditorMedia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class FeaturedMedia
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];

    public static function validate(Request $request): array
    {
        if (!$request->has('featured_kind')) {
            return [];
        }

        $data = $request->validate([
            'featured_kind' => ['nullable', Rule::in(['none', 'image', 'video', 'youtube', 'color'])],
            'featured_media_id' => ['exclude_unless:featured_kind,image,video', 'required', 'integer', 'min:1'],
            'featured_alt' => ['exclude_unless:featured_kind,image', 'nullable', 'string', 'max:200'],
            'featured_youtube' => ['exclude_unless:featured_kind,youtube', 'required', 'string', 'max:2048'],
            'featured_color' => ['exclude_unless:featured_kind,color', 'required', 'regex:/^#[a-fA-F0-9]{6}$/'],
        ]);

        $kind = $data['featured_kind'] ?? 'none';
        $media = null;
        $value = null;

        if (in_array($kind, ['image', 'video'], true)) {
            $media = EditorMedia::query()->where('context', 'library')->find($data['featured_media_id']);
            if (!$media || !in_array($media->mime, self::MIMES, true) || !str_starts_with($media->mime, $kind.'/')) {
                throw ValidationException::withMessages(['featured_media_id' => 'Choose a matching file from the Media Library.']);
            }
        } elseif ($kind === 'youtube') {
            $value = self::youtubeId($data['featured_youtube']);
            if ($value === null) {
                throw ValidationException::withMessages(['featured_youtube' => 'Enter a valid YouTube video link or ID.']);
            }
        } elseif ($kind === 'color') {
            $value = strtolower($data['featured_color']);
        }

        return [
            'featured_kind' => $kind === 'none' ? null : $kind,
            'featured_media_id' => $media?->id,
            'featured_value' => $value,
            'featured_alt' => $data['featured_alt'] ?? null,
        ];
    }

    private static function youtubeId(string $input): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $input)) {
            return $input;
        }

        $parts = parse_url($input);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $id = null;

        if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
            $id = $match[1];
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $match)) {
                $id = $match[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : null;
    }
}
