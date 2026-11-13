<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PublicProfile;
use App\Models\User;
use App\Support\ApprovalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PublicProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('workspace.public-profile', [
            'role'=>$request->user()->role,
            'profile'=>PublicProfile::query()->where('user_id',$request->user()->id)->first(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $kind=$request->user()->role;
        abort_unless(in_array($kind,[User::ROLE_STUDENT,User::ROLE_INSTRUCTOR],true),403);
        $data=$request->validate([
            'display_name'=>['required','string','max:100'],
            'headline'=>['required','string','max:160'],
            'subjects'=>['nullable','string','max:160'],
            'city'=>['nullable','string','max:80'],
            'bio'=>['nullable','string','max:1500'],
            'show_learning_summary'=>['sometimes','boolean'],
            'public_consent'=>['required','accepted'],
            'adult_confirmation'=>[$kind===User::ROLE_STUDENT ? 'accepted' : 'nullable'],
        ]);

        DB::transaction(function () use ($request,$kind,$data): void {
            // Lock the account row to serialize first publication and prevent
            // races between concurrent profile creation/save operations.
            $user=User::query()->whereKey($request->user()->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($user->role===$kind,403);
            $profile=PublicProfile::query()->where('user_id',$user->id)
                ->lockForUpdate()->first();
            abort_if($profile && !in_array($profile->status,['draft','rejected','withdrawn'],true),
                409,'Submitted or published profiles must be withdrawn before editing.');

            $content=[
                'display_name'=>$data['display_name'],'headline'=>$data['headline'],
                'subjects'=>$data['subjects']??null,'city'=>$data['city']??null,
                'bio'=>$data['bio']??null,
                'show_learning_summary'=>$kind===User::ROLE_STUDENT &&
                    (bool)($data['show_learning_summary']??false),
            ];

            if (!$profile) {
                $profile=PublicProfile::create($content+['user_id'=>$user->id,'kind'=>$kind]);
            } else {
                $profile->fill($content);
            }
            $profile->forceFill([
                'kind'=>$kind,'status'=>'draft',
                'consented_at'=>now(),
                'adult_confirmed_at'=>$kind===User::ROLE_STUDENT ? now() : null,
                'reviewed_by'=>null,'reviewed_at'=>null,
                'published_at'=>null,'review_note'=>null,
            ])->save();
        });

        return back()->with('status','Your public profile draft was saved privately.');
    }

    public function submit(Request $request): RedirectResponse
    {
        DB::transaction(function() use ($request): void {
            $profile=PublicProfile::query()->where('user_id',$request->user()->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->role===$profile->kind &&
                $profile->status==='draft' && $profile->consented_at!==null,409);
            abort_if($profile->kind==='student' && $profile->adult_confirmed_at===null,403);
            $profile->forceFill(['status'=>'pending_review'])->save();
            ApprovalAudit::record('public_profile',$profile->id,$request->user(),
                'submitted','draft','pending_review');
        });
        return back()->with('status','Your profile was submitted for independent privacy review.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        DB::transaction(function() use ($request): void {
            $profile=PublicProfile::query()->where('user_id',$request->user()->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($profile->kind===$request->user()->role,403);
            $before=$profile->status;
            $profile->forceFill([
                'status'=>'withdrawn','published_at'=>null,
                'consented_at'=>null,'adult_confirmed_at'=>null,
                'show_learning_summary'=>false,
            ])->save();
            ApprovalAudit::record('public_profile',$profile->id,$request->user(),
                'withdrawn',$before,'withdrawn');
        });
        return back()->with('status','Your profile has been removed from public visibility.');
    }
}
