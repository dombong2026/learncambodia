<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('instructor_feedback_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->timestamps();
            $table->index(['instructor_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_feedback_templates');
    }
};
