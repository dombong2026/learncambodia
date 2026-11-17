<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['courses', 'course_exams', 'content_entries'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $requires = !Schema::hasColumn($name, 'public_requirement');
            $language = !Schema::hasColumn($name, 'content_language');
            if (!$requires && !$language) continue;

            Schema::table($name, function (Blueprint $table) use ($requires, $language): void {
                if ($requires) $table->text('public_requirement')->nullable();
                if ($language) $table->string('content_language', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['public_requirement', 'content_language']));
        }
    }
};
