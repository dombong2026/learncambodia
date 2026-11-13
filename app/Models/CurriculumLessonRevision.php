<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumLessonRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_id','lesson_id','edited_by','snapshot_number','action','title',
        'content','position','duration_minutes','is_preview','created_at',
    ];

    protected function casts(): array
    {
        return ['created_at'=>'datetime','is_preview'=>'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class,'edited_by');
    }

    public static function capture(Course $course, CourseLesson $lesson, User $actor, string $action): void
    {
        $number=1+(int) self::where('lesson_id',$lesson->id)->max('snapshot_number');
        self::create([
            'course_id'=>$course->id,'lesson_id'=>$lesson->id,'edited_by'=>$actor->id,
            'snapshot_number'=>$number,'action'=>$action,'title'=>$lesson->title,
            'content'=>$lesson->content,'position'=>$lesson->position,
            'duration_minutes'=>$lesson->duration_minutes,'is_preview'=>$lesson->is_preview,
            'created_at'=>now(),
        ]);
    }
}
