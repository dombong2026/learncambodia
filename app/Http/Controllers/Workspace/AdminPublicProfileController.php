<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PublicProfile;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminPublicProfileController extends Controller
{
    public function index(): View
    {
        return view('workspace.admin.public-profile-reviews',[
            'role'=>'admin',
            'profiles'=>PublicProfile::query()->where('status','pending_review')
                ->with('user:id,role')->orderBy('id')->paginate(10),
        ]);
    }

    public function review(Request $request, PublicProfile $profile): RedirectResponse
    {
        $data=$request->validate([
            'decision'=>['required',Rule::in(['approve','reject'])],
            'note'=>['nullable','string','max:500','required_if:decision,reject'],
        ]);
        DB::transaction(function() use ($request,$profile,$data): void {
            $locked=PublicProfile::query()->whereKey($profile->id)->lockForUpdate()
                ->with('user')->firstOrFail();
            abort_unless($locked->status==='pending_review',409);
            abort_if($locked->user_id===$request->user()->id,403);
            abort_unless($locked->user?->role===$locked->kind &&
                $locked->consented_at!==null,409);
            abort_if($locked->kind==='student' &&
                $locked->adult_confirmed_at===null,403);

            $approved=$data['decision']==='approve';
            $locked->forceFill([
                'status'=>$approved ? 'published' : 'rejected',
                'published_at'=>$approved ? now() : null,
                'reviewed_by'=>$request->user()->id,
                'reviewed_at'=>now(),
                'review_note'=>$data['note']??null,
            ])->save();
            ApprovalAudit::record('public_profile',$locked->id,$request->user(),
                $approved?'approved':'rejected','pending_review',
                $approved?'published':'rejected',$data['note']??null);
        });

        return back()->with('status','Public profile review decision recorded.');
    }
}
