<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ReadingReturn
{
    /**
     * A small, explicit allowlist. Never trust arbitrary user-provided URLs
     * for post-login redirects, even when they appear to be on this host.
     */
    public static function valid(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 280) {
            return null;
        }

        return preg_match(
            '~\A/(?:articles/[a-z0-9][a-z0-9-]*|exam-catalog(?:/[a-z0-9][a-z0-9-]*)?)(?:\?lang=(?:en|km|zh))?\z~iD',
            $value
        ) === 1 ? $value : null;
    }

    public static function onPage(Request $request): ?string
    {
        if (! $request->routeIs('articles.show', 'learning.exam.show', 'learning.exams')) {
            return null;
        }

        $path = '/'.ltrim($request->path(), '/');
        $language = $request->query('lang');
        if (is_string($language) && in_array($language, ['en', 'km', 'zh'], true)) {
            $path .= '?lang='.$language;
        }

        return self::valid($path);
    }

    public static function authUrl(Request $request, string $routeName): string
    {
        $next = self::onPage($request);

        return route($routeName, $next !== null ? ['next' => $next] : []);
    }
}
