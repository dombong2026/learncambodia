<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('lesson_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['lesson_id', 'parent_id', 'id']);
        });

        Schema::create('lesson_comment_reactions', function (Blueprint $table): void {
            $table->foreignId('comment_id')->constrained('lesson_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->primary(['comment_id', 'user_id', 'kind'], 'lesson_reactions_primary');
        });

        Schema::create('lesson_support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('course_lessons')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['instructor_id', 'enrollment_id', 'lesson_id', 'id'], 'lesson_support_thread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_support_messages');
        Schema::dropIfExists('lesson_comment_reactions');
        Schema::dropIfExists('lesson_comments');
    }
};
