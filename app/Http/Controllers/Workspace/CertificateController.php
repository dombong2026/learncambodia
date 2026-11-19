<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Support\WorkspaceNotifications;
use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\ExamAttempt;
use App\Models\IssuedCertificate;
use App\Models\PracticeAttempt;
use App\Support\CertificateAwards;
use App\Support\CertificateDesign;
use App\Support\LearningAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $fields = [];
        foreach (array_keys(CertificateDesign::FIELDS) as $field) {
            foreach (CertificateDesign::limits($field) as $axis => [$min, $max]) {
                $fields["design.fields.$field.$axis"] = ["required_with:design.fields.$field", 'numeric', "between:$min,$max"];
            }
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'certificate_type_id' => ['required', 'integer', 'exists:certificate_types,id'],
            'heading' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:700'],
            'issuer' => ['required', 'string', 'max:140'],
            'accent' => ['required', 'regex:/^#[0-9a-f]{6}$/iD'],
            'orientation' => ['required', 'in:landscape,portrait'],
            'design' => ['sometimes', 'array:font,ink,frame,fields'],
            'design.font' => ['sometimes', 'in:serif,sans'],
            'design.ink' => ['sometimes', 'regex:/^#[0-9a-f]{6}$/iD'],
            'design.frame' => ['sometimes', 'boolean'],
            'design.fields' => ['sometimes', 'array:'.implode(',', array_keys(CertificateDesign::FIELDS))],
            'design.fields.*' => ['array:x,y,size'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'],
            'remove_background' => ['sometimes', 'boolean'],
            'courses' => ['sometimes', 'array', 'max:300'],
            'courses.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
        ] + $fields);

        $type = CertificateType::findOrFail($data['certificate_type_id']);
        if (in_array($type->kind, ['exam', 'quiz'], true) && !empty($data['courses'])) {
            throw ValidationException::withMessages(['courses' => 'Assign Exam and Quiz templates from the assessment builder.']);
        }

        $background = $request->file('background')?->store('certificate-backgrounds', 'local');
        abort_if($background === false, 503, 'The certificate background could not be stored.');

        try {
            $saved = DB::transaction(function () use ($template, $data, $background, $type): CertificateTemplate {
                $saved = $template ? CertificateTemplate::lockForUpdate()->findOrFail($template->id) : new CertificateTemplate();
                if ($saved->default_kind && $saved->default_kind !== $type->kind) {
                    throw ValidationException::withMessages(['certificate_type_id' => 'A default template keeps its certificate type.']);
                }
                $design = CertificateDesign::options($data['design'] ?? $saved->design);
                $design['background'] = $background ?? (empty($data['remove_background'])
                    ? ($saved->design['background'] ?? null) : null);
                $saved->fill(collect($data)->only('name', 'certificate_type_id', 'heading', 'body', 'issuer', 'accent', 'orientation')->all());
                $saved->design = $design;
                $saved->save();
                $saved->courses()->sync($data['courses'] ?? []);

                return $saved;
            });
        } catch (\Throwable $exception) {
            if ($background !== null) {
                Storage::disk('local')->delete($background);
            }
            throw $exception;
        }

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
            $enrollment = CourseEnrollment::with('student')->lockForUpdate()->findOrFail($enrollment->id);
            $template = CertificateTemplate::lockForUpdate()->with('type')->findOrFail($data['template_id']);
            $course = Course::published()->findOrFail($enrollment->course_id);
            abort_unless(in_array($template->type->kind, [null, 'course'], true)
                && $template->courses()->whereKey($course->id)->exists(), 422,
                'A course certificate template assigned to this course is required.');
            abort_unless(CertificateAwards::courseEligible($course, $enrollment),
                422, 'Complete every lesson and pass all published exams before issuing this certificate.');

            $issued = IssuedCertificate::firstOrCreate([
                'course_enrollment_id' => $enrollment->id, 'certificate_template_id' => $template->id,
            ], [
                'issued_by' => $request->user()->id, 'code' => (string) Str::uuid(),
                'snapshot' => $template->only('heading', 'body', 'issuer', 'accent', 'orientation', 'design') + [
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

    public function mine(Request $request): View
    {
        $studentId = $request->user()->id;
        $certificates = IssuedCertificate::whereNull('revoked_at')
            ->where(fn ($query) => $query->where('student_id', $studentId)
                ->orWhereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $studentId)
                    ->where('status', 'approved')->whereHas('course', fn ($course) => $course->published())))
            ->latest('id')->paginate(15);

        return view('workspace.student.certificates', compact('certificates'));
    }

    public function claimCourse(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        $certificate = DB::transaction(function () use ($request, $enrollment): ?IssuedCertificate {
            $locked = CourseEnrollment::lockForUpdate()->findOrFail($enrollment->id);
            $course = LearningAccess::course($request->user(), $locked);

            return CertificateAwards::course($course, $locked);
        });

        return $this->showAward($certificate);
    }

    public function claimAssessment(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $certificate = DB::transaction(function () use ($request, $attempt): ?IssuedCertificate {
            $enrollment = $attempt->course_enrollment_id
                ? CourseEnrollment::lockForUpdate()->findOrFail($attempt->course_enrollment_id) : null;
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            if ($enrollment) {
                LearningAccess::course($request->user(), $enrollment);
            } else {
                abort_unless($locked->student_id === $request->user()->id, 404);
            }

            return CertificateAwards::assessment($locked);
        });

        return $this->showAward($certificate);
    }

    public function claimPractice(Request $request, PracticeAttempt $practiceAttempt): RedirectResponse
    {
        $certificate = DB::transaction(function () use ($request, $practiceAttempt): ?IssuedCertificate {
            $enrollment = CourseEnrollment::lockForUpdate()->findOrFail($practiceAttempt->enrollment_id);
            LearningAccess::course($request->user(), $enrollment);

            return CertificateAwards::practice($practiceAttempt);
        });

        return $this->showAward($certificate);
    }

    private function showAward(?IssuedCertificate $certificate): RedirectResponse
    {
        abort_unless($certificate, 422, __('certificate_verification.not_eligible'));
        abort_if($certificate->revoked_at !== null, 404);

        return to_route('certificates.show', [$certificate->code, 'lang' => app()->getLocale()]);
    }

    public function revoke(IssuedCertificate $certificate): RedirectResponse
    {
        IssuedCertificate::whereKey($certificate->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return back()->with('status', 'Certificate revoked.');
    }

    public function show(Request $request, IssuedCertificate $certificate): View
    {
        $this->authorizeCertificate($request, $certificate);

        return view('learning.certificate', compact('certificate'));
    }

    public function templateBackground(CertificateTemplate $template): StreamedResponse
    {
        return $this->background($template->design['background'] ?? null);
    }

    public function issuedBackground(Request $request, IssuedCertificate $certificate): StreamedResponse
    {
        $this->authorizeCertificate($request, $certificate);

        return $this->background($certificate->snapshot['design']['background'] ?? null);
    }

    private function authorizeCertificate(Request $request, IssuedCertificate $certificate): void
    {
        $viewer = $request->user();
        if (!$viewer->isAdministrator()) {
            abort_if($certificate->revoked_at !== null, 404);
            if ($certificate->student_id !== null) {
                abort_unless($certificate->student_id === $viewer->id, 404);

                return;
            }
            LearningAccess::course($viewer, $certificate->enrollment);
        }
    }

    private function background(?string $path): StreamedResponse
    {
        abort_unless($path && preg_match('~^certificate-backgrounds/[a-z0-9]{40}\.(jpe?g|png|webp)$~iD', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
