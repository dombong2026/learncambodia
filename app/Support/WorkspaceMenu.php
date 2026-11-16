<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

final class WorkspaceMenu
{
    /**
     * Only show links whose destinations exist. Older inactive modules
     * retain their configuration for future releases but do not appear as
     * clickable promises in the production workspace.
     */
    public static function groups(string $role): array
    {
        $sections = collect(config('workspaces.'.$role, []))->keyBy('key');
        $definitions = match ($role) {
            'admin' => [
                ['overview','Overview','layout',['dashboard']],
                ['content','Content & Appearance','palette',['appearance']],
                ['page-manager','Page Manager','files',['page-manager']],
                ['post-manager','Post Manager','newspaper',['post-manager']],
                ['policy-center','Policy Center','shield',['policy-center']],
                ['support-center','Support Center','headphones',['support-center']],
                ['course-manager','Course Manager','book',['courses','category','course-appearance']],
                ['examination','Examination','graduation',['examination']],
                ['quiz-manager','Quiz Manager','quiz-bank',['quiz-manager']],
                ['people','People & Accounts','users',['user-manager']],
                ['review','Reviews & Quality','shield',['approvals','academic-quality']],
                ['reporting','Analytics','activity',['learning-insights']],
            ],
            'student' => [
                ['overview','Overview','layout',['dashboard']],
                ['learning','My Learning','book',['learning']],
                ['coursework','Assignments & Grades','graduation',['homework','assessments']],
                ['support','Support','headphones',['support']],
            ],
            'instructor' => [
                ['overview','Overview','layout',['dashboard']],
                ['teaching','My Teaching','book',['teaching']],
                ['grading','Assignments & Grades','graduation',['assessment']],
            ],
            'developer' => [
                ['overview','Overview','layout',['dashboard']],
                ['api','API & Credentials','code',['api-center','credentials']],
                ['operations','Operations','activity',['operations']],
            ],
            default => [],
        };

        $groups = [];
        foreach ($definitions as [$key, $title, $icon, $sectionKeys]) {
            $items = [];
            foreach ($sectionKeys as $sectionKey) {
                foreach ($sections->get($sectionKey, [])['items'] ?? [] as $item) {
                    if (!isset($item['route']) || !Route::has($item['route'])) {
                        continue;
                    }
                    if (in_array($item['route'], [
                        'admin.profile.edit','account.profile.edit',
                        'instructor.profile.edit','developer.profile.edit',
                    ], true)) {
                        continue; // Dedicated, genuinely functional bottom-of-sidebar action.
                    }
                    $item['section_title'] = $sections[$sectionKey]['title'];
                    $item['icon'] = self::iconFor($sectionKey, $item['key']);
                    $items[] = $item;
                }
            }
            if ($role === 'admin' && $key === 'content' && Route::has('admin.media-library.index')) {
                $items[] = [
                    'key' => 'media-library', 'label' => 'Media Library', 'route' => 'admin.media-library.index',
                    'icon' => 'media-library', 'section_title' => 'Content & Appearance',
                ];
            }
            if ($role === 'admin' && $key === 'people' && Route::has('admin.public-profiles.index')) {
                $items[] = [
                    'key'=>'public-profiles','label'=>'Public Profile Reviews',
                    'route'=>'admin.public-profiles.index',
                    'icon'=>'id-card','section_title'=>'People & Accounts',
                ];
            }
            if ($items !== []) {
                $groups[] = compact('key', 'title', 'icon', 'items');
            }
        }

        return $groups;
    }

