<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $identifiers = $this->notificationIdentifiers($request);

        $items = DB::table('notifications')
            ->whereIn('member_id', $identifiers)
            ->when(! $request->boolean('include_read'), function ($query): void {
                $query->where('status_notification', '<>', 'read');
            })
            ->orderByDesc('id_notification')
            ->limit((int) $request->input('limit', 100))
            ->get();

        return $this->ok(NotificationResource::collection($items));
    }

    public function markAsRead(Request $request, int $notification)
    {
        DB::table('notifications')
            ->where('id_notification', $notification)
            ->whereIn('member_id', $this->notificationIdentifiers($request))
            ->update([
                'status_notification' => 'read',
            ]);

        return $this->ok(null, 'Notification marquee comme lue.');
    }

    private function notificationIdentifiers(Request $request): array
    {
        return array_values(array_filter(array_unique([
            (string) $request->user()->member_id,
            (string) $request->user()->member_code,
            (string) $request->user()->username,
        ]), fn (?string $value): bool => $value !== null && $value !== ''));
    }
}
