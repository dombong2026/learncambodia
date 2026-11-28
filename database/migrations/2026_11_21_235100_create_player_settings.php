<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('audio_player', 16);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_settings');
    }
};
