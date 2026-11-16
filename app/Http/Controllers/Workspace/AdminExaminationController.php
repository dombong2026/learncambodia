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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminExaminationController extends Controller
{
    public function index(): View
    {
        return view('workspace.admin.examination.index', [
            'exams' => CourseExam::with('course', 'type')->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return $this->editor(new CourseExam());
    }

    public function edit(CourseExam $exam): View
    {
        return $this->editor($exam);
    }

    private function editor(CourseExam $exam): View
    {
        $attempts = $exam->exists
            ? ExamAttempt::where('course_exam_id', $exam->id)->whereNotNull('submitted_at')->with('enrollment.student')
                ->latest('id')->paginate(20)
            : null;

        return view('workspace.admin.examination.builder', [
            'exam' => $exam, 'attempts' => $attempts, 'courses' => Course::orderBy('title')->get(['id', 'title']),
            'types' => ExamType::orderBy('name')->get(),
        ]);
    }

    public function save(Request $request, ?CourseExam $exam = null): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'exam_type_id' => ['required', 'integer', 'exists:exam_types,id'],
            'title' => ['required', 'string', 'max:160'],
            'instructions_html' => ['nullable', 'string', 'max:120000'],
            'instructions' => ['nullable', 'string', 'max:15000'],
            'time_limit' => ['required', 'integer', 'between:1,240'],
            'passing_percent' => ['required', 'integer', 'between:1,100'],
            'attempt_limit' => ['required', 'integer', 'between:1,10'],
            'shuffle' => ['sometimes', 'boolean'], 'gamified' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'questions.*' => ['array:format,prompt,a,b,c,d,correct,points'],
            'questions.*.format' => ['sometimes', Rule::in(['choice', 'boolean', 'short'])],
            'questions.*.prompt' => ['required', 'string', 'max:600'],
            'questions.*.a' => ['nullable', 'string', 'max:300'],
            'questions.*.b' => ['nullable', 'string', 'max:300'],
            'questions.*.c' => ['nullable', 'string', 'max:300'],
            'questions.*.d' => ['nullable', 'string', 'max:300'],
            'questions.*.correct' => ['required', 'string', 'max:300'],
            'questions.*.points' => ['required', 'integer', 'between:1,100'],
        ]);
        foreach ($data['questions'] as $index => $question) {
            $format = $question['format'] ?? 'choice';
            $correct = $question['correct'];
            if ($format === 'boolean' && !in_array($correct, ['true', 'false'], true)) {
                throw ValidationException::withMessages(["questions.$index.correct" => 'Enter true or false as the correct answer.']);
            }
            if ($format === 'choice' && (blank($question['a'] ?? null) || blank($question['b'] ?? null)
                || !in_array($correct, ['a', 'b', 'c', 'd'], true) || blank($question[$correct] ?? null))) {
                throw ValidationException::withMessages(["questions.$index.correct" => 'Provide at least options A and B and select a populated answer.']);
            }
            $data['questions'][$index]['format'] = $format;
        }
        $data['instructions_html'] = EditorHtml::clean($data['instructions_html']
            ?? nl2br(e($data['instructions'] ?? '')), lesson: true);
        unset($data['instructions']);
        $data += ['shuffle' => false, 'gamified' => false, 'published' => false];

        $saved = DB::transaction(function () use ($request, $exam, $data): CourseExam {
            $record = $exam ? CourseExam::lockForUpdate()->findOrFail($exam->id) : new CourseExam();
            if ($record->exists && (int) $record->course_id !== (int) $data['course_id']) {
                abort_if(ExamAttempt::where('course_exam_id', $record->id)->exists(), 409, 'An exam with attempts stays linked to its original course.');
            }
            $ids = EditorHtml::sourceIds($data['instructions_html'], lesson: true);
            $attached = $record->exists ? $record->media()->allRelatedIds() : [];
            $media = EditorMedia::whereIn('id', $ids)->where('context', 'exam')
                ->where(fn ($query) => $query->where('uploaded_by', $request->user()->id)->orWhereIn('id', $attached))
                ->lockForUpdate()->get();
            if ($media->count() !== count($ids)) {
                throw ValidationException::withMessages(['instructions' => 'One or more resources are unavailable for this exam.']);
            }
            $record->fill($data)->save();
            $record->media()->syncWithoutDetaching($ids);

            return $record;
        });

        return redirect()->route('admin.exams.edit', $saved)->with('status', 'Exam saved.');
    }

    public function types(Request $request, string $kind): View
    {
        $model = $kind === 'exam-types' ? ExamType::class : CertificateType::class;

        return view('workspace.admin.examination.types', [
            'kind' => $kind, 'types' => $model::orderBy('name')->paginate(15),
        ]);
    }

    public function updateType(Request $request, int $id, string $kind): RedirectResponse
    {
        return $this->saveType($request, $kind, $id);
    }

    public function saveType(Request $request, string $kind, ?int $id = null): RedirectResponse
    {
        $model = $kind === 'exam-types' ? ExamType::class : CertificateType::class;
        $record = $id ? $model::findOrFail($id) : new $model();
        $record->fill($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($record->getTable())->ignore($record->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]))->save();

        return back()->with('status', 'Type saved.');
    }
}
