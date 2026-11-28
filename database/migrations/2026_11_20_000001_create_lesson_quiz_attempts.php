<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('retry_of_id')->nullable()->constrained('lesson_quiz_attempts')->nullOnDelete();
            $table->json('questions');
            $table->json('answers');
            $table->unsignedTinyInteger('correct_count');
            $table->timestamps();
            $table->index(['enrollment_id', 'lesson_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_quiz_attempts');
    }
};
