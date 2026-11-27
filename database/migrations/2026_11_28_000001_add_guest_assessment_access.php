<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->boolean('guest_enabled')->default(false);
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->string('guest_identity_hash', 64)->nullable();
            $table->index(['course_exam_id', 'guest_identity_hash'], 'exam_attempts_guest_exam_index');
        });
    }

    public function down(): void
    {
        if (DB::table('exam_attempts')->whereNotNull('guest_identity_hash')->exists()
            || DB::table('course_exams')->where('guest_enabled', true)->exists()) {
            throw new RuntimeException('Preserve guest assessment settings and attempts before reverting.');
        }
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropIndex('exam_attempts_guest_exam_index');
            $table->dropColumn('guest_identity_hash');
        });
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->dropColumn('guest_enabled');
        });
    }
};
