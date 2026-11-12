<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogApiController extends Controller
{
    private function pagination(Request $request): int
    {
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return (int) ($validated['per_page'] ?? 10);
    }

    public function categories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['sometimes', 'string', Rule::in(array_keys(Category::GROUPS))],
        ]);
        $perPage = $this->pagination($request);

        $result = Category::query()->published()
            ->when(isset($validated['group']), fn ($builder) => $builder->where('group', $validated['group']))
            ->orderBy('sort_order')
            ->paginate($perPage, ['code', 'slug', 'name', 'group', 'description']);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function levels(Request $request): JsonResponse
    {
        $result = CefrLevel::query()->orderBy('sort_order')
            ->paginate($this->pagination($request), ['code', 'category', 'name', 'description']);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function courses(Request $request): JsonResponse
    {
        $result = Course::query()->published()->orderBy('title')
            ->paginate($this->pagination($request), ['id', 'category_id', 'slug', 'title', 'summary', 'cefr_level', 'delivery_mode']);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
