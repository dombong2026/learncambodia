<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\User;
use App\Models\EditorMedia;
use App\Models\ExamAttempt;
use App\Support\AssessmentQuestions;
use App\Models\PolicyRevision;
use App\Support\FeaturedMedia;
use App\Support\ManagedFiles;
use Illuminate\Database\ForeignKeyConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class EditorMediaController extends Controller
{
    private const DOCUMENT_MIMES = [
        'application/pdf', 'application/msword', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'kind' => ['nullable', Rule::in(['image', 'video'])],
            'columns' => ['nullable', 'integer', 'between:1,4'],
        ]);
        $columns = (int) ($filters['columns'] ?? 4);
        $search = $filters['q'] ?? '';
        $kind = $filters['kind'] ?? null;
        $items = EditorMedia::query()->where('context', 'library')
            ->when($kind, fn ($query) => $query->where('mime', 'like', $kind.'/%'))
            ->when($search !== '', fn ($query) => $query->where('original_name', 'like', '%'.$search.'%'))
            ->latest('id')->paginate($request->expectsJson() ? 12 : $columns * 4)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'items' => $items->getCollection()->map($this->describe(...)),
                'page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ]);
        }

        if ($items->currentPage() > $items->lastPage()) {
            return redirect()->to($items->url($items->lastPage()));
        }

        return view('workspace.admin.media-library', compact('items', 'search', 'kind', 'columns'));
    }

    public function uploadToLibrary(Request $request): JsonResponse|RedirectResponse
    {
        $request->merge(['context' => 'library']);
        $response = $this->storeUpload($request, true);

        return $request->expectsJson() ? $response : back()->with('status', 'Media uploaded.');
    }

    public function updateLibrary(Request $request, EditorMedia $media): RedirectResponse
    {
        abort_unless($media->context === 'library', 404);
        $allowedMimes = array_filter(FeaturedMedia::MIMES,
            fn (string $mime) => explode('/', $mime)[0] === explode('/', $media->mime)[0]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'replacement' => ['nullable', 'file', 'max:51200', 'mimetypes:'.implode(',', $allowedMimes)],
        ]);

        $replacement = $request->file('replacement');
        $disk = ManagedFiles::disk();
        $path = $replacement ? ManagedFiles::store($replacement, 'editor-media') : null;
        abort_if($replacement && !$path, 500, 'The replacement file could not be saved.');

        try {
            $previousPath = DB::transaction(function () use ($media, $data, $replacement, $path): ?string {
                $current = EditorMedia::query()->lockForUpdate()->findOrFail($media->id);
                $previousPath = $replacement ? $current->path : null;
                $current->original_name = $data['title'];

                if ($replacement) {
                    $current->path = $path;
                    $current->mime = $replacement->getMimeType();
                    $current->size = $replacement->getSize();
                }

                $current->save();

                return $previousPath;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                $disk->delete($path);
            }

            throw $exception;
        }

        if ($previousPath) {
            $disk->delete($previousPath);
        }

        return back()->with('status', 'Media updated.');
    }

    public function destroyLibrary(EditorMedia $media): RedirectResponse
    {
        abort_unless($media->context === 'library', 404);

        try {
            $path = DB::transaction(function () use ($media): string {
                $current = EditorMedia::query()->lockForUpdate()->findOrFail($media->id);
                $inUse = ContentEntry::withTrashed()->where('featured_media_id', $current->id)->exists()
                    || Course::withTrashed()->where('featured_media_id', $current->id)->exists()
                    || CourseExam::withTrashed()->where('featured_media_id', $current->id)->exists()
                    || PolicyRevision::query()->where('snapshot->featured_media_id', $current->id)->exists();
                if ($inUse) {
                    throw ValidationException::withMessages([
                        'media' => 'This file is used by a course, quiz, editorial entry or policy revision and cannot be deleted.',
                    ]);
                }

                $current->delete();

                return $current->path;
            });
        } catch (ForeignKeyConstraintViolationException) {
            return back()->withErrors(['media' => 'This file is now in use and cannot be deleted.']);
        }

        ManagedFiles::disk()->delete($path);

        return back()->with('status', 'Media deleted.');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeUpload($request, false);
    }

    private function storeUpload(Request $request, bool $library): JsonResponse
    {
        $lesson = !$library && in_array($request->input('context'), ['lesson', 'exam'], true);
        $mimes = $library ? FeaturedMedia::MIMES : [...FeaturedMedia::MIMES, 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/webm'];
        if ($lesson) {
            $mimes = [...$mimes, ...self::DOCUMENT_MIMES];
        }
        $data = $request->validate([
            'context' => ['required', Rule::in($library ? ['library'] : ['editorial', 'ticket', 'course', 'lesson', 'exam'])],
            'file' => ['required', 'file', ($library || $lesson) ? 'max:51200' : 'max:12288',
                'mimetypes:'.implode(',', $mimes)],
        ]);
        abort_if($data['context'] !== 'ticket' && !$request->user()->isAdministrator(), 403);

        // Cap unclaimed private media rather than enabling unlimited anonymous staging.
        if (!$library) {
            $unclaimed = EditorMedia::query()->where('context', $data['context'])->where('uploaded_by', $request->user()->id)
                ->whereNull('content_entry_id')->whereNull('support_ticket_id')
                ->when($data['context'] === 'course', fn ($media) => $media->doesntHave('descriptionCourses'))
                ->when($data['context'] === 'lesson', fn ($media) => $media->doesntHave('lessons'))
                ->when($data['context'] === 'exam', fn ($media) => $media->doesntHave('exams'))
                ->where('created_at', '>=', now()->subDay())->count();
            abort_if($unclaimed >= 20, 429, 'Save your current draft before uploading more files.');
        }

        $file = $request->file('file');
        $path = ManagedFiles::store($file, 'editor-media');
        abort_unless($path, 500, 'Media upload was not saved.');

        try {
            $media = EditorMedia::create([
                'uploaded_by' => $request->user()->id,
                'context' => $data['context'],
                'path' => $path,
                'mime' => $file->getMimeType(),
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 190),
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            ManagedFiles::disk()->delete($path);
            throw $e;
        }

        return response()->json($this->describe($media), 201);
    }

    private function describe(EditorMedia $media): array
    {
        return [
            'id' => $media->id,
            'url' => route('editor-media.show', $media, false),
            'mime' => $media->mime,
            'name' => $media->original_name,
        ];
    }

    public function show(Request $request, EditorMedia $media): Response
    {
        $viewer = $request->user();
        if ($media->context === 'library') {
            $published = ContentEntry::query()->where('featured_media_id', $media->id)
                ->where('status', 'published')->whereIn('featured_kind', ['image', 'video'])
                ->where(fn ($entries) => $entries->where('type', '!=', 'post')->orWhere(fn ($posts) => $posts->visibleArticleCategory()))->exists();
            $published = $published || Course::published()->where('featured_media_id', $media->id)
                ->whereIn('featured_kind', ['image', 'video'])->exists();
            $published = $published || CourseExam::query()->where('featured_media_id', $media->id)
                ->where('kind', 'quiz')->whereNull('course_id')->where('published', true)
                ->whereIn('featured_kind', ['image', 'video'])->exists();
            abort_unless($published || $viewer?->isAdministrator(), 403);
        } elseif ($media->context === 'lesson') {
            $enrolled = $viewer?->role === User::ROLE_STUDENT
                && $media->lessons()->whereHas('module.course', fn ($courses) => $courses->published()
                    ->whereHas('enrollments', fn ($enrollments) => $enrollments
                        ->where('student_id', $viewer->id)->where('status', 'approved')))->exists();
            abort_unless($viewer?->isAdministrator() || $enrolled, 403);
        } elseif ($media->context === 'exam') {
            $enrolled = $viewer?->role === User::ROLE_STUDENT
                && ExamAttempt::whereIn('course_exam_id', $media->exams()->select('course_exams.id'))
                    ->whereNull('submitted_at')->where('expires_at', '>', now())
                    ->where(fn ($query) => $query
                        ->where(fn ($standalone) => $standalone->whereNull('course_enrollment_id')->where('student_id', $viewer->id))
                        ->orWhereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $viewer->id)
                            ->where('status', 'approved')->whereHas('course', fn ($courses) => $courses->published())))
                    ->cursor()->contains(fn ($attempt) => in_array($media->id,
                        AssessmentQuestions::mediaIds($attempt->snapshot), true));
            abort_unless($viewer?->isAdministrator() || $enrolled, 403);
        } elseif ($media->context === 'course') {
            $published = $media->descriptionCourses()->published()->exists();
            abort_unless($published || $viewer?->isAdministrator(), 403);
        } elseif ($media->context === 'editorial') {
            $approved = $media->content_entry_id !== null &&
                ContentEntry::query()->whereKey($media->content_entry_id)
                    ->where('status', 'published')->whereIn('type', [...ContentEntry::DIRECT_PUBLICATION_TYPES, 'faq'])
                    ->where(fn ($entries) => $entries->where('type', '!=', 'post')->orWhere(fn ($posts) => $posts->visibleArticleCategory()))->exists();
            abort_unless($approved || $viewer?->isAdministrator(), 403);
        } else {
            $ticket = $media->support_ticket_id
                ? \App\Models\SupportTicket::find($media->support_ticket_id) : null;
            abort_unless($viewer && (
                ($ticket && ($viewer->isAdministrator() || $ticket->user_id === $viewer->id)) ||
                (!$ticket && $media->support_ticket_id === null && $media->uploaded_by === $viewer->id)
            ), 403);
        }
        $headers = [
            'Content-Type' => $media->mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        $assessmentResource = in_array($media->context, ['lesson', 'exam'], true);
        $downloadName = $assessmentResource && in_array($media->mime, self::DOCUMENT_MIMES, true) ? $media->original_name : null;
        $seekable = str_starts_with($media->mime, 'video/') || ($assessmentResource && str_starts_with($media->mime, 'audio/'));

        return ManagedFiles::response($request, $media->path, $headers, $seekable, $downloadName);
    }
}
