<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\ContentEntry;
use App\Models\CourseExam;
use App\Models\PublicProfile;
use App\Models\CourseInstructorAssignment;
use App\Support\Appearance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function home(): View
    {
        $assessments = CourseExam::query()
            ->whereNull('course_id')->where('published', true)->orderByDesc('id')->limit(4);
        $assessmentColumns = ['id', 'public_id', 'kind', 'title'];
        $featuredExams = (clone $assessments)->where('kind', 'exam')->get($assessmentColumns);
        $featuredQuizzes = $assessments->where('kind', 'quiz')->get($assessmentColumns);

        $featuredCourses = Course::discoverable()->orderByDesc('published_at')->orderByDesc('id')
            ->limit(4)->get(['id', 'public_id', 'slug', 'title']);
        $latestArticles = ContentEntry::query()->publishedArticles()
            ->orderByDesc('id')->limit(4)->get(['id', 'public_id', 'title']);

        $heroCourses = collect();
        if (Appearance::value('home', 'hero_mode') === 'course_player') {
            $preferred = (int) (Appearance::value('home', 'hero_course_id') ?? 0);
            $heroCourses = Course::discoverable()
                ->with(['featuredMedia', 'modules.lessons'])
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferred])
                ->orderByRaw("CASE WHEN featured_kind IN ('video', 'youtube', 'image') THEN 0 ELSE 1 END")
                ->orderByDesc('published_at')->limit(6)->get();
        }

        return view('home', compact('featuredExams', 'featuredQuizzes', 'featuredCourses', 'latestArticles', 'heroCourses'));
    }

    public function navigationCategories(): Response
    {
        return response()->view('partials.category-tray-links', [
            'navigationCategories' => Category::forNavigation()->get(),
        ], 200, ['Cache-Control' => 'no-store', 'X-Category-Navigation' => '1']);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'group' => ['nullable', 'string', Rule::in(array_keys(Category::GROUPS))],
        ]);
        $query = trim($filters['q'] ?? '');
        $group = $filters['group'] ?? null;

        $categories = Category::query()->visibleInCatalog()
            ->withCount(['courses as published_courses_count'=> fn($q)=>$q->discoverable()])
            ->when($group, fn ($builder) => $builder->where('group', $group))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('code', 'like', '%'.$query.'%')
                        ->orWhere('name', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('sort_order')->paginate(12)->withQueryString();

        $groupCounts = Category::visibleInCatalog()->select('group')
            ->selectRaw('COUNT(*) AS aggregate_count')
            ->groupBy('group')->pluck('aggregate_count','group');
        $publishedCategories = Category::visibleInCatalog()->count();
        $publishedCourses = Course::discoverable()->count();
        $cefrLevelCount=CefrLevel::query()->count();
        $featuredInstructors = PublicProfile::visible()->where('kind','instructor')
            ->orderBy('display_name')->limit(4)->get();
        $articles = ContentEntry::query()->publishedArticles()->orderByDesc('id')->limit(3)->get();

        return view('categories.index', compact(
            'categories','group','query','groupCounts','publishedCategories',
            'publishedCourses','featuredInstructors','articles','cefrLevelCount'
        ));
    }

    public function show(Category $category): View
    {
        abort_unless(Category::visibleInCatalog()->whereKey($category->id)->exists(), 404);

        $relatedCategories = Category::query()->visibleInCatalog()
            ->where('group', $category->group)
            ->whereKeyNot($category->getKey())
            ->orderBy('sort_order')->limit(6)->get();

        $courses = $category->courses()->discoverable()
            ->with(['category:id,slug,name,code', 'featuredMedia'])->withCount('lessons')
            ->orderBy('title')->paginate(12);

        $publishedCourseCount = $category->courses()->discoverable()->count();
        $cefrLevelCount=CefrLevel::query()->count();
        $instructorIds = CourseInstructorAssignment::query()
            ->where('status','active')
            ->whereHas('course', fn($q)=>$q->discoverable()
                ->where('category_id',$category->id))
            ->pluck('instructor_id');

        $featuredInstructors = PublicProfile::visible()->where('kind','instructor')
            ->whereIn('user_id',$instructorIds)->orderBy('display_name')->limit(4)->get();

        $articles = $category->articles()->with('featuredMedia')->orderByDesc('id')->paginate(10, pageName: 'articles_page');

        return view('categories.show', compact(
            'category','relatedCategories','courses','publishedCourseCount',
            'featuredInstructors','articles','cefrLevelCount'
        ));
    }
}
