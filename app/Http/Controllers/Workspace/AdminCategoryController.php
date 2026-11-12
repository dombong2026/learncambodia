<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $query = trim($filters['q'] ?? '');

        $categories = Category::query()
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query) {
                $inner->where('code', 'like', '%'.$query.'%')
                    ->orWhere('name', 'like', '%'.$query.'%');
            }))->orderBy('sort_order')->paginate(10)->withQueryString();

        return view('workspace.admin.categories', compact('categories', 'query'));
    }

    public function create(): View
    {
        return view('workspace.admin.category-form', [
            'category' => new Category(), 'mode' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        $category = Category::create($validated + [
            'slug' => Str::slug($validated['code'].'-'.$validated['name']),
        ]);

        return redirect()->route('admin.categories.edit', $category)
            ->with('status', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('workspace.admin.category-form', compact('category') + ['mode' => 'edit']);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);
        $category->update($validated);

        return back()->with('status', 'Category updated.');
    }

    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/',
                $category ? Rule::in([$category->code]) : Rule::unique('categories', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'group' => ['required', Rule::in(array_keys(Category::GROUPS))],
            'description' => ['required', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:99999'],
            'is_published' => ['required', 'boolean'],
        ]);
    }
}
