<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promo_player_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('revision')->default(0);
            $table->json('configuration');
        });

        DB::table('promo_player_settings')->insert(['id' => 1, 'configuration' => '{}']);
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_player_settings');
    }
};
