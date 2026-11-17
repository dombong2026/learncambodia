<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_branding', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('settings');
        });

        DB::table('platform_branding')->insert(['id' => 1, 'settings' => '{}']);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_branding');
    }
};
