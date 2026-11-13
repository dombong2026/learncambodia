<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCourseRevisionController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $revision = DB::transaction(function () use ($course, $request): Course {
            // Lock the source and rely on a unique source_course_id constraint.
            $source = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($source->status === 'published'
                && $source->published_at !== null
                && $source->published_at->isPast()
                && $source->superseded_at === null, 409,
                'Only the current published edition can be revised.');

            abort_if(Course::query()->where('source_course_id', $source->id)->exists(), 409,
                'A revision already exists. Edit and submit that draft instead.');

            $number = $source->revision_number + 1;
            $slug = $source->slug.'-v'.$number;
            abort_if(mb_strlen($slug) > 255 || Course::where('slug', $slug)->exists(), 409,
                'The proposed revision URL is already in use.');

            $new = Course::create([
                'source_course_id' => $source->id,
                'revision_number' => $number,
                'category_id' => $source->category_id,
                'slug' => $slug,
                'title' => $source->title,
                'summary' => $source->summary,
                'description' => $source->description,
                'cefr_level' => $source->cefr_level,
                'delivery_mode' => $source->delivery_mode,
                'status' => 'draft',
                'published_at' => null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($source->modules()->with('lessons')->get() as $module) {
                $copied = $new->modules()->create([
                    'title' => $module->title,
                    'summary' => $module->summary,
                    'position' => $module->position,
                ]);
                foreach ($module->lessons as $lesson) {
                    $copied->lessons()->create([
                        'title' => $lesson->title,
                        'content' => $lesson->content,
                        'position' => $lesson->position,
                        'duration_minutes' => $lesson->duration_minutes,
                        'is_preview' => $lesson->is_preview,
                    ]);
                }
            }

            foreach ($source->quizzes()->with('questions')->get() as $quiz) {
                $copied = $new->quizzes()->create([
                    'title' => $quiz->title,
                    'instructions' => $quiz->instructions,
                    'position' => $quiz->position,
                    'passing_percent' => $quiz->passing_percent,
                ]);
                foreach ($quiz->questions as $question) {
                    $copied->questions()->create([
                        'prompt' => $question->prompt,
                        'option_a' => $question->option_a,
                        'option_b' => $question->option_b,
                        'option_c' => $question->option_c,
                        'option_d' => $question->option_d,
                        'correct_option' => $question->correct_option,
                        'explanation' => $question->explanation,
                        'position' => $question->position,
                    ]);
                }
            }

            \App\Support\ApprovalAudit::record('course', $new->id, $request->user(),
                'revision_created', null, 'draft',
                'Edition '.$number.' copied from course '.$source->id);

            return $new;
        });

        return redirect()->route('admin.course-builder.edit', $revision)
            ->with('status', 'Editable course edition created. Existing learners stay on their published edition.');
    }
}
