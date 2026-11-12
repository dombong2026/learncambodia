<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->index(['course_id', 'position']);
        });

        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->longText('content');
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedSmallInteger('duration_minutes')->default(15);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
            $table->index(['course_module_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_modules');
    }
};
