<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['courses', 'course_exams'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('deactivated_at')->nullable();
                $table->softDeletes();
            });
        }

        Schema::table('assessment_access', function (Blueprint $table): void {
            $table->timestamp('assigned_at')->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('courses')->whereNotNull('deleted_at')->orWhereNotNull('deactivated_at')->exists()
            || \Illuminate\Support\Facades\DB::table('course_exams')->whereNotNull('deleted_at')->orWhereNotNull('deactivated_at')->exists()
            || \Illuminate\Support\Facades\DB::table('assessment_access')->whereNotNull('assigned_at')->exists()) {
            throw new RuntimeException('Learning manager state exists. Preserve it before reverting the schema.');
        }

        Schema::table('assessment_access', function (Blueprint $table): void {
            $table->dropColumn('assigned_at');
        });

        foreach (['courses', 'course_exams'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['deactivated_at', 'deleted_at']);
            });
        }
    }
};
