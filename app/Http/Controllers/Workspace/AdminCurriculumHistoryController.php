<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReviewSnapshot;
use App\Models\CurriculumLessonRevision;
use App\Support\CourseReadiness;
use Illuminate\View\View;

class AdminCurriculumHistoryController extends Controller
{
    public function index(Course $course): View
    {
        return view('workspace.admin.curriculum-history',[
            'role'=>'admin','course'=>$course,
            'checklist'=>CourseReadiness::checklist($course),
            'history'=>CurriculumLessonRevision::where('course_id',$course->id)
                ->with('editor')->latest('id')->paginate(12),
            'submissions'=>CourseReviewSnapshot::where('course_id',$course->id)
                ->latest('id')->limit(10)->get(),
        ]);
    }
}
