<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cefr_levels', function (Blueprint $table): void {
            $table->string('code', 2)->primary();
            $table->string('category', 40);
            $table->string('name', 80);
            $table->text('description');
            $table->unsignedSmallInteger('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cefr_levels');
    }
};
