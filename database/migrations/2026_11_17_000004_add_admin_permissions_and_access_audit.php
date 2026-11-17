<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'admin_permissions')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('admin_permissions')->nullable());
        }

        if (! Schema::hasColumn('account_role_changes', 'actor_user_id')) {
            Schema::table('account_role_changes', fn (Blueprint $table) => $table->unsignedBigInteger('actor_user_id')->nullable());
        }

        foreach (['previous_permissions', 'new_permissions'] as $column) {
            if (! Schema::hasColumn('account_role_changes', $column)) {
                Schema::table('account_role_changes', fn (Blueprint $table) => $table->json($column)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('account_role_changes', function (Blueprint $table): void {
            $table->dropColumn(['actor_user_id', 'previous_permissions', 'new_permissions']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_permissions');
        });
    }
};
