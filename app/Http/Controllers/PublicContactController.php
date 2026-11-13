<?php
namespace App\Http\Controllers;
use App\Models\ContactInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class PublicContactController extends Controller {
public function store(Request $request): RedirectResponse {
$data=$request->validate([
'name'=>['required','string','min:2','max:120'],
'email'=>['required','email','max:255'],
'topic'=>['required',Rule::in(['courses','enrollment','technical','general'])],
'subject'=>['required','string','min:3','max:160'],
'message'=>['required','string','min:10','max:4000'],
'consent'=>['required','accepted'],
'company_website'=>['nullable','string','max:200'],
]);
if (!filled($data['company_website']??null)) {
ContactInquiry::create([
'name'=>$data['name'],'email'=>$data['email'],'topic'=>$data['topic'],
'subject'=>$data['subject'],'message'=>$data['message'],'consented_at'=>now(),
]);}
return redirect()->route('information.show','contact')
->with('contact_status',__('longpage.contact_confirmation'));
}
}