<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\McpActionApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class McpBrowserApprovalController extends Controller
{
    private function approval(Request $request, McpActionApproval $approval): McpActionApproval
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        abort_unless((int)$approval->user_id === (int)$request->user()->id, 403);
        abort_unless($approval->expires_at->isFuture(), 410);
        abort_unless($approval->status === 'pending', 409);

        return $approval;
    }

    public function show(Request $request, McpActionApproval $approval): View
    {
        return view('workspace.admin.mcp-approval', [
            'role'=>'admin',
            'approval'=>$this->approval($request, $approval),
        ]);
    }

    public function decide(Request $request, McpActionApproval $approval): RedirectResponse
    {
        $this->approval($request,$approval);
        $data=$request->validate([
            'decision'=>['required',Rule::in(['approve','deny'])],
            'confirmation'=>['nullable','string','max:20'],
        ]);
        if($data['decision']==='approve'){
            $request->validate(['confirmation'=>['required',Rule::in(['CONFIRM'])]]);
        }
        // This is a browser-only, CSRF-protected, password-confirmed decision.
        // It never executes the operation; the MCP endpoint must recheck the
        // exact token/resource/payload and consume approval atomically.
        $approval->update([
            'status'=>$data['decision']==='approve'?'approved':'denied',
            'approved_at'=>$data['decision']==='approve'?now():null,
        ]);

        return redirect()->route('admin.system-settings.mcp')
            ->with('status',$data['decision']==='approve'
                ? 'Action authorized once. The MCP session must perform the matching action within ten minutes.'
                : 'MCP action denied.');
    }
}
