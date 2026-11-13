<?php

namespace App\Http\Controllers;

use App\Models\CefrLevel;
use Illuminate\View\View;

class CefrController extends Controller
{
    public function index(): View
    {
        $levels = CefrLevel::query()->orderBy('sort_order')->get();

        return view('cefr.index', compact('levels'));
    }
}
