<?php

namespace App\Support;

use App\Models\Course;

final class CourseReadiness
{
    public static function checklist(Course $course): array
    {
        $modules=$course->modules()->withCount('lessons')->get();
        $quizzes=$course->quizzes()->withCount('questions')->get();

        return [
            'Published academic category' => $course->category()->where('is_published',true)->exists(),
            'Complete course title and summary' => filled($course->title) && filled($course->summary),
            'Full course description' => filled($course->description),
            'Every module includes a lesson' => $modules->isNotEmpty() &&
                $modules->every(fn($module)=>$module->lessons_count>0),
            'Every practice quiz includes a question' => $quizzes->every(fn($quiz)=>$quiz->questions_count>0),
        ];
    }

    public static function ready(Course $course): bool
    {
        return !in_array(false,self::checklist($course),true);
    }

    public static function fingerprint(Course $course): string
    {
        // Excludes review status/timestamps so it remains constant across submission.
        $course->load('modules.lessons','quizzes.questions');
        $data=[
            'course'=>$course->only([
                'title','slug','summary','description','category_id','cefr_level','delivery_mode',
            ]),
            'modules'=>$course->modules->map(fn($module)=>[
                'id'=>$module->id,'title'=>$module->title,'summary'=>$module->summary,
                'position'=>$module->position,
                'lessons'=>$module->lessons->map(fn($lesson)=>$lesson->only([
                    'id','title','content','position','duration_minutes','is_preview',
                ]))->values()->all(),
            ])->values()->all(),
            'quizzes'=>$course->quizzes->map(fn($quiz)=>[
                'id'=>$quiz->id,'title'=>$quiz->title,'instructions'=>$quiz->instructions,
                'position'=>$quiz->position,'passing_percent'=>$quiz->passing_percent,
                'questions'=>$quiz->questions->map(fn($question)=>$question->only([
                    'id','prompt','option_a','option_b','option_c','option_d',
                    'correct_option','explanation','position',
                ]))->values()->all(),
            ])->values()->all(),
        ];

        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
}
