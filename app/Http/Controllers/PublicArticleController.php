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

        $articles = ContentEntry::query()->where('type','posts')
            ->where('status','published')
            ->when($search !== '',fn($query)=>$query
                ->where(fn($inner)=>$inner->where('title','like','%'.$search.'%')
                    ->orWhere('summary','like','%'.$search.'%')))
            ->orderByDesc('id')->paginate(10)->withQueryString();

        return view('articles.index',compact('articles','search'));
    }

    public function show(ContentEntry $article): View
    {
        abort_unless($article->type === 'posts' && $article->status === 'published',404);

        return view('articles.show',compact('article'));
    }
}
