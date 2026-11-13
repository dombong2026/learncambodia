<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16)->index();
            $table->string('status', 24)->default('draft')->index();
            $table->string('display_name', 100);
            $table->string('headline', 160);
            $table->string('subjects', 160)->nullable();
            $table->string('city', 80)->nullable();
            $table->text('bio')->nullable();
            $table->boolean('show_learning_summary')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('adult_confirmed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['kind','status','published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_profiles');
    }
};
