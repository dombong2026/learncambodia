<?php

namespace App\Http\Controllers;

use App\Models\CefrLevel;
use App\Models\CourseExam;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CefrController extends Controller
{
    public function index(Request $request): View
    {
        $levels = CefrLevel::query()->orderBy('sort_order')->get();

        $requestedLevel = $request->query('practice');
        $practiceLevel = is_string($requestedLevel) && $levels->contains('code', $requestedLevel)
            ? $requestedLevel
            : null;
        $quizzes = collect();

        if ($practiceLevel !== null) {
            $quizzes = CourseExam::query()
                ->whereNull('course_id')
                ->where('kind', 'quiz')
                ->where('published', true)
                ->where('starter_key', 'like', 'cefr-practice-'.strtolower($practiceLevel).'-%')
                ->orderBy('starter_key')
                ->get(['public_id', 'title']);
        }

        return view('cefr.index', compact('levels', 'practiceLevel', 'quizzes'));
    }
}
