<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->string('title', 140);
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->unique(['student_id', 'enrollment_id', 'lesson_id']);
            $table->index(['student_id', 'is_pinned', 'updated_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
    }
};
