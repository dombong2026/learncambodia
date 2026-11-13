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
        'blog' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','feature_heading'=>'text:140','articles_heading'=>'text:140','resources_heading'=>'text:140','cta_heading'=>'text:140'],
        'single_blog' => ['reading_heading'=>'text:140','outline_heading'=>'text:140','related_heading'=>'text:140','engagement_heading'=>'text:140','cta_heading'=>'text:140'],
        'recommendations' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','courses_heading'=>'text:140','activity_heading'=>'text:140','goals_heading'=>'text:140','cta_heading'=>'text:140'],
        'leaderboard_trophy' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','milestone_heading'=>'text:140','method_heading'=>'text:140','privacy_heading'=>'text:140','cta_heading'=>'text:140'],
        'course_exam_catalog' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','course_heading'=>'text:140','format_heading'=>'text:140','resources_heading'=>'text:140','cta_heading'=>'text:140'],
        'single_course_exam' => ['intro_heading'=>'text:140','quiz_heading'=>'text:140','steps_heading'=>'text:140','related_heading'=>'text:140','cta_heading'=>'text:140'],
        'course_watch' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','curriculum_heading'=>'text:140','features_heading'=>'text:140','privacy_heading'=>'text:140','cta_heading'=>'text:140'],
        'reading' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','articles_heading'=>'text:140','methods_heading'=>'text:140','pathways_heading'=>'text:140','cta_heading'=>'text:140'],
        'library' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','resources_heading'=>'text:140','courses_heading'=>'text:140','access_heading'=>'text:140','cta_heading'=>'text:140'],
        'upload_studio' => ['hero_heading'=>'text:140','hero_intro'=>'text:550','courses_heading'=>'text:140','tools_heading'=>'text:140','review_heading'=>'text:140','cta_heading'=>'text:140'],
        'course' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'catalog' => ['heading' => 'text:100', 'intro' => 'text:350'],
        'auth' => ['login_heading' => 'text:100', 'register_heading' => 'text:100'],
    ];

    public const LABELS = [
        'home'=>'Home', 'categories'=>'Categories',
        'single_category'=>'Single Category', 'author'=>'Author',
        'about'=>'About', 'contact'=>'Contact',
        'blog'=>'Blog', 'single_blog'=>'Single Blog',
        'recommendations'=>'Recommendations', 'leaderboard_trophy'=>'Leaderboard & Trophy',
        'course_exam_catalog'=>'Course Exam Catalog', 'single_course_exam'=>'Single Course Exam',
        'course_watch'=>'Course Watch', 'reading'=>'Reading', 'library'=>'Library',
        'upload_studio'=>'Upload Studio',
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
        'blog'=>'articles.index',
        'single_blog'=>'articles.index',
        'recommendations'=>'learning.recommendations',
        'leaderboard_trophy'=>'learning.leaderboard',
        'course_exam_catalog'=>'learning.exams',
        'single_course_exam'=>'learning.exams',
        'course_watch'=>'learning.watch',
        'reading'=>'learning.reading',
        'library'=>'learning.library',
        'upload_studio'=>'learning.studio',
        'course'=>'courses.index',
    ];

    public static function preview(string $section): ?string
    {
        $route = self::PAGE_PREVIEWS[$section] ?? null;
        if (!$route || !\Illuminate\Support\Facades\Route::has($route)) return null;

        if ($section==='single_blog') {
            $article=\App\Models\ContentEntry::query()->where('type','post')
                ->where('status','published')->orderByDesc('id')->first();
            return $article ? route('articles.show',$article->slug) : route('articles.index');
        }
        if ($section==='single_course_exam') {
            $course=\App\Models\Course::discoverable()->whereHas('quizzes',fn($q)=>$q->has('questions'))
                ->orderBy('id')->first();
            return $course ? route('learning.exam.show',$course->slug) : route('learning.exams');
        }
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
        'blog' => [
            'hero_heading'=>'Educational articles and study insights',
            'hero_intro'=>'Explore independently reviewed articles, practical learning guides and real educational resources.',
            'feature_heading'=>'Featured educational articles',
            'articles_heading'=>'Recent educational articles',
            'resources_heading'=>'Explore more learning resources',
            'cta_heading'=>'Keep learning with trusted materials',
        ],
        'single_blog' => [
            'reading_heading'=>'Read and explore',
            'outline_heading'=>'Article overview',
            'related_heading'=>'Related educational articles',
            'engagement_heading'=>'Continue the conversation thoughtfully',
            'cta_heading'=>'Continue learning',
        ],
        'recommendations' => [
            'hero_heading'=>'Your learning pathways',
            'hero_intro'=>'Discover recommended learning paths based on your own approved enrollments and published course categories.',
            'courses_heading'=>'Relevant published courses',
            'activity_heading'=>'Your learning progress',
            'goals_heading'=>'Next study actions',
            'cta_heading'=>'Build your learning plan',
        ],
        'leaderboard_trophy' => [
            'hero_heading'=>'Learning milestones and achievements',
            'hero_intro'=>'Track milestones earned through your own learning records. Public rankings and rewards are not yet available.',
            'milestone_heading'=>'Your recorded achievements',
            'method_heading'=>'How milestones are recognized',
            'privacy_heading'=>'Student privacy comes first',
            'cta_heading'=>'Continue your progress',
        ],
        'course_exam_catalog' => [
            'hero_heading'=>'Practice assessments and exam preparation',
            'hero_intro'=>'Explore reviewed courses with optional in-platform practice quizzes. These are not official examinations.',
            'course_heading'=>'Courses with practice assessments',
            'format_heading'=>'How practice assessments work',
            'resources_heading'=>'Prepare with reviewed course resources',
            'cta_heading'=>'Start a learning assessment',
        ],
        'single_course_exam' => [
            'intro_heading'=>'About this practice preparation',
            'quiz_heading'=>'Available practice quizzes',
            'steps_heading'=>'Prepare and check your understanding',
            'related_heading'=>'Explore related preparation',
            'cta_heading'=>'Request access to practice',
        ],
        'course_watch' => [
            'hero_heading'=>'Focused lesson learning',
            'hero_intro'=>'Read protected lessons, monitor your own progress and access approved practice from your learning account.',
            'curriculum_heading'=>'Available learning pathways',
            'features_heading'=>'Study tools and progress',
            'privacy_heading'=>'Protected course materials',
            'cta_heading'=>'Open your learning workspace',
        ],
        'reading' => [
            'hero_heading'=>'Reading and literacy',
            'hero_intro'=>'Explore published educational reading, language guidance and study tips. All materials pass independent editorial review.',
            'articles_heading'=>'Reading and literacy resources',
            'methods_heading'=>'Build a reading routine',
            'pathways_heading'=>'Related learning pathways',
            'cta_heading'=>'Continue reading',
        ],
        'library' => [
            'hero_heading'=>'Digital learning library',
            'hero_intro'=>'Discover published reading materials, course outlines and CEFR reference guides in one organized library.',
            'resources_heading'=>'Published educational resources',
            'courses_heading'=>'Related published courses',
            'access_heading'=>'Resource access and permissions',
            'cta_heading'=>'Explore available learning',
        ],
        'upload_studio' => [
            'hero_heading'=>'Educator content studio',
            'hero_intro'=>'Prepare assignments and instructional material through authorized instructor workflows and independent academic review.',
            'courses_heading'=>'Your approved teaching assignments',
            'tools_heading'=>'Course and assignment tools',
            'review_heading'=>'Review and publication workflow',
            'cta_heading'=>'Open your teaching workspace',
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
