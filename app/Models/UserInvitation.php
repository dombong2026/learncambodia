<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserInvitation extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function statusLabel(): string
    {
        return __('invitations.'.match (true) {
            $this->revoked_at !== null => 'revoked_status',
            $this->expires_at->isPast() => 'expired',
            $this->sent_at === null => 'unsent',
            default => 'invited',
        });
    }

    public function canBeAccepted(User $user, ?User $inviter): bool
    {
        return $user->isInvitedAccount()
            && $this->email === $user->email
            && $this->token_hash !== null
            && $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $inviter?->isActiveAccount()
            && \App\Support\AdminAccess::canEdit($inviter, $user);
    }
}
