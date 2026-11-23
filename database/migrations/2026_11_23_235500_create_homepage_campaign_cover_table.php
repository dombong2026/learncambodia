<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('homepage_campaign_cover', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('asset', 75)->nullable();
            $table->string('kind', 8)->default('quiz');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_campaign_cover');
    }
};
