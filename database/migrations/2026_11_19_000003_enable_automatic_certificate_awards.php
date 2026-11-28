<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_types', function (Blueprint $table): void {
            $table->string('kind', 12)->nullable()->unique();
        });
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->string('default_kind', 12)->nullable()->unique();
        });

        Schema::table('course_exams', function (Blueprint $table): void {
            $table->foreignId('certificate_template_id')->nullable()->constrained()->nullOnDelete();
        });
        $this->changeLegacyLinks(true);
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('award_key', 80)->nullable()->unique();
        });

        foreach ([
            'course' => ['Course', 'Certificate of Course Completion', 'For successfully completing {course}.'],
            'exam' => ['Exam', 'Certificate of Exam Achievement', 'For successfully passing {assessment}.'],
            'quiz' => ['Quiz', 'Certificate of Quiz Achievement', 'For successfully passing {assessment}.'],
        ] as $kind => [$name, $heading, $body]) {
            $type = DB::table('certificate_types')->where('name', $name)->first();
            if ($type) {
                DB::table('certificate_types')->where('id', $type->id)->update(['name' => $name, 'kind' => $kind]);
                $typeId = $type->id;
            } else {
                $typeId = DB::table('certificate_types')->insertGetId([
                    'name' => $name, 'kind' => $kind, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $templateName = 'Default '.$name.' Certificate';
            $template = DB::table('certificate_templates')->where('certificate_type_id', $typeId)
                ->where('name', $templateName)->orderBy('id')->first();
            if ($template) {
                DB::table('certificate_templates')->where('id', $template->id)->update(['default_kind' => $kind]);

                continue;
            }
            DB::table('certificate_templates')->insert([
                'certificate_type_id' => $typeId, 'default_kind' => $kind, 'name' => $templateName,
                'heading' => $heading, 'body' => $body, 'issuer' => 'LearnCambodia',
                'accent' => ['course' => '#8b5e18', 'exam' => '#29508d', 'quiz' => '#26745c'][$kind],
                'orientation' => 'landscape', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('issued_certificates')->whereNotNull('award_key')->exists()
            || DB::table('issued_certificates')->whereNull('issued_by')->exists()
            || DB::table('issued_certificates')->whereNull('course_enrollment_id')->exists()) {
            throw new RuntimeException('Automatic certificates exist. Preserve them before reverting the schema.');
        }

        // Keep templates and types because administrators may have personalized or assigned them.
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->dropUnique(['award_key']);
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn('award_key');
        });
        $this->changeLegacyLinks(false);
        Schema::table('course_exams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_template_id');
        });
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->dropUnique(['default_kind']);
            $table->dropColumn('default_kind');
        });
        Schema::table('certificate_types', function (Blueprint $table): void {
            $table->dropUnique(['kind']);
            $table->dropColumn('kind');
        });
    }

    private function changeLegacyLinks(bool $nullable): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE issued_certificates
                DROP FOREIGN KEY issued_certificates_course_enrollment_id_foreign,
                DROP FOREIGN KEY issued_certificates_issued_by_foreign,
                MODIFY course_enrollment_id BIGINT UNSIGNED $null,
                MODIFY issued_by BIGINT UNSIGNED $null");
            DB::statement('ALTER TABLE issued_certificates
                ADD CONSTRAINT issued_certificates_course_enrollment_id_foreign
                    FOREIGN KEY (course_enrollment_id) REFERENCES course_enrollments (id) ON DELETE RESTRICT,
                ADD CONSTRAINT issued_certificates_issued_by_foreign
                    FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE RESTRICT');

            return;
        }

        Schema::table('issued_certificates', function (Blueprint $table) use ($nullable): void {
            $table->foreignId('course_enrollment_id')->nullable($nullable)->change();
            $table->foreignId('issued_by')->nullable($nullable)->change();
        });
    }
};
