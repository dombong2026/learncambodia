<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->changeCourseLinks(nullable: true);
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->string('kind', 12)->default('exam')->index();
            $table->json('pricing')->nullable();
            $table->boolean('live_feedback')->default(false);
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('grading')->nullable();
            $table->index(['student_id', 'course_exam_id'], 'exam_attempts_student_exam_index');
        });
        Schema::create('assessment_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->json('quote');
            $table->string('status', 16)->default('pending');
            $table->string('reference', 160)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('course_exams')->whereNull('course_id')->exists()
            || DB::table('exam_attempts')->whereNull('course_enrollment_id')->exists()
            || DB::table('assessment_access')->exists()) {
            throw new RuntimeException('Standalone assessment records exist. Preserve them before reverting the schema.');
        }

        Schema::dropIfExists('assessment_access');
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropIndex('exam_attempts_student_exam_index');
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn('grading');
        });
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'pricing', 'live_feedback']);
        });
        $this->changeCourseLinks(nullable: false);
    }

    private function changeCourseLinks(bool $nullable): void
    {
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        foreach ([
            ['course_exams', 'course_id', 'cascade'],
            ['exam_attempts', 'course_enrollment_id', 'restrict'],
        ] as [$name, $column, $onDelete]) {
            if ($mysql) {
                // MySQL requires the foreign key to be removed while changing nullability.
                $constraint = $name.'_'.$column.'_foreign';
                $parent = $column === 'course_id' ? 'courses' : 'course_enrollments';
                $null = $nullable ? 'NULL' : 'NOT NULL';
                $exists = collect(Schema::getForeignKeys($name))->contains('name', $constraint);
                if ($exists) {
                    DB::statement("ALTER TABLE `$name` DROP FOREIGN KEY `$constraint`, MODIFY `$column` BIGINT UNSIGNED $null");
                } else {
                    DB::statement("ALTER TABLE `$name` MODIFY `$column` BIGINT UNSIGNED $null");
                }
                DB::statement("ALTER TABLE `$name` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$parent` (`id`) ON DELETE $onDelete");
            } else {
                Schema::table($name, function (Blueprint $table) use ($column, $nullable): void {
                    $table->foreignId($column)->nullable($nullable)->change();
                });
            }
        }
    }
};
