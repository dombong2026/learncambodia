<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_instructor_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'instructor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructor_assignments');
    }
};
