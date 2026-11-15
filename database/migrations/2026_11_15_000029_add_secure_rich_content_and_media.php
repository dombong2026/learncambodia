<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_entries', function (Blueprint $table): void {
            $table->longText('body_html')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();
        });
        Schema::table('support_messages', function (Blueprint $table): void {
            $table->longText('body_html')->nullable();
        });
        Schema::create('editor_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('context', 16);
            $table->foreignId('content_entry_id')->nullable()->constrained('content_entries')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->string('path');
            $table->string('mime', 100);
            $table->string('original_name', 190);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['uploaded_by', 'context', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_media');
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn('body_html'));
        Schema::table('content_entries', fn (Blueprint $table) => $table->dropColumn(['body_html','seo_title','seo_description']));
    }
};
