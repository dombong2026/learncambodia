<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\InstructorFeedbackTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstructorFeedbackTemplateController extends Controller
{
    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required','string','max:120'],
            'body' => ['required','string','min:5','max:3000'],
        ]);
    }

    private function owned(Request $request, InstructorFeedbackTemplate $template): void
    {
        abort_unless($template->instructor_id === $request->user()->id, 404);
    }

    public function index(Request $request): View
    {
        return view('workspace.instructor.feedback-templates', [
            'role' => 'instructor',
            'templates' => InstructorFeedbackTemplate::query()
                ->where('instructor_id',$request->user()->id)
                ->orderBy('title')->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        abort_if(InstructorFeedbackTemplate::query()
            ->where('instructor_id',$request->user()->id)->count() >= 30, 422);
        InstructorFeedbackTemplate::create($data + ['instructor_id'=>$request->user()->id]);

        return back()->with('status','Private feedback template created.');
    }

    public function update(Request $request, InstructorFeedbackTemplate $template): RedirectResponse
    {
        $this->owned($request,$template);
        $template->update($this->data($request));

        return back()->with('status','Feedback template updated.');
    }

    public function destroy(Request $request, InstructorFeedbackTemplate $template): RedirectResponse
    {
        $this->owned($request,$template);
        $template->delete();

        return back()->with('status','Private feedback template deleted.');
    }
}
