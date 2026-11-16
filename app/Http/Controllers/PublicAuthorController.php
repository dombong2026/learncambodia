<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\Category;
use App\Models\Course;
use App\Models\PublicProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicAuthorController extends Controller
{
    public function index(Request $request): View
    {
        $data=$request->validate(['q'=>['nullable','string','max:80']]);
        $search=trim($data['q']??'');

        $articles=ContentEntry::query()->publishedArticles()
            ->when($search!=='',fn($q)=>$q->where(fn($s)=>
                $s->where('title','like','%'.$search.'%')
                    ->orWhere('summary','like','%'.$search.'%')))
            ->orderByDesc('id')->paginate(9)->withQueryString();

        $approvedAuthorIds=ContentEntry::query()->publishedArticles()->distinct()->pluck('created_by');
        $authors=PublicProfile::visible()->where('kind','instructor')
            ->whereIn('user_id',$approvedAuthorIds)
            ->orderBy('display_name')->limit(8)->get();

        $articleCount=ContentEntry::query()->publishedArticles()->count();
        $courseCount=Course::discoverable()->count();
        $categoryCount=Category::visibleInCatalog()->count();

        return view('authors.index',compact('articles','authors','articleCount','courseCount','categoryCount','search'));
    }

    public function show(PublicProfile $profile): View
    {
        abort_unless(PublicProfile::visible()->where('kind','instructor')
            ->whereKey($profile->id)->exists(),404);
        $articles=ContentEntry::query()->publishedArticles()->where('created_by',$profile->user_id)
            ->orderByDesc('id')->paginate(9);
        abort_if($articles->total()===0,404);

        return view('authors.show',compact('profile','articles'));
    }
}
