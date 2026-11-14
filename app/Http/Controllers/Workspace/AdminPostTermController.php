<?php
namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PostTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminPostTermController extends Controller {
    private function type(string $kind): string {
        abort_unless(in_array($kind,['categories','tags'],true),404);
        return $kind === 'categories' ? 'category' : 'tag';
    }
    private function check(string $kind, PostTerm $term): void {
        abort_unless($term->kind === $this->type($kind),404);
    }
    public function index(Request $request, string $kind): View {
        $type=$this->type($kind);
        $data=$request->validate(['q'=>['nullable','string','max:80']]);
        $query=trim((string)($data['q']??''));
        $terms=PostTerm::where('kind',$type)->when($query!=='',fn($q)=>$q->where('name','like','%'.$query.'%'))
            ->orderBy('name')->paginate(10)->withQueryString();
        return view('workspace.admin.post-terms-index',compact('kind','terms','query'));
    }
    public function create(string $kind): View {
        $this->type($kind);
        return view('workspace.admin.post-terms-form',['kind'=>$kind,'term'=>new PostTerm(),'editing'=>false]);
    }
    public function store(Request $request, string $kind): RedirectResponse {
        $type=$this->type($kind);
        PostTerm::create($this->data($request,$type)+['kind'=>$type]);
        return redirect()->route('admin.post-terms.index',compact('kind'))->with('status','Taxonomy created.');
    }
    public function edit(string $kind, PostTerm $term): View {
        $this->check($kind,$term);
        return view('workspace.admin.post-terms-form',compact('kind','term')+['editing'=>true]);
    }
    public function update(Request $request, string $kind, PostTerm $term): RedirectResponse {
        $this->check($kind,$term); $term->update($this->data($request,$term->kind,$term));
        return redirect()->route('admin.post-terms.index',compact('kind'))->with('status','Taxonomy updated.');
    }
    public function destroy(string $kind, PostTerm $term): RedirectResponse {
        $this->check($kind,$term); $term->delete();
        return back()->with('status','Taxonomy deleted and detached from posts.');
    }
    private function data(Request $request, string $type, ?PostTerm $term=null): array {
        $slug=Str::slug((string)($request->input('slug')?:$request->input('name')));
        $request->merge(['slug'=>$slug]);
        return $request->validate([
            'name'=>['required','string','max:120'],
            'slug'=>['required','max:160','regex:/^[a-z0-9-]+$/',
                Rule::unique('post_terms','slug')->where('kind',$type)->ignore($term?->id)],
            'description'=>['nullable','string','max:500'],
        ]);
    }
}