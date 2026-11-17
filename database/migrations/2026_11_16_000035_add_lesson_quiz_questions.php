<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['course_lessons', 'curriculum_lesson_revisions'] as $table) {
            if (!Schema::hasColumn($table, 'quiz_questions')) {
                Schema::table($table, fn (Blueprint $schema) => $schema->json('quiz_questions')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['course_lessons', 'curriculum_lesson_revisions'] as $table) {
            Schema::table($table, fn (Blueprint $schema) => $schema->dropColumn('quiz_questions'));
        }
    }
};
