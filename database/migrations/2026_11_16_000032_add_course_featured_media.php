<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('featured_kind', 10)->nullable();
            $table->foreignId('featured_media_id')->nullable()->constrained('editor_media')->restrictOnDelete();
            $table->string('featured_value', 11)->nullable();
            $table->string('featured_alt', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_media_id');
            $table->dropColumn(['featured_kind', 'featured_value', 'featured_alt']);
        });
    }
};
