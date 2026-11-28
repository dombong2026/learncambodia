<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_assessment_owners', function (Blueprint $table): void {
            $table->foreignId('course_exam_id')->primary()->constrained('course_exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('mcp_assessment_owners')->exists()) {
            throw new RuntimeException('Preserve assessment ownership before reverting this schema.');
        }
        Schema::dropIfExists('mcp_assessment_owners');
    }
};
