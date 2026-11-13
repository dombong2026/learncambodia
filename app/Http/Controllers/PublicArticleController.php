<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q'=>['nullable','string','max:80']]);
        $search = trim((string) ($filters['q'] ?? ''));

        $articles = ContentEntry::query()->where('type','post')
            ->where('status','published')
            ->when($search !== '',fn($query)=>$query
                ->where(fn($inner)=>$inner->where('title','like','%'.$search.'%')
                    ->orWhere('summary','like','%'.$search.'%')))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('articles.index',compact('articles','search'));
    }

    public function show(string $article): View
    {
        $article = ContentEntry::query()->where('type','post')->where('status','published')
            ->where('slug',$article)->firstOrFail();

        return view('articles.show',compact('article'));
    }
}
