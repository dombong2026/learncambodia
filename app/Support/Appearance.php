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
        'about' => [
            'heading'=>'text:100','intro'=>'text:350',
            'mission_heading'=>'text:120','mission_body'=>'text:650',
            'vision_heading'=>'text:120','vision_body'=>'text:650',
            'method_heading'=>'text:120','join_heading'=>'text:120',
        ],
        'contact' => [
            'heading'=>'text:100','intro'=>'text:350',
            'form_heading'=>'text:120','channels_heading'=>'text:120',
            'faq_heading'=>'text:120','privacy_note'=>'text:500',
        ],
        'categories' => [
            'heading'=>'text:120','intro'=>'text:500',
            'explore_heading'=>'text:120','groups_heading'=>'text:120',
            'pathways_heading'=>'text:120','cta_heading'=>'text:120',
        ],
        'single_category' => [
            'eyebrow'=>'text:80','course_heading'=>'text:120',
            'resources_heading'=>'text:120','teachers_heading'=>'text:120',
            'related_heading'=>'text:120','cta_heading'=>'text:120',
        ],
        'author' => [
            'heading'=>'text:120','intro'=>'text:500',
            'work_heading'=>'text:120','people_heading'=>'text:120',
            'method_heading'=>'text:120','cta_heading'=>'text:120',
        ],
        'course' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'catalog' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'auth' => ['login_heading' => 'text:100', 'register_heading' => 'text:100'],
    ];

    public const LABELS = [
        'home'=>'Home', 'categories'=>'Categories',
        'single_category'=>'Single Category', 'author'=>'Author',
        'about'=>'About', 'contact'=>'Contact',
        'catalog'=>'Catalog (legacy)', 'course'=>'Course (legacy)',
        'header'=>'Global Header','menu'=>'Global Menu',
        'footer'=>'Global Footer','typography'=>'Typography',
        'auth'=>'Authentication',
    ];

    public const PAGE_PREVIEWS = [
        'home'=>'home',
        'categories'=>'categories.index',
        'single_category'=>'categories.index',
        'author'=>'authors.index',
        'about'=>'information.show',
        'contact'=>'information.show',
        'catalog'=>'categories.index',
        'course'=>'courses.index',
    ];

    public static function preview(string $section): ?string
    {
        $route = self::PAGE_PREVIEWS[$section] ?? null;
        if (!$route || !\Illuminate\Support\Facades\Route::has($route)) return null;

        if ($section==='single_category') {
            $category=\App\Models\Category::published()->orderBy('sort_order')->first();
            return $category ? route('categories.show',$category) : route('categories.index');
        }

        return match ($section) {
            'about'=>route('information.show','about'),
            'contact'=>route('information.show','contact'),
            default=>route($route),
        };
    }

    public const DEFAULTS = [
        'header' => ['announcement' => '', 'search_placeholder' => 'Search courses, IELTS, TEFL, CEFR…'],
        'menu' => ['all_categories_label' => 'All categories', 'show_cefr' => true],
        'footer' => ['footer_note' => 'Independent preparation information. Third-party exams and qualifications belong to their respective organizations.'],
        'typography' => ['font_style' => 'system', 'text_scale' => 'normal'],
        'home' => ['hero_title' => 'Explore English learning for every ambition.', 'hero_intro' => 'Discover clear learning pathways from CEFR A1 to C2, international English examination preparation, and professional teaching development.'],
        'about' => [
            'heading'=>'About Learn Cambodia',
            'intro'=>'Learning pathways built for clarity, accessibility and responsible academic review.',
            'mission_heading'=>'Our mission',
            'mission_body'=>'Make educational pathways understandable, accessible and respectful of the learners who use them. Our developing platform brings course discovery, approved enrollment and structured learning into one place.',
            'vision_heading'=>'Our vision',
            'vision_body'=>'An educational platform where learners can discover credible resources and instructors can teach with clear responsibilities, accessible design and transparent review.',
            'method_heading'=>'Our educational approach',
            'join_heading'=>'Be part of the learning journey',
        ],
        'contact' => [
            'heading'=>'Contact Learn Cambodia',
            'intro'=>'Questions about a course, enrollment or our learning platform? Send a secure inquiry to our administrative team.',
            'form_heading'=>'Send an inquiry',
            'channels_heading'=>'How to get support',
            'faq_heading'=>'Frequently asked questions',
            'privacy_note'=>'Your inquiry is stored privately for authorized administrators. Do not include passwords, identity documents, medical information or bank details. Automated outbound notifications are not enabled.',
        ],
        'categories' => [
            'heading'=>'Explore learning categories',
            'intro'=>'Find approved educational pathways organized by subject, examination preparation and teaching goals.',
            'explore_heading'=>'Find your learning pathway',
            'groups_heading'=>'Explore by learning focus',
            'pathways_heading'=>'Published course categories',
            'cta_heading'=>'Ready to choose a course?',
        ],
        'single_category' => [
            'eyebrow'=>'Learning pathway',
            'course_heading'=>'Explore approved courses',
            'resources_heading'=>'Study resources and guidance',
            'teachers_heading'=>'Instructors in this pathway',
            'related_heading'=>'Explore related categories',
            'cta_heading'=>'Continue your learning journey',
        ],
        'author' => [
            'heading'=>'Educational authors and contributors',
            'intro'=>'Discover educational articles and the instructors who have voluntarily shared reviewed public contributor profiles.',
            'work_heading'=>'Published educational articles',
            'people_heading'=>'Meet contributing educators',
            'method_heading'=>'Editorial quality and publication',
            'cta_heading'=>'Explore more learning resources',
        ],
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
                default => ['nullable', 'string', 'max:'.explode(':', $kind)[1]],
            };
        }
        return $rules;
    }

    public static function approved(string $section): array
    {
        self::fields($section);

        $approved = AppearanceRevision::query()->approved()
            ->where('section', $section)->orderByDesc('id')->first();

        return array_replace(self::DEFAULTS[$section], $approved?->settings ?? []);
    }

    public static function value(string $section, string $field): mixed
    {
        return self::approved($section)[$field] ?? null;
    }
}
