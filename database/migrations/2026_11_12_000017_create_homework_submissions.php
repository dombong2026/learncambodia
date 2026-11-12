<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('course_homework_assignments')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->restrictOnDelete();
            $table->longText('body');
            $table->unsignedTinyInteger('version')->default(1);
            $table->string('status', 20)->default('submitted')->index();
            $table->timestamp('submitted_at');
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'enrollment_id']);
        });

        Schema::create('homework_submission_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('homework_submissions')->cascadeOnDelete();
            $table->unsignedTinyInteger('version');
            $table->longText('body');
            $table->timestamp('submitted_at');
            $table->unique(['submission_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submission_versions');
        Schema::dropIfExists('homework_submissions');
    }
};
