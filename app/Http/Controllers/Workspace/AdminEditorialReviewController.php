<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEditorialReviewController extends Controller
{
    public function index(string $kind): View
    {
        abort_unless($kind === 'posts',404);

        return view('workspace.admin.editorial-reviews',[
            'role'=>'admin','kind'=>$kind,
            'entries'=>ContentEntry::query()->where('type','post')
                ->where('status','pending_review')
                ->with('creator:id,name')
                ->orderBy('id')->paginate(10),
        ]);
    }

    public function submit(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($kind === 'posts' && $entry->type === 'post',404);
        DB::transaction(function() use ($request,$entry): void {
            $post=ContentEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($post->status,['draft','rejected'],true),409);
            abort_if(trim((string) $post->body)==='' || trim((string)$post->title)==='',422);

            $before=$post->status;
            $post->update(['status'=>'pending_review','updated_by'=>$request->user()->id]);
            ApprovalAudit::record('editorial_post',$post->id,$request->user(),
                'submitted',$before,'pending_review');
        });

        return redirect()->route('admin.editorial.index',['kind'=>'posts'])
            ->with('status','Article submitted for independent editorial approval.');
    }

    public function review(Request $request, string $kind, ContentEntry $entry): RedirectResponse
    {
        abort_unless($kind === 'posts' && $entry->type === 'post',404);
        $data=$request->validate([
            'decision'=>['required',Rule::in(['approve','reject'])],
            'note'=>['nullable','string','max:500','required_if:decision,reject'],
        ]);

        DB::transaction(function() use ($request,$entry,$data): void {
            $post=ContentEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless($post->status === 'pending_review',409);
            abort_if($post->created_by === $request->user()->id ||
                $post->updated_by === $request->user()->id,403,
                'The author or submitting editor cannot approve their own work.');

            $next=$data['decision']==='approve' ? 'published' : 'rejected';
            $post->update(['status'=>$next]);
            ApprovalAudit::record('editorial_post',$post->id,$request->user(),
                $data['decision'],'pending_review',$next,$data['note']??null);
        });

        return back()->with('status', 'Editorial decision recorded. Publication is visible only if approved.');
    }
}
