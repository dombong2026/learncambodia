<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_media_id')->constrained('editor_media')->restrictOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['course_id', 'editor_media_id']);
            $table->index(['course_id', 'position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_resources');
    }
};
