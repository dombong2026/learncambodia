<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('source_course_id')->nullable()
                ->unique()->constrained('courses')->restrictOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->timestamp('superseded_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_course_id');
            $table->dropColumn(['revision_number', 'superseded_at']);
        });
    }
};
