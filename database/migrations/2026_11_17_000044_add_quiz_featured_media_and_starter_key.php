<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->string('featured_kind', 16)->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('editor_media')->restrictOnDelete();
            $table->string('featured_alt', 200)->nullable();
            $table->string('featured_value', 32)->nullable();
            $table->string('starter_key', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_media_id');
            $table->dropUnique(['starter_key']);
            $table->dropColumn(['featured_kind', 'featured_alt', 'featured_value', 'starter_key']);
        });
    }
};
