<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseResource;
use App\Models\EditorMedia;
use App\Support\ManagedFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminCourseResourceController extends Controller
{
    private const MIMES = 'application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,audio/mpeg,audio/ogg,audio/wav,image/png,image/jpeg';

    public function index(Course $course): View
    {
        return view('workspace.admin.course-builder.resources', [
            'role' => 'admin',
            'course' => $course,
            'resources' => $course->resources()->with('media')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        return $this->save($request, $course);
    }

    public function update(Request $request, Course $course, CourseResource $resource): RedirectResponse
    {
        abort_unless($resource->course_id === $course->id, 404);

        return $this->save($request, $course, $resource);
    }

    public function download(Request $request, Course $course, CourseResource $resource): Response
    {
        abort_unless($resource->course_id === $course->id, 404);

        return $resource->download($request);
    }

    public function destroy(Course $course, CourseResource $resource): RedirectResponse
    {
        abort_unless($resource->course_id === $course->id, 404);
        $resource->delete();

        return back()->with('status', 'Resource removed from this edition.');
    }

    private function save(Request $request, Course $course, ?CourseResource $resource = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'position' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'file' => [$resource ? 'nullable' : 'required', 'file', 'max:51200', 'mimetypes:'.self::MIMES],
        ]);
        $upload = $request->file('file');
        $path = $upload ? ManagedFiles::store($upload, 'editor-media') : null;
        abort_if($upload && !$path, 500, 'Resource upload was not saved.');

        try {
            DB::transaction(function () use ($request, $course, $resource, $data, $upload, $path): void {
                $locked = Course::query()->lockForUpdate()->findOrFail($course->id);
                $entry = $resource ? $locked->resources()->findOrFail($resource->id) : $locked->resources()->make();
                $entry->title = $data['title'];
                $entry->position = $data['position'] ?? $entry->position ?? min(999999, $locked->resources()->reorder()->max('position') + 1);
                if ($upload) {
                    $media = EditorMedia::create([
                        'uploaded_by' => $request->user()->id,
                        'context' => 'course_resource',
                        'path' => $path,
                        'mime' => $upload->getMimeType(),
                        'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 190),
                        'size' => $upload->getSize(),
                    ]);
                    $entry->editor_media_id = $media->id;
                }
                $entry->save();
            });
        } catch (Throwable $exception) {
            if ($path) {
                ManagedFiles::disk()->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'Course resource saved.');
    }
}
