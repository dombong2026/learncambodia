<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('file_id')->constrained('editor_media')->restrictOnDelete();
            $table->foreignId('cover_id')->nullable()->constrained('editor_media')->restrictOnDelete();
            $table->string('title', 180);
            $table->string('author', 150)->nullable();
            $table->string('topic', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('kind', 20)->index();
            $table->string('access', 20);
            $table->string('status', 20)->index();
            $table->boolean('allow_download')->default(false);
            $table->unsignedSmallInteger('loan_days')->default(14);
            $table->unsignedSmallInteger('copies')->default(1);
            $table->timestamps();
        });

        Schema::create('library_saves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'library_item_id']);
        });

        Schema::create('library_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->index(['library_item_id', 'returned_at', 'due_at'], 'library_loan_availability');
            $table->index(['user_id', 'returned_at', 'due_at'], 'library_loan_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_saves');
        Schema::dropIfExists('library_items');
    }
};
