<?php

namespace App\Support;

use DOMDocument;
use DOMNode;
use DOMElement;

final class EditorHtml
{
    private const TAGS = [
        'p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'hr', 'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'sup', 'sub',
        'video', 'audio', 'source',
    ];
    private const DROP = ['script','style','iframe','object','embed','svg','math','form','input','button','link','meta','base'];
    private const VOID = ['br','hr','img','source'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return null;
        $document = new DOMDocument('1.0', 'UTF-8');
        $before = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="editor-root">'.$html.'</div></body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($before);
        }
        $root = $document->getElementById('editor-root');
        if (!$root) return null;
        $rendered = '';
        foreach ($root->childNodes as $node) $rendered .= self::render($node);
        return trim($rendered) ?: null;
    }

    public static function text(?string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(self::clean($html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /** Only scoped media endpoints; no remote tracking pixels or executable URLs. */
    public static function mediaIds(?string $html): array
    {
        if (!$html) return [];
        preg_match_all('~/(?:editor-media)/([1-9][0-9]*)(?=[^0-9]|$)~', $html, $matches);
        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    private static function render(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof DOMElement) return '';

        $original = strtolower($node->tagName);
        if (in_array($original, self::DROP, true)) return '';

        $children = '';
        foreach ($node->childNodes as $child) $children .= self::render($child);
        if ($original !== 'font' && !in_array($original, self::TAGS, true)) return $children;
        $tag = $original === 'font' ? 'span' : $original;

        $attrs = '';
        $style = self::safeStyle($node);
        if ($style !== '') $attrs .= self::attr('style', $style);
        if ($tag === 'a') {
            $url = trim($node->getAttribute('href'));
            if (preg_match('~^(https?://|mailto:|tel:|/)~i', $url) && !str_starts_with($url, '//')) {
                $attrs .= self::attr('href', $url);
                $attrs .= ' rel="noopener noreferrer nofollow"';
            }
        }
        if (in_array($tag, ['img','source','audio','video'], true)) {
            $source = $node->getAttribute('src');
            if ($source !== '' && preg_match('~^/editor-media/[1-9][0-9]*$~D', $source)) {
                $attrs .= self::attr('src', $source);
            } elseif ($tag === 'img' || $tag === 'source') {
                return '';
            }
            if ($tag === 'img') {
                $attrs .= self::attr('alt', mb_substr($node->getAttribute('alt'), 0, 200));
                $attrs .= ' loading="lazy"';
            }
            if ($tag === 'audio' || $tag === 'video') $attrs .= ' controls preload="none"';
        }
        if ($tag === 'td' || $tag === 'th') {
            foreach (['colspan','rowspan'] as $dimension) {
                $value = $node->getAttribute($dimension);
                if (ctype_digit($value) && (int)$value >= 1 && (int)$value <= 8) $attrs .= self::attr($dimension, $value);
            }
        }
        if (in_array($tag, self::VOID, true)) return '<'.$tag.$attrs.'>';
        return '<'.$tag.$attrs.'>'.$children.'</'.$tag.'>';
    }

    private static function safeStyle(DOMElement $node): string
    {
        $safe = [];
        $raw = explode(';', mb_strtolower($node->getAttribute('style')));
        if (strtolower($node->tagName) === 'font') {
            $raw[] = 'color:'.$node->getAttribute('color');
            $size = (int) $node->getAttribute('size');
            $sizes = [1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 24, 6 => 32, 7 => 36];
            if (isset($sizes[$size])) $raw[] = 'font-size:'.$sizes[$size].'px';
            $face = mb_strtolower($node->getAttribute('face'));
            if (in_array($face, ['arial','georgia','monospace'], true)) $raw[] = 'font-family:'.$face;
        }
        foreach ($raw as $item) {
            $pair = explode(':', trim($item), 2);
            if (count($pair) !== 2) continue;
            [$key,$value] = array_map('trim', $pair);
            if (in_array($key, ['color','background-color'], true)) {
                $value = self::safeColor($value);
                if ($value !== null) $safe[$key] = $value;
            } elseif ($key === 'text-align' && in_array($value, ['left','center','right','justify'], true)) {
                $safe[$key] = $value;
            } elseif ($key === 'font-size' && preg_match('/^(12|14|16|18|20|24|28|32|36)px$/D', $value)) {
                $safe[$key] = $value;
            } elseif ($key === 'font-family' && in_array($value, ['arial','georgia','monospace'], true)) {
                $safe[$key] = $value;
            }
        }
        return implode(';', array_map(fn ($key,$value) => $key.':'.$value, array_keys($safe), array_values($safe)));
    }

    private static function safeColor(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/iD', $value)) return $value;
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/D', $value, $rgb)) {
            if ((int)$rgb[1] <= 255 && (int)$rgb[2] <= 255 && (int)$rgb[3] <= 255) {
                return sprintf('#%02x%02x%02x', (int)$rgb[1], (int)$rgb[2], (int)$rgb[3]);
            }
        }
        return null;
    }

    private static function attr(string $name, string $value): string
    {
        return ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
    }
}
