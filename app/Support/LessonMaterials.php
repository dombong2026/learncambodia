<?php

namespace App\Support;

use App\Models\CourseLesson;
use App\Models\EditorMedia;
use DOMDocument;
use DOMXPath;
use ZipArchive;

final class LessonMaterials
{
    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public static function for(CourseLesson $lesson): array
    {
        $viewers = ['read' => ['type' => 'reading', 'label' => __('focused_learning.reading')]];
        $links = $files = [];
        $clean = EditorHtml::clean($lesson->content_html, lesson: true);
        $reading = $clean;

        foreach ($lesson->media()->where('editor_media.context', 'lesson')->orderBy('editor_media.id')->get() as $file) {
            $url = route('editor-media.show', $file);
            $type = match (true) {
                str_starts_with($file->mime, 'video/') => 'video',
                str_starts_with($file->mime, 'audio/') => 'audio',
                $file->mime === 'application/pdf' => 'pdf',
                $file->mime === self::DOCX && class_exists(ZipArchive::class) => 'document',
                default => null,
            };
            $key = $type === null ? null : 'file-'.$file->id;
            $files[] = ['label' => $file->original_name, 'url' => $url, 'viewer' => $key];
            if ($key !== null) {
                $viewers[$key] = [
                    'type' => $type, 'label' => $file->original_name,
                    'url' => $type === 'pdf' ? route('editor-media.show', [$file, 'preview' => 1]) : $url,
                    'file' => $file,
                ];
            }
        }

        if ($clean !== null) {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<meta charset="UTF-8"><div id="materials">'.$clean.'</div>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementById('materials');
            if ($root) {
                $xpath = new DOMXPath($document);
                foreach ($xpath->query('.//figure[@data-youtube] | .//video | .//audio', $root) as $element) {
                    if ($element->hasAttribute('data-youtube')) {
                        $videoId = $element->getAttribute('data-youtube');
                        $caption = trim($element->textContent);
                        $viewers['video-'.$videoId] = [
                            'type' => 'youtube', 'label' => $caption ?: 'YouTube video',
                            'url' => 'https://www.youtube-nocookie.com/embed/'.$videoId,
                        ];
                    }
                    $element->parentNode->removeChild($element);
                }
                foreach ($root->getElementsByTagName('a') as $link) {
                    $href = $link->getAttribute('href');
                    if ($href !== '' && !str_starts_with($href, '/editor-media/')) {
                        $links[$href] = trim($link->textContent) ?: 'Open link';
                    }
                }
                $reading = '';
                foreach ($root->childNodes as $child) {
                    $reading .= $document->saveHTML($child);
                }
            }
        }

        $playable = array_filter($viewers, fn ($viewer) => in_array($viewer['type'], ['video', 'youtube', 'audio'], true));
        $inlineMedia = EditorHtml::sourceIds($clean, lesson: true);
        // Supplemental attachments stay accessible without replacing authored reading.
        $documents = array_filter($viewers, fn ($viewer) => in_array($viewer['type'], ['pdf', 'document'], true)
            && ($clean === null || in_array($viewer['file']->id, $inlineMedia, true)));
        $defaultViewer = array_key_first($playable + $documents) ?? 'read';

        return compact('files', 'viewers', 'defaultViewer', 'links', 'reading');
    }

    /** Display plain text from a bounded DOCX file without sending it to a third party. */
    public static function document(EditorMedia $media): array
    {
        $limit = 8 * 1024 * 1024;
        if ($media->mime !== self::DOCX || $media->size > $limit || !class_exists(ZipArchive::class)) {
            return [];
        }

        $source = ManagedFiles::disk()->readStream($media->path);
        if (!is_resource($source)) {
            return [];
        }
        $temporary = tmpfile();
        try {
            if ($temporary === false) {
                return [];
            }
            $copied = stream_copy_to_stream($source, $temporary, $limit + 1);
            if ($copied === false || $copied > $limit || !fflush($temporary)) {
                return [];
            }
            $zip = new ZipArchive;
            if ($zip->open(stream_get_meta_data($temporary)['uri'], ZipArchive::RDONLY) !== true) {
                return [];
            }
            try {
                $entry = $zip->statName('word/document.xml');
                if (!$entry || $entry['size'] > 2 * 1024 * 1024) {
                    return [];
                }
                $xml = $zip->getFromName('word/document.xml');
            } finally {
                $zip->close();
            }
        } finally {
            fclose($source);
            if (is_resource($temporary)) {
                fclose($temporary);
            }
        }

        $document = new DOMDocument;
        if (!$xml || !$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) || $document->doctype) {
            return [];
        }
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paragraphs = [];
        foreach ($document->getElementsByTagNameNS($namespace, 'p') as $paragraph) {
            $text = '';
            foreach ($paragraph->getElementsByTagNameNS($namespace, 't') as $run) {
                $text .= $run->textContent;
            }
            if (trim($text) !== '') {
                $paragraphs[] = $text;
            }
            if (count($paragraphs) >= 1000) {
                break;
            }
        }

        return $paragraphs;
    }
}
