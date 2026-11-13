<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\ContentEntry;
use App\Models\PublicProfile;
use App\Models\CourseInstructorAssignment;
use App\Models\PublicProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function home(): View
    {
        $featuredCategories = Category::query()->published()
            ->whereIn('code', ['GE', 'IELTS', 'TOEFL', 'TEFL', 'TESOL', 'CEFR'])
            ->orderBy('sort_order')->get();

        $featuredCourses = Course::discoverable()->with('category')
            ->withCount('lessons')->orderByDesc('published_at')->limit(6)->get();
        $latestArticles = ContentEntry::query()->where('type','post')
            ->where('status','published')->orderByDesc('id')->limit(3)->get();

        $featuredInstructors = PublicProfile::visible()->where('kind','instructor')
            ->orderByDesc('published_at')->limit(4)->get();
        $publishedCourseCount = Course::discoverable()->count();
        $publishedCategoryCount = Category::published()->count();
        $publishedArticleCount = ContentEntry::query()->where('type','post')
            ->where('status','published')->count();

        return view('home', compact(
            'featuredCategories','featuredCourses','latestArticles',
            'featuredInstructors','publishedCourseCount',
            'publishedCategoryCount','publishedArticleCount'
        ));
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'group' => ['nullable', 'string', Rule::in(array_keys(Category::GROUPS))],
        ]);
        $query = trim($filters['q'] ?? '');
        $group = $filters['group'] ?? null;

        $categories = Category::query()->published()
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

        $groupCounts = Category::published()->select('group')
            ->selectRaw('COUNT(*) AS aggregate_count')
            ->groupBy('group')->pluck('aggregate_count','group');
        $publishedCategories = Category::published()->count();
        $publishedCourses = Course::discoverable()->count();
        $featuredInstructors = PublicProfile::visible()->where('kind','instructor')
            ->orderBy('display_name')->limit(4)->get();
        $articles = ContentEntry::query()->where('type','post')
            ->where('status','published')->orderByDesc('id')->limit(3)->get();

        return view('categories.index', compact(
            'categories','group','query','groupCounts','publishedCategories',
            'publishedCourses','featuredInstructors','articles'
        ));
    }

    public function show(Category $category): View
    {
        abort_unless($category->is_published, 404);

        $relatedCategories = Category::query()->published()
            ->where('group', $category->group)
            ->whereKeyNot($category->getKey())
            ->orderBy('sort_order')->limit(6)->get();

        $courses = $category->courses()->discoverable()
            ->with('category:id,slug,name,code')->withCount('lessons')
            ->orderBy('title')->paginate(12);

        $publishedCourseCount = $category->courses()->discoverable()->count();
        $instructorIds = CourseInstructorAssignment::query()
            ->where('status','active')
            ->whereHas('course', fn($q)=>$q->discoverable()
                ->where('category_id',$category->id))
            ->pluck('instructor_id');

        $featuredInstructors = PublicProfile::visible()->where('kind','instructor')
            ->whereIn('user_id',$instructorIds)->orderBy('display_name')->limit(4)->get();

        $articles = ContentEntry::query()->where('type','post')
            ->where('status','published')->orderByDesc('id')->limit(3)->get();

        return view('categories.show', compact(
            'category','relatedCategories','courses','publishedCourseCount',
            'featuredInstructors','articles'
        ));
    }
}