    /**
     * Keeps legacy direct links resolvable without showing disabled
     * integrations as active navigation.
     */
    public static function iconFor(string $section, string $item): string
    {
        // Matching icon names are mapped to original inline SVG drawings.
        return [
            'dashboard/overview' => 'layout',
            'appearance/all' => 'swatches',
            'appearance/header' => 'panel-top',
            'appearance/menu' => 'list-tree',
            'appearance/footer' => 'panel-bottom',
            'appearance/typography' => 'type',
            'appearance/home' => 'house',
            'appearance/about' => 'info',
            'appearance/contact' => 'mail',
            'course-appearance/course' => 'presentation',
            'course-appearance/catalog' => 'library',
            'examination/exams' => 'exam-sheet',
            'examination/create' => 'exam-create',
            'quiz-manager/quizzes' => 'quiz-bank',
            'quiz-manager/create' => 'quiz-create',
            'quiz-manager/quiz-types' => 'quiz-types',
            'examination/exam-types' => 'exam-types',
            'examination/certificate-types' => 'certificate-type',
            'examination/templates' => 'certificate-design',
            'examination/certificates' => 'certificate-issued',
            'appearance/catalog' => 'library',
            'appearance/auth' => 'lock',
            'page-manager/all' => 'files',
            'page-manager/create' => 'file-plus-2',
            'post-manager/all' => 'newspaper',
            'post-manager/create' => 'pen-square',
            'post-manager/category' => 'folders',
            'post-manager/tag' => 'hash',
            'support-center/contact' => 'inbox',
            'support-center/faqs' => 'help-circle',
            'support-center/help-articles' => 'book-text',
            'support-center/live-chat' => 'messages-square',
            'support-center/ticketing' => 'ticket',
            'policy-center/all' => 'scale',
            'policy-center/create' => 'shield-plus',
            'user-manager/all' => 'user-round',
            'category/all' => 'tags',
            'category/create' => 'tag',
            'category/cefr' => 'graduation',
            'courses/all' => 'book-open',
            'courses/create' => 'book-plus',
            'courses/instructors' => 'school',
            'courses/enrollments' => 'user-check',
            'courses/quizzes' => 'brain',
            'courses/homework' => 'clipboard-check',
            'approvals/course-reviews' => 'check-check',
            'approvals/appearance-reviews' => 'eye',
            'approvals/enrollment-reviews' => 'user-round-check',
            'learning-insights/overview' => 'chart-column',
            'academic-quality/gradebook' => 'chart-line',
            'academic-quality/evidence' => 'badge-check',
            'learning/categories' => 'compass',
            'learning/courses' => 'library-big',
            'learning/progress' => 'chart-pie',
            'learning/cefr' => 'award',
            'learning/course-catalog' => 'book-marked',
            'learning/saved-courses' => 'bookmark-check',
            'learning/study-planner' => 'calendar-check',
            'learning/lesson-notes' => 'notebook-text',
            'homework/available' => 'notebook-pen',
            'homework/submissions' => 'send',
            'assessments/standalone-exams' => 'exam-sheet',
            'assessments/standalone-quizzes' => 'quiz-bank',
            'assessments/practice' => 'list-check',
            'assessments/practice-history' => 'history',
            'support/help' => 'life-buoy',
            'teaching/courses' => 'book-open-check',
            'teaching/assignments' => 'file-pen',
            'teaching/insights' => 'chart-column',
            'assessment/submissions' => 'clipboard-list',
            'assessment/feedback-templates' => 'message-square',
            'assessment/review' => 'shield-check',
            'api-center/endpoints' => 'terminal',
            'api-center/docs' => 'file-code',
            'api-center/catalog' => 'braces',
            'credentials/tokens' => 'key-round',
            'credentials/new' => 'key-square',
            'operations/usage' => 'gauge',
        ][$section.'/'.$item] ?? 'file';
    }

    public static function find(string $role, string $section, string $item): ?array
    {
        foreach (config('workspaces.'.$role, []) as $group) {
            if ($group['key'] !== $section) continue;
            foreach ($group['items'] as $entry) {
                if ($entry['key'] === $item) {
                    return $entry + ['section_title' => $group['title']];
                }
            }
        }

        return null;
    }

    public static function href(string $role, array $group, array $item): string
    {
        if (isset($item['route']) && Route::has($item['route'])) {
            return route($item['route'], $item['parameters'] ?? []);
        }

        return self::landing($role);
    }

