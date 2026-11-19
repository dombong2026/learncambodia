<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->json('design')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->dropColumn('design');
        });
    }
};
