<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseInstructorAssignment;
use App\Models\LessonProgress;
use App\Models\PublicProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicPeopleController extends Controller
{
    public function instructors(Request $request): View
    {
        return $this->directory($request, 'instructor');
    }

    public function students(Request $request): View
    {
        return $this->directory($request, 'student');
    }

    private function directory(Request $request, string $kind): View
    {
        $data = $request->validate(['q'=>['nullable','string','max:80']]);
        $query = trim($data['q'] ?? '');
        $profiles = PublicProfile::visible()->where('kind',$kind)
            ->when($query !== '', fn($p) => $p->where(fn($q) =>
                $q->where('display_name','like','%'.$query.'%')
                  ->orWhere('headline','like','%'.$query.'%')
                  ->orWhere('subjects','like','%'.$query.'%')))
            ->orderBy('display_name')->paginate(10)->withQueryString();

        return view('people.index', compact('profiles','kind','query'));
    }

    public function instructor(PublicProfile $profile): View
    {
        return $this->detail($profile,'instructor');
    }

    public function student(PublicProfile $profile): View
    {
        return $this->detail($profile,'student');
    }

    private function detail(PublicProfile $profile, string $kind): View
    {
        abort_unless(PublicProfile::visible()->where('kind',$kind)
            ->whereKey($profile->id)->exists(),404);

        $courses = collect();
        $learning = null;

        if ($kind === 'instructor') {
            $courses = Course::discoverable()->with('category:id,slug,name,code')
                ->withCount('lessons')
                ->whereHas('instructorAssignments', fn($q) =>
                    $q->where('instructor_id',$profile->user_id)->where('status','active'))
                ->orderBy('title')->limit(12)->get();
        } elseif ($profile->show_learning_summary) {
            $enrollments = CourseEnrollment::query()
                ->where('student_id',$profile->user_id)->where('status','approved')
                ->whereHas('course',fn($q)=>$q->published())->pluck('id');
            $learning = [
                'courses'=>$enrollments->count(),
                'completed_lessons'=>LessonProgress::query()->whereIn('enrollment_id',$enrollments)
                    ->whereNotNull('completed_at')->count(),
            ];
        }

        return view('people.show', compact('profile','kind','courses','learning'));
    }
}
