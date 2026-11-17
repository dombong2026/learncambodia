<?php

namespace App\Support;

final class CourseDescriptionChecklist
{
    private const BULLET = '/^\h*(?:(?:[•◦▪‣●]\h*)|(?:[-*–]\h+))(.+)$/uD';

    public static function render(?string $richText, ?string $plainText): string
    {
        $html = EditorHtml::clean($richText);
        if ($html === null) {
            $lines = preg_split('/\R/u', trim($plainText ?? '')) ?: [];

            return self::formatLines($lines);
        }

        // Rich editor lists already have semantic markup. Convert typed bullets in
        // plain paragraphs as well, leaving formatted paragraphs and media intact.
        return preg_replace_callback(
            '~(<(p|div)(?: [^>]*)?>)((?:[^<]++|<br>)*)(</\2>)~i',
            function (array $block): string {
                $lines = array_map(
                    fn (string $line): string => trim(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    explode('<br>', $block[3]),
                );
                foreach ($lines as $line) {
                    if (preg_match(self::BULLET, $line)) {
                        return self::formatLines($lines, $block[1], $block[4]);
                    }
                }

                return $block[0];
            },
            $html,
        ) ?? $html;
    }

    private static function formatLines(array $lines, string $open = '<p>', string $close = '</p>'): string
    {
        $html = '';
        $plain = [];
        $items = [];

        $flushParagraph = static function () use (&$html, &$plain, $open, $close): void {
            if ($plain !== []) {
                $html .= $open.implode('<br>', $plain).$close;
                $plain = [];
            }
        };
        $flushList = static function () use (&$html, &$items): void {
            if ($items !== []) {
                $html .= '<ul><li>'.implode('</li><li>', $items).'</li></ul>';
                $items = [];
            }
        };

        foreach ($lines as $line) {
            if (preg_match(self::BULLET, $line, $matches) && trim($matches[1]) !== '') {
                $flushParagraph();
                $items[] = e(trim($matches[1]));
                continue;
            }

            $flushList();
            if (trim($line) === '') {
                $flushParagraph();
            } else {
                $plain[] = e($line);
            }
        }

        $flushParagraph();
        $flushList();

        return $html;
    }
}
