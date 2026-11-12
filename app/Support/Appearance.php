<?php

namespace App\Support;

use App\Models\AppearanceRevision;

final class Appearance
{
    // Only allow scalar, length-bounded copy and a fixed typography palette.
    public const SECTIONS = [
        'header' => ['announcement' => 'text:120', 'search_placeholder' => 'text:80'],
        'menu' => ['all_categories_label' => 'text:60', 'show_cefr' => 'bool'],
        'footer' => ['footer_note' => 'text:180'],
        'typography' => ['font_style' => 'font', 'text_scale' => 'scale'],
        'home' => ['hero_title' => 'text:130', 'hero_intro' => 'text:480'],
        'about' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'contact' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'course' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'catalog' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'auth' => ['login_heading' => 'text:100', 'register_heading' => 'text:100'],
    ];

    public const DEFAULTS = [
        'header' => ['announcement' => '', 'search_placeholder' => 'Search courses, IELTS, TEFL, CEFR…'],
        'menu' => ['all_categories_label' => 'All categories', 'show_cefr' => true],
        'footer' => ['footer_note' => 'Independent preparation information. Third-party exams and qualifications belong to their respective organizations.'],
        'typography' => ['font_style' => 'system', 'text_scale' => 'normal'],
        'home' => ['hero_title' => 'Explore English learning for every ambition.', 'hero_intro' => 'Discover clear learning pathways from CEFR A1 to C2, international English examination preparation, and professional teaching development.'],
        'about' => ['heading' => 'About Learn Cambodia', 'intro' => 'Learn Cambodia is developing accessible English-language pathways for students and educators worldwide.'],
        'contact' => ['heading' => 'Contact Us', 'intro' => 'Connect with the Learn Cambodia team.'],
        'course' => ['heading' => 'Courses in this pathway', 'intro' => 'Explore academically reviewed courses.'],
        'catalog' => ['heading' => 'Explore learning categories', 'intro' => 'Find English study, proficiency-test preparation, professional teacher development and language-learning frameworks.'],
        'auth' => ['login_heading' => 'Welcome back', 'register_heading' => 'Create your account'],
    ];

    public static function fields(string $section): array
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return self::SECTIONS[$section];
    }

    public static function rules(string $section): array
    {
        $rules = [];
        foreach (self::fields($section) as $key => $kind) {
            $rules[$key] = match (true) {
                $kind === 'bool' => ['required', 'boolean'],
                $kind === 'font' => ['required', 'in:system,serif,mono'],
                $kind === 'scale' => ['required', 'in:compact,normal,large'],
                default => ['present', 'string', 'max:'.explode(':', $kind)[1]],
            };
        }
        return $rules;
    }

    public static function approved(string $section): array
    {
        self::fields($section);

        return once(fn () => array_replace(
            self::DEFAULTS[$section],
            AppearanceRevision::query()->approved()->where('section', $section)
                ->orderByDesc('id')->value('settings') ?? []
        ));
    }

    public static function value(string $section, string $field): mixed
    {
        return self::approved($section)[$field] ?? null;
    }
}
