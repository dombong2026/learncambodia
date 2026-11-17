<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\PublicProfile;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q'=>['nullable','string','max:80'], 'category'=>['nullable','string','max:200']]);
        $search = trim((string) ($filters['q'] ?? ''));
        $category = !empty($filters['category'])
            ? Category::published()->whereHas('articles')->where('slug', $filters['category'])->firstOrFail()
            : null;

        $published = ContentEntry::query()->publishedArticles()
            ->when($category, fn ($query) => $query->where('category_id', $category->id));
        $articles = (clone $published)->with('featuredMedia')
            ->when($search !== '',fn($query)=>$query
                ->where(fn($inner)=>$inner->where('title','like','%'.$search.'%')
                    ->orWhere('summary','like','%'.$search.'%')))
            ->orderByDesc('id')->paginate(10)->withQueryString();


        $featured = (clone $published)->with('featuredMedia')->orderByDesc('id')->first();
        $publishedCount = $published->count();

        return view('articles.index',compact('articles','search','featured','publishedCount','category'));
    }

    public function show(string $article): View
    {
        $article = PublicId::lookup(ContentEntry::publishedArticles()->with(['category', 'featuredMedia']), $article);


        $related=ContentEntry::query()->publishedArticles()
            ->when($article->category_id, fn ($query) => $query->where('category_id', $article->category_id))
            ->whereKeyNot($article->id)->orderByDesc('id')->limit(4)->get();
        $author=PublicProfile::visible()->where('kind','instructor')
            ->where('user_id',$article->created_by)->first();
        // Legacy plaintext remains escaped; newer HTML is allowlisted by EditorHtml.
        $paragraphs=array_values(array_filter(preg_split('/\R{2,}/u',trim($article->body)) ?: [],fn($p)=>trim($p)!==''));
        return view('articles.show',compact('article','related','author','paragraphs'));
    }
}
