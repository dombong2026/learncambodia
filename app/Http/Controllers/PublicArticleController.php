<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\PublicProfile;
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


        $featured=ContentEntry::query()->where('type','post')->where('status','published')
            ->orderByDesc('id')->first();
        $publishedCount=ContentEntry::query()->where('type','post')
            ->where('status','published')->count();

        return view('articles.index',compact('articles','search','featured','publishedCount'));
    }

    public function show(string $article): View
    {
        $article = ContentEntry::query()->where('type','post')->where('status','published')
            ->where('slug',$article)->firstOrFail();


        $related=ContentEntry::query()->where('type','post')->where('status','published')
            ->whereKeyNot($article->id)->orderByDesc('id')->limit(4)->get();
        $author=PublicProfile::visible()->where('kind','instructor')
            ->where('user_id',$article->created_by)->first();
        // Safely present the editorial text as distinct paragraphs; never parse it as HTML.
        $paragraphs=array_values(array_filter(preg_split('/\R{2,}/u',trim($article->body)) ?: [],fn($p)=>trim($p)!==''));
        return view('articles.show',compact('article','related','author','paragraphs'));
    }
}
