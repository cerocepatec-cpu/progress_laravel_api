<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,read,unread'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $request->input('filter', 'all');
        $perPage = (int) $request->input('per_page', 15);

        $query = Notification::query()
            ->whereIn('member_id', $this->notificationIdentifiers($request))
            ->where('status_notification', '<>', 'read')
            ->orderByDesc('id_notification');

        if ($filter === 'read') {
            $query->where('status_notification', 'read');
        }

        if ($filter === 'unread') {
            $query->where('status_notification', '<>', 'read');
        }

        $notifications = $query->paginate($perPage);

        return $this->ok([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, int $notification)
    {
        Notification::query()
            ->where('id_notification', $notification)
            ->whereIn('member_id', $this->notificationIdentifiers($request))
            ->update([
                'status_notification' => 'read',
            ]);

        return $this->ok(null, 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request)
    {
        Notification::query()
            ->whereIn('member_id', $this->notificationIdentifiers($request))
            ->where('status_notification', '<>', 'read')
            ->update([
                'status_notification' => 'read',
            ]);

        return $this->ok(null, 'Toutes les notifications ont été marquées comme lues.');
    }

    private function notificationIdentifiers(Request $request): array
    {
        $user = $request->user();

        return array_values(array_filter(array_unique([
            (string) $user->member_id,
            (string) $user->member_code,
            (string) $user->username,
            (string) $user->email,
        ])));
    }
}
