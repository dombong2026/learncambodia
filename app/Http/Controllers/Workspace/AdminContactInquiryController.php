<?php
namespace App\Http\Controllers\Workspace;
use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
final class AdminContactInquiryController extends Controller {
public function index(Request $request): View {
$data=$request->validate(['status'=>['nullable',Rule::in(['new','reviewed','closed'])]]);
$status=$data['status']??null;
$inquiries=ContactInquiry::query()->when($status,fn($q)=>$q->where('status',$status))
->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
->orderByDesc('id')->paginate(10)->withQueryString();
return view('workspace.admin.contact-inquiries',compact('inquiries','status')+['role'=>'admin']);
}
public function update(Request $request, ContactInquiry $inquiry): RedirectResponse {
$data=$request->validate([
'status'=>['required',Rule::in(['reviewed','closed'])],
'review_note'=>['nullable','string','max:500'],
]);
$inquiry->forceFill([
'status'=>$data['status'],'reviewed_by'=>$request->user()->id,
'reviewed_at'=>now(),'review_note'=>$data['review_note']??null,
])->save();
return back()->with('status','Private inquiry status updated.');
}
}