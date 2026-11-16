<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('course_exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->longText('instructions_html')->nullable();
            $table->json('questions');
            $table->unsignedSmallInteger('time_limit');
            $table->unsignedTinyInteger('passing_percent');
            $table->unsignedTinyInteger('attempt_limit');
            $table->boolean('shuffle')->default(false);
            $table->boolean('gamified')->default(false);
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
        Schema::create('course_exam_media', function (Blueprint $table): void {
            $table->foreignId('course_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_media_id')->constrained('editor_media')->restrictOnDelete();
            $table->primary(['course_exam_id', 'editor_media_id']);
        });
        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_enrollment_id')->constrained()->restrictOnDelete();
            $table->json('snapshot');
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->boolean('passed')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['course_enrollment_id', 'course_exam_id']);
        });
        Schema::create('certificate_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certificate_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('heading', 140);
            $table->string('body', 700);
            $table->string('issuer', 140);
            $table->string('accent', 7);
            $table->string('orientation', 12);
            $table->timestamps();
        });
        Schema::create('course_certificate_templates', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_template_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'certificate_template_id']);
        });
        Schema::create('issued_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('certificate_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->uuid('code')->unique();
            $table->json('snapshot');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['course_enrollment_id', 'certificate_template_id'], 'issued_certificates_enrollment_template_unique');
        });
    }

    public function down(): void
    {
        foreach (['issued_certificates', 'course_certificate_templates', 'certificate_templates', 'certificate_types',
            'exam_attempts', 'course_exam_media', 'course_exams', 'exam_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
