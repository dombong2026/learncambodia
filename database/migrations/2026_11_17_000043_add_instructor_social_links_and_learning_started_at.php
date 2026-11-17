<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('public_profiles', 'social_links')) {
            Schema::table('public_profiles', fn (Blueprint $table) => $table->json('social_links')->nullable());
        }

        if (!Schema::hasColumn('course_enrollments', 'started_at')) {
            Schema::table('course_enrollments', fn (Blueprint $table) => $table->timestamp('started_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropColumn('started_at'));
        Schema::table('public_profiles', fn (Blueprint $table) => $table->dropColumn('social_links'));
    }
};
