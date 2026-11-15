<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_entries', function (Blueprint $table): void {
            $table->string('featured_kind', 10)->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('editor_media')->restrictOnDelete();
            $table->string('featured_value', 11)->nullable();
            $table->string('featured_alt', 200)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('policy_version', 40)->nullable();
            $table->date('policy_effective_on')->nullable();
        });

        Schema::create('policy_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_revisions');

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_media_id');
        });

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'featured_kind', 'featured_value', 'featured_alt', 'published_at',
                'policy_version', 'policy_effective_on',
            ]);
        });
    }
};
