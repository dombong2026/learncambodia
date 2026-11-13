<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicCourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable','string','max:80'],
            'category' => ['nullable','string','max:100'],
            'level' => ['nullable',Rule::in(['A1','A2','B1','B2','C1','C2'])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $category = $filters['category'] ?? null;
        $level = $filters['level'] ?? null;

        $courses = Course::discoverable()
            ->with('category:id,slug,name,code')
            ->withCount('lessons')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('title','like','%'.$search.'%')
                    ->orWhere('summary','like','%'.$search.'%')
                    ->orWhereHas('category',fn($categories)=>$categories
                        ->where('name','like','%'.$search.'%'));
            }))
            ->when($category,fn($query)=>$query->whereHas('category',fn($categories)=>
                $categories->where('slug',$category)->where('is_published',true)))
            ->when($level, fn($query)=>$query->where('cefr_level',$level))
            ->orderBy('title')->paginate(10)->withQueryString();

        return view('courses.index', [
            'courses'=>$courses,'search'=>$search,'category'=>$category,
            'level'=>$level,
            'categories'=>Category::published()->orderBy('sort_order')
                ->get(['id','slug','name','code']),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless(Course::discoverable()->whereKey($course->id)->exists(),404);

        $course->load([
            'category:id,slug,name,code',
            'modules' => fn ($query) => $query->withCount('lessons'),
        ])->loadCount('lessons');

        $existingEnrollment = auth()->check() && auth()->user()->role === \App\Models\User::ROLE_STUDENT
            ? $course->enrollments()->where('student_id', auth()->id())->first()
            : null;

        return view('courses.show',compact('course','existingEnrollment'));
    }
}
