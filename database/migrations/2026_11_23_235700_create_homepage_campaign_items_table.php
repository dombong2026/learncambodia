<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('homepage_campaign_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 195)->unique();
            $table->string('kind', 8)->default('quiz');
            $table->string('status', 12)->default('draft')->index();
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->string('draft_title', 180)->nullable();
            $table->string('draft_kind', 8)->nullable();
            $table->string('draft_cover_asset', 75)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('cover_asset', 75)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_campaign_items');
    }
};
