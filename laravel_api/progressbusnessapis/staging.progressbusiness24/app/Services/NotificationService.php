<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function push(string|int $memberId, string $message, string $status = 'unread'): void
    {
        DB::table('notifications')->insert([
            'message_notified' => $message,
            'member_id' => (string) $memberId,
            'date_notification' => now(),
            'status_notification' => $status,
        ]);
    }
}
