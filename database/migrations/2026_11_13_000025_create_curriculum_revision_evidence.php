<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('curriculum_lesson_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('snapshot_number');
            $table->string('action', 24);
            $table->string('title', 180);
            $table->longText('content');
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_preview')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['course_id','created_at']);
            $table->unique(['lesson_id','snapshot_number']);
        });
        Schema::create('course_review_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('checklist');
            $table->string('fingerprint',64);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision',20)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['course_id','created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('course_review_snapshots');
        Schema::dropIfExists('curriculum_lesson_revisions');
    }
};
