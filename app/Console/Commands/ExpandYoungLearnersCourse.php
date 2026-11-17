<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CurriculumLessonRevision;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpandYoungLearnersCourse extends Command
{
    public const SLUG = 'teaching-young-learners-stories-movement-and-games';

    protected $signature = 'courses:expand-young-learners';

    protected $description = 'Add the reviewed classroom activities to the Teaching Young Learners draft.';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $course = Course::where('slug', self::SLUG)->lockForUpdate()->firstOrFail();
            if (!$course->isMutable() || $course->category()->value('code') !== 'TEYL') {
                $this->error('The specified TEYL course must be an editable draft.');

                return self::FAILURE;
            }

            $author = User::findOrFail($course->created_by);
            $curriculum = require base_path('support/editorial/course-expansions/teaching-young-learners.php');
            $created = 0;
            foreach ($curriculum as $moduleTitle => $lessons) {
                $module = $course->modules()->where('title', $moduleTitle)->first();
                if ($module === null) {
                    $module = $course->modules()->create([
                        'title' => $moduleTitle,
                        'summary' => 'Adapt the techniques to your classroom and evaluate learner responses.',
                        'position' => $course->modules()->max('position') + 1,
                    ]);
                }
                foreach ($lessons as $lesson) {
                    if ($module->lessons()->where('title', $lesson['title'])->exists()) {
                        continue;
                    }

                    [$prompt, $correct, $distractor, $feedback] = $lesson['question'];
                    $choices = $created % 2 === 0 ? ['a' => $correct, 'b' => $distractor] : ['a' => $distractor, 'b' => $correct];
                    $item = $module->lessons()->create([
                        'title' => $lesson['title'], 'content' => $lesson['content'],
                        'duration_minutes' => $lesson['minutes'], 'position' => $module->lessons()->max('position') + 1,
                        'is_preview' => false,
                        'quiz_questions' => [[
                            'prompt' => $prompt, 'choices' => $choices,
                            'correct' => array_search($correct, $choices, true), 'feedback' => $feedback,
                        ]],
                    ]);
                    CurriculumLessonRevision::capture($course, $item, $author, 'created');
                    $created++;
                }
            }

            $this->info("TEYL_LESSONS_ADDED={$created}");
            $this->info('TEYL_TOTAL_LESSONS='.$course->lessons()->count());

            return self::SUCCESS;
        });
    }
}
