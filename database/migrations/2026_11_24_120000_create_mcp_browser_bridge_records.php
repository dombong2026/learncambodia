<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mcp_action_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('token_id');
            $table->string('operation', 20);
            $table->string('resource', 70);
            $table->string('resource_id', 80);
            $table->char('payload_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['token_id', 'status', 'expires_at'], 'mcp_approval_session_status');
        });

        Schema::create('mcp_action_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('tool', 75);
            $table->string('operation', 20);
            $table->string('resource', 70)->nullable();
            $table->string('resource_id', 80)->nullable();
            $table->string('result', 30);
            $table->unsignedSmallInteger('http_status');
            $table->string('summary', 250);
            $table->timestamps();
            $table->index(['created_at','result'], 'mcp_audit_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_action_audits');
        Schema::dropIfExists('mcp_action_approvals');
    }
};
