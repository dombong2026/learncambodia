<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->longText('description_html')->nullable();
        });

        Schema::create('course_description_media', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_media_id')->constrained('editor_media')->restrictOnDelete();
            $table->primary(['course_id', 'editor_media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_description_media');
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('description_html'));
    }
};
