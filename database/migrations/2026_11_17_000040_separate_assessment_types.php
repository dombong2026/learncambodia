<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table): void {
            $table->string('kind', 12)->default('exam');
            $table->unique(['kind', 'name']);
        });
        Schema::table('exam_types', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });

        // Give existing quizzes independent copies of their types and preserve exam type IDs.
        DB::transaction(function (): void {
            $types = DB::table('exam_types')->where('kind', 'exam')
                ->whereIn('id', DB::table('course_exams')->where('kind', 'quiz')->select('exam_type_id'))
                ->orderBy('id')->cursor();

            foreach ($types as $type) {
                $quizType = DB::table('exam_types')->insertGetId([
                    'kind' => 'quiz',
                    'name' => $type->name,
                    'description' => $type->description,
                    'created_at' => $type->created_at,
                    'updated_at' => $type->updated_at,
                ]);

                DB::table('course_exams')->where('kind', 'quiz')->where('exam_type_id', $type->id)
                    ->update(['exam_type_id' => $quizType]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('exam_types')->where('kind', 'quiz')->exists()) {
            throw new RuntimeException('Quiz types must be preserved before reverting this migration.');
        }

        Schema::table('exam_types', function (Blueprint $table): void {
            $table->unique('name');
            $table->dropUnique(['kind', 'name']);
        });
        Schema::table('exam_types', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
