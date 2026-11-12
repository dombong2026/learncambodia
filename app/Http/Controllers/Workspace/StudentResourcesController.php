<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CefrLevel;
use Illuminate\View\View;

class StudentResourcesController extends Controller
{
    public function categories(): View
    {
        return view('workspace.student.categories', [
            'role' => 'student',
            'categories' => Category::query()->published()->orderBy('sort_order')->paginate(10),
        ]);
    }

    public function cefr(): View
    {
        return view('workspace.student.cefr', [
            'role' => 'student',
            'levels' => CefrLevel::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function help(): View
    {
        return view('workspace.student.help', ['role' => 'student']);
    }
}
