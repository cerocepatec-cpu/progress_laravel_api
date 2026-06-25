<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\UserSession;
use Illuminate\Support\Facades\Cache;

class UserSessionService
{
    public static function hasActiveSession(int $userId, string $deviceType): bool
    {
        $ttlMinutes = $deviceType === 'mobile' ? 2 : 1;

        return UserSession::where('user_id', $userId)
            ->where('device_type', $deviceType)
            ->where('status', 'active')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes($ttlMinutes))
            ->exists();
    }

     public static function hasRealActiveSession(int $userId, string $deviceType,string $deviceIp): bool
    {
        $heartbeatKey = "heartbeat:user:$userId:$deviceType";
        $hasHeartbeat = Cache::has($heartbeatKey);

        if (!$hasHeartbeat) {
            UserSession::where('user_id', $userId)
                ->where('device_type', $deviceType)
                ->where('ip_address', $deviceIp)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        }

        return UserSession::where('user_id', $userId)
            ->where('device_type', $deviceType)
            ->where('ip_address', $deviceIp)
            ->where('status', 'active')
            ->exists()
            && $hasHeartbeat;
    }

}
