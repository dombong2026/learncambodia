<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'account_status' => fn (Blueprint $table) => $table->string('account_status', 16)->default('active'),
            'account_auth_version' => fn (Blueprint $table) => $table->unsignedInteger('account_auth_version')->default(0),
            'phone' => fn (Blueprint $table) => $table->string('phone', 16)->nullable(),
            'phone_verified_at' => fn (Blueprint $table) => $table->timestamp('phone_verified_at')->nullable(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }

        if (! Schema::hasTable('user_account_events')) {
            Schema::create('user_account_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
                $table->string('action', 32);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_events');
        Schema::table('users', fn (Blueprint $table) =>
            $table->dropColumn(['account_status', 'account_auth_version', 'phone', 'phone_verified_at']));
    }
};