    public static function landing(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.overview'),
            'developer' => route('developer.overview'),
            'instructor' => route('instructor.overview'),
            default => route('account.overview'),
        };
    }

    public static function profile(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.profile.edit'),
            'developer' => route('developer.profile.edit'),
            'instructor' => route('instructor.profile.edit'),
            default => route('account.profile.edit'),
        };
    }

    public static function topAction(string $role): array
    {
        return match ($role) {
            'admin' => ['label' => __('workspace.create_course'), 'url' => route('admin.course-builder.create')],
            'instructor' => ['label' => __('workspace.my_courses'), 'url' => route('instructor.courses.index')],
            'developer' => ['label' => __('workspace.access_tokens'), 'url' => route('developer.tokens.index')],
            default => ['label' => __('workspace.my_learning'), 'url' => route('account.enrollments.index')],
        };
    }

    public static function mobileItems(string $role): array
    {
        $links = match ($role) {
            'admin' => [
                ['admin.overview','Overview','layout'],
                ['admin.courses.index','Courses','book'],
                ['admin.reviews.index','Reviews','shield'],
                ['admin.learning.report','Reports','activity'],
                ['admin.profile.edit','Profile','settings'],
            ],
            'instructor' => [
                ['instructor.overview','Overview','layout'],
                ['instructor.courses.index','Courses','book'],
                ['instructor.grading.index','Grading','graduation'],
                ['instructor.homework.index','Homework','pen'],
                ['instructor.profile.edit','Profile','settings'],
            ],
            'developer' => [
                ['developer.overview','Overview','layout'],
                ['developer.apis','APIs','code'],
                ['developer.tokens.index','Tokens','key'],
                ['developer.usage','Usage','activity'],
                ['developer.profile.edit','Profile','settings'],
            ],
            default => [
                ['account.overview','Overview','layout'],
                ['account.enrollments.index','Courses','book'],
                ['account.homework.index','Homework','file'],
                ['account.progress.index','Progress','activity'],
                ['account.profile.edit','Profile','settings'],
            ],
        };

        return array_map(fn ($item) => [
            'label' => __('workspace.nav.'.$item[1]),
            'url' => route($item[0]),
            'icon' => $item[2],
            'active' => Route::currentRouteName() === $item[0],
        ], array_filter($links, fn ($item) => Route::has($item[0])));
    }

    public static function trail(string $role, string $heading): array
    {
        $current = Route::currentRouteName() ?? '';
        $rootName = match ($role) {
            'admin' => 'admin.overview',
            'instructor' => 'instructor.overview',
            'developer' => 'developer.overview',
            default => 'account.overview',
        };
        if ($current === $rootName) {
            return [['label' => __('workspace.overview'), 'url' => null]];
        }

        $items = [['label' => __('workspace.overview'), 'url' => self::landing($role)]];
        $parent = null;
        // Many editorial kinds share one Laravel route name; identify their
        // actual manager from the validated kind parameter, not the first
        // matching sidebar item (which happens to be Page Manager).
        if ($role === 'admin' && str_starts_with($current, 'admin.editorial.')) {
            $kind = request()->route('kind');
            $parentTitle = match ($kind) {
                'pages' => 'Page Manager',
                'posts' => 'Post Manager',
                'policies' => 'Policy Center',
                'faqs', 'help-articles' => 'Support Center',
                default => null,
            };
            if ($parentTitle !== null) {
                $parent = [
                    'label' => $parentTitle,
                    'url' => route('admin.editorial.index', ['kind' => $kind]),
                ];
            }
        }
        if ($role === 'admin' && $current === 'support.tickets.create') {
            $parent = [
                'label' => 'Support Center',
                'url' => route('admin.support.index', ['kind' => 'tickets']),
            ];
        }
        if ($parent === null) foreach (self::groups($role) as $group) {
            foreach ($group['items'] as $item) {
                if (($item['route'] ?? '') === $current
                    && collect($item['parameters'] ?? [])->every(fn ($value, $key) =>
                        (string) (request()->route($key) ?? request()->query($key, $key === 'focus' ? 'courses' : '')) === (string) $value)) {
                    $parent = [
                        'label' => $group['title'],
                        'url' => self::href($role, $group, $group['items'][0]),
                    ];
                    break 2;
                }
            }
        }

        if (!$parent) {
            $prefix = explode('.', $current)[1] ?? '';
            $route = match ($role.':'.$prefix) {
                'admin:course-builder' => 'admin.courses.index',
                'admin:exams' => 'admin.exams.index',
                'admin:quizzes-manager' => 'admin.quizzes-manager.index',
                'account:assessment-attempts' => 'assessments.exams',
                'admin:certificate-templates' => 'admin.certificate-templates.index',
                'admin:appearance' => 'admin.appearance.index',
                'admin:editorial' => 'admin.editorial.index',
                'instructor:grading' => 'instructor.grading.index',
                'instructor:homework' => 'instructor.homework.index',
                'instructor:courses' => 'instructor.courses.index',
                'account:homework' => 'account.homework.index',
                'account:enrollments' => 'account.enrollments.index',
                'account:saved-courses' => 'account.saved-courses.index',
                'account:practice' => 'account.practice.index',
                default => null,
            };
            if ($route && Route::has($route)) {
                $params = $route === 'admin.editorial.index' ? ['kind' => (request()->route('kind') ?? 'posts')] : [];
                $parent = ['label' => __('workspace.back_to_section'), 'url' => route($route, $params)];
            }
        }

        if ($parent && $parent['url'] !== url()->current()) $items[] = $parent;
        $items[] = ['label' => $heading, 'url' => null];

        return $items;
    }
}
