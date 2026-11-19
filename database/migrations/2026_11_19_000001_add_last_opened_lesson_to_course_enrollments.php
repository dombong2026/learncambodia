<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->foreignId('last_opened_lesson_id')->nullable()
                ->constrained('course_lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_opened_lesson_id');
        });
    }
};
