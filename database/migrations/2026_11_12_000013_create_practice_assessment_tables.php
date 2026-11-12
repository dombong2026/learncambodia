<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedTinyInteger('passing_percent')->default(70);
            $table->timestamps();
            $table->index(['course_id', 'position']);
        });

        Schema::create('course_quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_quiz_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('option_a', 500);
            $table->string('option_b', 500);
            $table->string('option_c', 500);
            $table->string('option_d', 500);
            $table->string('correct_option', 1);
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->index(['course_quiz_id', 'position']);
        });

        Schema::create('practice_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_quiz_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->restrictOnDelete();
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedTinyInteger('score_percent');
            $table->boolean('passed');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['enrollment_id', 'course_quiz_id', 'submitted_at'], 'practice_attempt_lookup');
        });

        Schema::create('practice_attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('practice_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('course_quiz_questions')->restrictOnDelete();
            $table->string('selected_option', 1);
            $table->boolean('is_correct');
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_attempt_answers');
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('course_quiz_questions');
        Schema::dropIfExists('course_quizzes');
    }
};
