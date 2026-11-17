<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Models\ExamType;
use App\Support\EditorHtml;
use App\Support\AssessmentQuestions;
use App\Support\CoursePricing;
use App\Support\FeaturedMedia;
use App\Support\LearningTable;
use App\Support\WorkspaceNotifications;
use App\Models\AssessmentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminExaminationController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->routeIs('admin.quizzes-manager.*') ? 'quiz' : 'exam';

        return view('workspace.admin.examination.index', [
            'kind' => $kind,
            'exams' => LearningTable::paginate(
                LearningTable::assessments(
                    CourseExam::where('kind', $kind)->with('course.category', 'type'), LearningTable::search($request)
                )->latest('id'),
                $request
            ),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->editor(new CourseExam(['kind' => $request->routeIs('admin.quizzes-manager.*') ? 'quiz' : 'exam']));
    }

    public function edit(Request $request, CourseExam $exam): View
    {
        abort_unless($exam->kind === ($request->routeIs('admin.quizzes-manager.*') ? 'quiz' : 'exam'), 404);

        return $this->editor($exam);
    }

    private function editor(CourseExam $exam): View
    {
        $attempts = $exam->exists
            ? ExamAttempt::where('course_exam_id', $exam->id)->whereNotNull('submitted_at')->with('enrollment.student', 'student')
                ->latest('id')->paginate(20)
            : null;

        return view('workspace.admin.examination.builder', [
            'exam' => $exam, 'accessRequests' => $exam->exists
                ? AssessmentAccess::where('course_exam_id', $exam->id)->with('student')->latest('id')->paginate(15, ['*'], 'access_page')
                : collect(), 'attempts' => $attempts, 'courses' => Course::orderBy('title')->get(['id', 'title']),
            'types' => ExamType::where('kind', $exam->kind)->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request, ?CourseExam $exam = null): RedirectResponse
    {
        $kind = $request->routeIs('admin.quizzes-manager.*') ? 'quiz' : 'exam';
        abort_if($exam && $exam->exists && $exam->kind !== $kind, 404);
        if ($request->has('questions_payload')) {
            $payload = $request->validate(['questions_payload' => ['required', 'string', 'json', 'max:4000000']])['questions_payload'];
            $request->merge(['questions' => json_decode($payload, true, flags: JSON_THROW_ON_ERROR)]);
        }
        $data = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'exam_type_id' => ['required', 'integer', Rule::exists('exam_types', 'id')->where('kind', $kind)],
            'title' => ['required', 'string', 'max:160'],
            'instructions_html' => ['nullable', 'string', 'max:120000'],
            'public_requirement' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content_language' => ['sometimes', 'nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:15000'],
            'time_limit' => ['required', 'integer', $kind === 'quiz' ? 'between:1,30' : 'between:1,240'],
            'passing_percent' => ['required', 'integer', 'between:1,100'],
            'attempt_limit' => ['required', 'integer', 'between:1,10'],
            'shuffle' => ['sometimes', 'boolean'], 'gamified' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'], 'live_feedback' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'list', 'min:1', $kind === 'quiz' ? 'max:50' : 'max:200'],
            'questions.*' => ['array:format,prompt,a,b,c,d,correct,points,passage,media_id,feedback'],
            'questions.*.format' => ['sometimes', Rule::in(array_keys(AssessmentQuestions::FORMATS))],
            'questions.*.prompt' => ['required', 'string', 'max:600'],
            'questions.*.a' => ['nullable', 'string', 'max:300'],
            'questions.*.b' => ['nullable', 'string', 'max:300'],
            'questions.*.c' => ['nullable', 'string', 'max:300'],
            'questions.*.d' => ['nullable', 'string', 'max:300'],
            'questions.*.correct' => ['nullable', 'string', 'max:300'],
            'questions.*.passage' => ['nullable', 'string', 'max:12000'],
            'questions.*.media_id' => ['nullable', 'integer', 'min:1'],
            'questions.*.feedback' => ['nullable', 'string', 'max:800'],
            'questions.*.points' => ['required', 'integer', 'between:1,100'],
        ]);
        $data['questions'] = AssessmentQuestions::validate($data['questions']);
        if (count(array_filter($data['questions'], fn ($question) => $question['format'] === 'speaking')) > 10) {
            throw ValidationException::withMessages(['questions' => 'An assessment can have up to 10 speaking recordings.']);
        }
        if ($kind === 'quiz') {
            $data += FeaturedMedia::validate($request);
        }
        $data['kind'] = $kind;
        $data['course_id'] = $kind === 'quiz' ? null : ($data['course_id'] ?? null);
        $data['pricing'] = $data['course_id'] === null && $kind === 'exam'
            ? (CoursePricing::validate($request) + ['pricing' => $exam?->pricing])['pricing']
            : null;
        $data['instructions_html'] = EditorHtml::clean($data['instructions_html']
            ?? nl2br(e($data['instructions'] ?? '')), lesson: true);
        unset($data['instructions']);
        $data += ['shuffle' => false, 'gamified' => false, 'published' => false, 'live_feedback' => false];

        $saved = DB::transaction(function () use ($request, $exam, $data): CourseExam {
            $record = $exam ? CourseExam::lockForUpdate()->findOrFail($exam->id) : new CourseExam();
            if ($record->exists && (int) $record->course_id !== (int) $data['course_id']) {
                abort_if(ExamAttempt::where('course_exam_id', $record->id)->exists() || AssessmentAccess::where('course_exam_id', $record->id)->exists(), 409, 'An exam with attempts stays linked to its original course.');
            }
            $ids = AssessmentQuestions::mediaIds($data);
            $attached = $record->exists ? $record->media()->allRelatedIds() : [];
            $media = EditorMedia::whereIn('id', $ids)->where('context', 'exam')
                ->where(fn ($query) => $query->where('uploaded_by', $request->user()->id)->orWhereIn('id', $attached))
                ->lockForUpdate()->get();
            if ($media->count() !== count($ids)) {
                throw ValidationException::withMessages(['instructions' => 'One or more resources are unavailable for this exam.']);
            }
            foreach ($data['questions'] as $index => $question) {
                if (($question['format'] ?? null) === 'listening'
                    && !str_starts_with($media->firstWhere('id', (int) $question['media_id'])?->mime ?? '', 'audio/')) {
                    throw ValidationException::withMessages(["questions.$index.media_id" => 'Select an audio recording for listening.']);
                }
            }
            $record->fill($data);
            if ($record->published) {
                $record->deactivated_at = null;
            }
            $record->save();
            $record->media()->syncWithoutDetaching($ids);

            return $record;
        });

        return redirect()->route($kind === 'quiz' ? 'admin.quizzes-manager.edit' : 'admin.exams.edit', $saved)->with('status', 'Assessment saved.');
    }

    public function grade(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->submitted_at, 404);
        $data = $request->validate([
            'grades' => ['required', 'array', 'max:50'], 'grades.*' => ['array:points,feedback'],
            'grades.*.points' => ['required', 'integer', 'min:0'], 'grades.*.feedback' => ['nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($attempt, $data): void {
            $locked = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $grades = $locked->grading ?? [];
            foreach ($data['grades'] as $index => $update) {
                $question = $locked->snapshot['questions'][$index] ?? null;
                if (!$question || !AssessmentQuestions::manual($question) || $update['points'] > $question['points']) {
                    throw ValidationException::withMessages(["grades.$index.points" => 'Enter a score within the points available for this response.']);
                }
                $grades[$index] = $update;
            }
            $locked->update(AssessmentQuestions::result($locked->snapshot, $locked->answers ?? [], $grades) + ['grading' => $grades]);
            $enrollment = $locked->enrollment;
            WorkspaceNotifications::send(
                $enrollment?->student_id ?? $locked->student_id, 'New assessment feedback',
                $enrollment ? 'account.exam-attempts.show' : 'account.assessment-attempts.show',
                $enrollment ? ['enrollment' => $enrollment->id, 'attempt' => $locked->id] : ['attempt' => $locked->id],
            );
        });

        return back()->with('status', 'Grades saved.');
    }

    public function types(Request $request, string $kind): View
    {
        $query = $kind === 'certificate-types' ? CertificateType::query() : ExamType::where('kind', $this->assessmentKind($kind));

        return view('workspace.admin.examination.types', [
            'kind' => $kind, 'types' => $query->orderBy('name')->paginate(15),
        ]);
    }

    public function updateType(Request $request, int $id, string $kind): RedirectResponse
    {
        return $this->saveType($request, $kind, $id);
    }

    public function saveType(Request $request, string $kind, ?int $id = null): RedirectResponse
    {
        if ($kind === 'certificate-types') {
            $record = $id ? CertificateType::findOrFail($id) : new CertificateType();
            $unique = Rule::unique('certificate_types', 'name');
        } else {
            $assessmentKind = $this->assessmentKind($kind);
            $record = $id ? ExamType::where('kind', $assessmentKind)->findOrFail($id) : new ExamType(['kind' => $assessmentKind]);
            $unique = Rule::unique('exam_types', 'name')->where('kind', $assessmentKind);
        }

        $record->fill($request->validate([
            'name' => ['required', 'string', 'max:100', $unique->ignore($record->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]))->save();

        return back()->with('status', 'Type saved.');
    }

    private function assessmentKind(string $kind): string
    {
        return $kind === 'quiz-types' ? 'quiz' : 'exam';
    }
}
