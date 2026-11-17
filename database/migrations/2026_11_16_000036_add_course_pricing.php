<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('courses', 'pricing')) {
            Schema::table('courses', fn (Blueprint $table) => $table->json('pricing')->nullable());
        }
        if (!Schema::hasColumn('course_enrollments', 'pricing_quote')) {
            Schema::table('course_enrollments', fn (Blueprint $table) => $table->json('pricing_quote')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropColumn('pricing_quote'));
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('pricing'));
    }
};
