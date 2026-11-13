<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\ContentEntry;
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

        return view('home', compact('featuredCategories','featuredCourses','latestArticles'));
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
            ->when($group, fn ($builder) => $builder->where('group', $group))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner->where('code', 'like', '%'.$query.'%')
                        ->orWhere('name', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->orderBy('sort_order')->paginate(12)->withQueryString();

        return view('categories.index', compact('categories', 'group', 'query'));
    }

    public function show(Category $category): View
    {
        abort_unless($category->is_published, 404);

        $relatedCategories = Category::query()->published()
            ->where('group', $category->group)
            ->whereKeyNot($category->getKey())
            ->orderBy('sort_order')->limit(4)->get();

        $courses = $category->courses()->discoverable()->orderBy('title')->paginate(9);

        return view('categories.show', compact('category', 'relatedCategories', 'courses'));
    }
}
