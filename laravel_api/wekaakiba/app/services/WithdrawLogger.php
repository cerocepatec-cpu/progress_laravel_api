<?php

namespace App\Services;

use App\Models\WithdrawRequest;

class WithdrawLogger
{
    public static function log(
        WithdrawRequest $withdraw,
        string $action,
        string $actorType,
        ?int $actorId = null,
        string $event =null,
        array $metadata = []
    ): void {
        $withdraw->logs()->create([
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
            'action'     => $action,
            'event'      =>$event,
            'metadata'   => $metadata,
        ]);
    }
}
