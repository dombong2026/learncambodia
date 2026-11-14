<?php

namespace App\Support;

use App\Models\Category;

/**
 * Presentation-only mapping. Never changes publication, course ownership,
 * authorization, database state, or a category's existing identity.
 */
final class CategoryVisual
{
    public static function iconFor(Category $category): string
    {
        return match (strtoupper(trim((string) $category->code))) {
            'GE', 'EFL' => 'book-open',
            'ESL' => 'headphones',
            'BE', 'ESP' => 'briefcase',
            'EAP' => 'library',
            'IELTS', 'TOEFL', 'CELPIP' => 'clipboard-check',
            'TOEIC', 'OET' => 'chart-line',
            'PTE', 'DET' => 'brain',
            'TEFL', 'TESOL', 'TEYL' => 'graduation',
            'CELTA', 'TKT' => 'presentation',
            'CEFR' => 'award',
            default => self::groupIcon((string) $category->group),
        };
    }

    public static function groupIcon(string $group): string
    {
        return match ($group) {
            'english-courses' => 'book-open',
            'exam-preparation' => 'clipboard-check',
            'teacher-development' => 'school',
            'specialist-english' => 'briefcase',
            'frameworks' => 'award',
            default => 'book',
        };
    }
}
