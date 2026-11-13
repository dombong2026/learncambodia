<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->string('title', 160);
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'enrollment_id']);
        });
        Schema::create('study_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goal_id')->constrained('study_goals')->cascadeOnDelete();
            $table->string('title', 160);
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('study_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedTinyInteger('weekday');
            $table->string('start_time', 5);
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
            $table->index(['student_id', 'weekday', 'start_time']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('study_tasks');
        Schema::dropIfExists('study_goals');
    }
};
