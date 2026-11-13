<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseInstructorAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InstructorInsightsController extends Controller
{
    public function index(Request $request): View
    {
        $id=$request->user()->id;
        $ids=CourseInstructorAssignment::query()->where('instructor_id',$id)
            ->where('status','active')
            ->whereHas('course',fn($q)=>$q->published())
            ->pluck('course_id');

        $courses=Course::published()->whereIn('id',$ids)->with('category')
            ->withCount(['enrollments as approved_enrollments_count'=>
                fn($q)=>$q->where('status','approved')])
            ->orderBy('title')->paginate(10);
        $pageIds=$courses->getCollection()->modelKeys();

        $completed=DB::table('lesson_progress')
            ->join('course_enrollments','course_enrollments.id','=','lesson_progress.enrollment_id')
            ->whereIn('course_enrollments.course_id',$pageIds)
            ->where('course_enrollments.status','approved')
            ->whereNotNull('lesson_progress.completed_at')
            ->groupBy('course_enrollments.course_id')
            ->selectRaw('course_enrollments.course_id, count(*) as total')
            ->pluck('total','course_id');

        $submissions=DB::table('homework_submissions')
            ->join('course_homework_assignments','course_homework_assignments.id','=','homework_submissions.assignment_id')
            ->where('course_homework_assignments.status','published')
            ->whereIn('course_homework_assignments.course_id',$pageIds)
            ->groupBy('course_homework_assignments.course_id','homework_submissions.status')
            ->selectRaw('course_homework_assignments.course_id, homework_submissions.status, count(*) as total')->get();

        $byCourse=[];
        foreach($submissions as $record){
            $byCourse[$record->course_id][$record->status]=(int)$record->total;
        }

        return view('workspace.instructor.insights',[
            'role'=>'instructor','courses'=>$courses,
            'completed'=>$completed,'submissionsByCourse'=>$byCourse,
        ]);
    }
}
