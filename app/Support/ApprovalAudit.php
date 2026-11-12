<?php

namespace App\Support;

use App\Models\ApprovalEvent;
use App\Models\User;

final class ApprovalAudit
{
    public static function record(
        string $type,
        int $id,
        User $actor,
        string $action,
        ?string $from,
        string $to,
        ?string $note = null
    ): void {
        ApprovalEvent::create([
            'subject_type' => $type,
            'subject_id' => $id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
