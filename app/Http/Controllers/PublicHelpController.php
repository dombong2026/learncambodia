<?php
namespace App\Http\Controllers;

use App\Models\ContentEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicHelpController extends Controller
{
    public function index(Request $request): View
    {
        $data=$request->validate(['q'=>['nullable','string','max:80']]);
        $query=trim((string)($data['q']??''));
        $content=fn($type)=>ContentEntry::where('type',$type)->where('status','published')
            ->when($query!=='',fn($q)=>$q->where(function($inner)use($query){
                $inner->where('title','like','%'.$query.'%')
                    ->orWhere('summary','like','%'.$query.'%');
            }))->orderBy('title');
        $faqs=$content('faq')->limit(20)->get();
        $articles=$content('help_article')->paginate(10)->withQueryString();
        return view('support.public-help',compact('faqs','articles','query'));
    }

    public function show(string $kind, string $entry): View
    {
        abort_unless(in_array($kind,['faqs','articles'],true),404);
        $type=$kind==='faqs'?'faq':'help_article';
        $content=ContentEntry::where('type',$type)->where('status','published')
            ->where('slug',$entry)->firstOrFail();
        return view('support.public-help-show',compact('content'));
    }
}
