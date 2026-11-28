<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->string('viewer_mode', 16)->default('default');
        });

        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->string('guest_browser', 64)->nullable();
            $table->string('guest_email', 64)->nullable()->index();
            $table->index(['guest_browser', 'course_exam_id'], 'exam_attempts_browser_exam_index');
        });
    }

    public function down(): void
    {
        if (DB::table('exam_attempts')->whereNotNull('guest_browser')->exists()) {
            throw new RuntimeException('Guest exam attempts exist. Preserve them before reverting the schema.');
        }

        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropIndex('exam_attempts_browser_exam_index');
            $table->dropIndex(['guest_email']);
            $table->dropColumn(['guest_browser', 'guest_email']);
        });

        Schema::table('course_exams', fn (Blueprint $table) => $table->dropColumn('viewer_mode'));
    }
};
