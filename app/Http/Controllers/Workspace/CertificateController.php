<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\WorkspaceNotifications;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\IssuedCertificate;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class CertificateController extends Controller
{
    public function templates(): View
    {
        return view('workspace.admin.examination.templates', [
            'templates' => CertificateTemplate::with('type')->withCount('courses')->latest('id')->paginate(15),
        ]);
    }

    public function builder(?CertificateTemplate $template = null): View
    {
        $template ??= new CertificateTemplate();

        return view('workspace.admin.examination.certificate-builder', [
            'template' => $template, 'types' => CertificateType::orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'assigned' => $template->exists ? $template->courses()->pluck('courses.id')->all() : [],
        ]);
    }

    public function save(Request $request, ?CertificateTemplate $template = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'certificate_type_id' => ['required', 'integer', 'exists:certificate_types,id'],
            'heading' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:700'],
            'issuer' => ['required', 'string', 'max:140'],
            'accent' => ['required', 'regex:/^#[0-9a-f]{6}$/iD'],
            'orientation' => ['required', 'in:landscape,portrait'],
            'courses' => ['sometimes', 'array', 'max:300'],
            'courses.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
        ]);

        $saved = DB::transaction(function () use ($template, $data): CertificateTemplate {
            $saved = $template ? CertificateTemplate::lockForUpdate()->findOrFail($template->id) : new CertificateTemplate();
            $saved->fill(collect($data)->except('courses')->all())->save();
            $saved->courses()->sync($data['courses'] ?? []);

            return $saved;
        });

        return redirect()->route('admin.certificate-templates.edit', $saved)->with('status', 'Certificate template saved.');
    }

    public function index(Request $request): View
    {
        $enrollmentId = $request->validate(['enrollment' => ['nullable', 'integer', 'min:1']])['enrollment'] ?? null;
        $enrollments = CourseEnrollment::with('course', 'student')
            ->where('status', 'approved')->whereHas('course', fn ($q) => $q->published()->whereHas('certificateTemplates'))
            ->when($enrollmentId, fn ($q) => $q->whereKey($enrollmentId))
            ->latest('id')->paginate(12, ['*'], 'enrollments_page')->withQueryString();
        $enrollments->getCollection()->load('course.certificateTemplates');

        return view('workspace.admin.examination.issued', [
            'enrollments' => $enrollments,
            'certificates' => IssuedCertificate::with('enrollment')->latest('id')->paginate(12)->withQueryString(),
        ]);
    }

    public function issue(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['template_id' => ['required', 'integer', 'exists:certificate_templates,id']]);

        $certificate = DB::transaction(function () use ($request, $enrollment, $data): IssuedCertificate {
            $template = CertificateTemplate::lockForUpdate()->with('type')->findOrFail($data['template_id']);
            $enrollment = CourseEnrollment::with('student')->lockForUpdate()->findOrFail($enrollment->id);
            $course = Course::published()->findOrFail($enrollment->course_id);
            abort_unless($enrollment->status === 'approved' && $template->courses()->whereKey($course->id)->exists(), 422,
                'An approved enrollment and a template assigned to this course are required.');
            $lessonCount = $course->lessons()->count();
            $completed = $enrollment->lessonProgress()->whereNotNull('completed_at')
                ->whereIn('lesson_id', $course->lessons()->select('course_lessons.id'))->count();
            $exams = CourseExam::where('course_id', $course->id)->where('published', true)->pluck('id');
            $passed = ExamAttempt::where('course_enrollment_id', $enrollment->id)->whereIn('course_exam_id', $exams)
                ->where('passed', true)->distinct()->count('course_exam_id');
            abort_unless($lessonCount > 0 && $lessonCount === $completed && $passed === $exams->count(),
                422, 'Complete every lesson and pass all published exams before issuing this certificate.');

            $issued = IssuedCertificate::firstOrCreate([
                'course_enrollment_id' => $enrollment->id, 'certificate_template_id' => $template->id,
            ], [
                'issued_by' => $request->user()->id, 'code' => (string) Str::uuid(),
                'snapshot' => $template->only('heading', 'body', 'issuer', 'accent', 'orientation') + [
                    'type' => $template->type->name, 'learner' => $enrollment->student->name, 'course' => $course->title,
                ],
            ]);
            if ($issued->wasRecentlyCreated) {
                WorkspaceNotifications::send($enrollment->student_id, 'Course certificate issued', 'certificates.show', ['certificate' => $issued->code]);
            }

            return $issued;
        });

        return redirect()->route('certificates.show', $certificate->code);
    }

    public function revoke(IssuedCertificate $certificate): RedirectResponse
    {
        IssuedCertificate::whereKey($certificate->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return back()->with('status', 'Certificate revoked.');
    }

    public function show(Request $request, IssuedCertificate $certificate): View
    {
        $viewer = $request->user();
        if (!$viewer->isAdministrator()) {
            abort_if($certificate->revoked_at !== null, 404);
            LearningAccess::course($viewer, $certificate->enrollment);
        }

        return view('learning.certificate', compact('certificate'));
    }
}
