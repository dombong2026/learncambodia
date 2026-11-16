<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['course_lessons', 'curriculum_lesson_revisions'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->longText('content_html')->nullable());
        }

        Schema::create('course_lesson_media', function (Blueprint $table): void {
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_media_id')->constrained('editor_media')->restrictOnDelete();
            $table->primary(['course_lesson_id', 'editor_media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_media');
        foreach (['course_lessons', 'curriculum_lesson_revisions'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('content_html'));
        }
    }
};
