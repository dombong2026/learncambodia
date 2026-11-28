<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_smtp_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('payload');
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_smtp_settings');
    }
};
