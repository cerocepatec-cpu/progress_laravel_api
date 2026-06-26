<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $message = (string) $this->message_notified;

        return [
            'id' => (int) $this->id_notification,
            'title' => $this->status_notification === 'read'
                ? 'Notification'
                : 'Nouvelle notification',
            'description' => $message,
            'message' => $message,
            'member_id' => (string) $this->member_id,
            'status' => (string) $this->status_notification,
            'read' => $this->status_notification === 'read',
            'date_notification' => optional($this->date_notification)->format('Y-m-d H:i:s'),
            'time' => optional($this->date_notification)->diffForHumans(),
            'type' => $this->resolveType($message),
        ];
    }

    private function resolveType(string $message): string
    {
        $message = mb_strtolower($message);

        if (
            str_contains($message, 'crédité') ||
            str_contains($message, 'déduit') ||
            str_contains($message, 'transféré') ||
            str_contains($message, 'transfert') ||
            str_contains($message, 'e-wallet') ||
            str_contains($message, 'compte')
        ) {
            return 'finance';
        }

        if (
            str_contains($message, 'nouveau membre') ||
            str_contains($message, 'filleul') ||
            str_contains($message, 'équipe progress business')
        ) {
            return 'member';
        }

        if (
            str_contains($message, 'commande') ||
            str_contains($message, 'achat')
        ) {
            return 'order';
        }

        return 'general';
    }
}