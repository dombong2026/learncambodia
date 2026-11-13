<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('slug', 200);
            $table->string('title', 200);
            $table->string('summary', 400)->nullable();
            $table->longText('body');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_entries');
    }
};
