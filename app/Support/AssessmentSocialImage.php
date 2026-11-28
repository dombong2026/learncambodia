<?php

namespace App\Support;

use App\Models\CourseExam;
use App\Models\EditorMedia;

final class AssessmentSocialImage
{
    public static function url(CourseExam $exam): ?string
    {
        if (!$exam->published || $exam->course_id !== null || $exam->featured_kind !== 'image') {
            return null;
        }

        $media = $exam->featuredMedia;

        return $media ? route('editor-media.show', ['media' => $media, 'social' => 1]) : null;
    }

    public static function jpegPath(EditorMedia $media): string
    {
        return 'editor-media/library/assessment-social/'.$media->id.'-'.substr(hash('sha256', $media->path), 0, 16).'.jpg';
    }
}
