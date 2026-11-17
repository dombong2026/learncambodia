<?php

use App\Support\PublicId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['users' => 8, 'courses' => 6, 'course_exams' => 6, 'course_quizzes' => 6, 'content_entries' => 6];

    public function up(): void
    {
        if (!Schema::hasTable('public_record_ids')) {
            Schema::create('public_record_ids', function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
            });
        }

        // Preserve old slug-only URLs that already look like a public-ID path.
        foreach (['courses', 'content_entries'] as $name) {
            DB::table($name)->select('id', 'slug')->where('slug', 'like', '%-%')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        if ($id = PublicId::fromPath($row->slug)) {
                            DB::table('public_record_ids')->insertOrIgnore(['id' => $id]);
                        }
                    }
                });
        }

        foreach (self::TABLES as $name => $digits) {
            if (!Schema::hasColumn($name, 'public_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->unsignedInteger('public_id')->nullable()->unique();
                });
            }

            $connection = DB::connection();
            $connection->table($name)->select('id')->whereNull('public_id')->chunkById(500, function ($rows) use ($connection, $name, $digits): void {
                $connection->transaction(function () use ($connection, $name, $digits, $rows): void {
                    foreach ($rows as $row) {
                        $connection->table($name)->where('id', $row->id)->whereNull('public_id')
                            ->update(['public_id' => PublicId::allocate($connection, $digits)]);
                    }
                });
            }, 'id');
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }

        Schema::dropIfExists('public_record_ids');
    }
};
