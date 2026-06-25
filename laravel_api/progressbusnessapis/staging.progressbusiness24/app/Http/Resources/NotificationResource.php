<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_notification,
            'message' => $this->message_notified,
            'member_id' => $this->member_id,
            'status' => $this->status_notification,
            'date_notification' => $this->date_notification instanceof \DateTimeInterface
                ? $this->date_notification->format('Y-m-d H:i:s')
                : (string) $this->date_notification,
        ];
    }
}
