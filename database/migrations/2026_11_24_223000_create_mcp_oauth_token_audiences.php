<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passport tables are created by its own vendor migrations. This
        // additive table captures the sole allowed MCP resource audience.
        Schema::create('mcp_oauth_token_audiences', function (Blueprint $table): void {
            $table->string('access_token_id', 128)->primary();
            $table->foreignId('user_id')->index();
            $table->string('client_id', 128)->index();
            $table->string('resource', 255);
            $table->timestamps();
            $table->index(['client_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_oauth_token_audiences');
    }
};
