<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_social_auth', function (Blueprint $table): void {
            $table->string('provider', 16)->primary();
            $table->boolean('enabled')->default(false);
            $table->text('credentials');
        });

        Schema::create('social_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->char('client', 64);
            $table->char('identity', 64);
            $table->unique(['provider', 'client', 'identity']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_identities');
        Schema::dropIfExists('managed_social_auth');
    }
};
