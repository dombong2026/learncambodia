<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('course_instructor_assignments', 'support_channels')) {
            Schema::table('course_instructor_assignments', function (Blueprint $table): void {
                $table->json('support_channels')->nullable();
            });
        }

        if (!Schema::hasIndex('lesson_support_messages', 'course_support_thread_index')) {
            Schema::table('lesson_support_messages', function (Blueprint $table): void {
                $table->index(['instructor_id', 'enrollment_id', 'id'], 'course_support_thread_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('lesson_support_messages', function (Blueprint $table): void {
            $table->dropIndex('course_support_thread_index');
        });

        Schema::table('course_instructor_assignments', function (Blueprint $table): void {
            $table->dropColumn('support_channels');
        });
    }
};
