<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignAccountRole extends Command
{
    protected $signature = 'accounts:assign-role
        {email : Existing account email address}
        {role : student, admin or developer}
        {--operator= : Approved operator identifier}
        {--reason= : Approved reason or change ticket}
        {--confirm : Explicitly authorize the role transition}';

    protected $description = 'Audit and apply an authorized account role change from the server console only.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $role = (string) $this->argument('role');
        $operator = trim((string) $this->option('operator'));
        $reason = trim((string) $this->option('reason'));

        if (! in_array($role, [User::ROLE_STUDENT, User::ROLE_ADMIN, User::ROLE_DEVELOPER], true)) {
            $this->error('Invalid role.');
            return self::FAILURE;
        }

        if (! $this->option('confirm') || mb_strlen($operator) < 3
            || mb_strlen($operator) > 120 || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            $this->error('Explicit --confirm, --operator (3–120 chars) and --reason (10–500 chars) are required.');
            return self::FAILURE;
        }

        $found = User::query()->where('email', $email)->exists();

        if (! $found) {
            $this->error('Account not found.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $role, $operator, $reason): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->firstOrFail();

            if ($user->role === $role) {
                $this->warn('Account already has this role; no changes made.');
                return;
            }

            $previousRole = $user->role;
            $user->forceFill(['role' => $role])->save();
            // Existing API tokens must never outlive a role transition.
            $user->tokens()->delete();

            DB::table('account_role_changes')->insert([
                'user_id' => $user->id,
                'previous_role' => $previousRole,
                'new_role' => $role,
                'operator_name' => $operator,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $this->info('Role updated, existing API tokens revoked and audit entry recorded.');
        });

        return self::SUCCESS;
    }
}
