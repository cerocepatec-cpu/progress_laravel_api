<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function push(string|int $memberId, string $message, string $status = 'unread'): Notification
    {
        $notification = Notification::create([
            'message_notified' => $message,
            'member_id' => (string) $memberId,
            'date_notification' => now(),
            'status_notification' => $status,
        ]);

        Log::error('NOTIFICATION INSERTED', [
            'database' => DB::connection()->getDatabaseName(),
            'id' => $notification->id_notification,
        ]);

        return $notification;
    }
}
