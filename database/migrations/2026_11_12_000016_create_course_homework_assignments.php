<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_homework_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('instructions');
            $table->text('rubric')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_homework_assignments');
    }
};
