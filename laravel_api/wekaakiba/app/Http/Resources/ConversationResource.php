<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Message;
use App\Models\User;

class ConversationResource extends JsonResource
{
    protected $viewer;

    /**
     * Injecter l’utilisateur courant
     */
    public function forUser(User $user)
    {
        $this->viewer = $user;
        return $this;
    }

    public function toArray($request)
    {
        /** @var User $user */
        $user = $this->viewer ?? $request->user();

        // 🔹 Participant courant
        $participant = $this->participants
            ->firstWhere('user_id', $user->id);

        $lastReadAt = $participant?->last_read_at;

        // 🔢 Messages non lus
        $unreadCount = Message::where('conversation_id', $this->id)
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadAt, fn ($q) =>
                $q->where('created_at', '>', $lastReadAt)
            )
            ->count();

        // 🧠 Type
        $isGroup = $this->type === 'group';

        // 🧠 TITRE UX
        $title = match ($this->type) {

            'private' => optional(
                $this->participants
                    ->firstWhere('user_id', '!=', $user->id)
            )->user?->full_name
                ?? optional(
                    $this->participants
                        ->firstWhere('user_id', '!=', $user->id)
                )->user?->name,

            'group' => $this->title,

            'system' => $user->enterprises->first()?->name ?? 'Système',

            default => 'Conversation',
        };

        return [
            'id' => $this->id,
            'type' => $this->type,

            // ✅ NOUVEAU (clé pour le front)
            'is_group' => $isGroup,

            // 🧠 Titre calculé
            'title' => $title,

            // 🧑‍🤝‍🧑 NOUVEAU – nombre de participants
            'participants_count' => $isGroup
                ? $this->participants->count()
                : 2,

            // 👥 NOUVEAU – preview pour UI (WhatsApp-like)
            'participants_preview' => $isGroup
            ? $this->participants
                ->filter(fn ($p) => $p->user_id !== $user->id)
                ->map(fn ($p) => [
                    'id'   => $p->user->id,
                    'name' => $p->user->full_name ?? $p->user->name,
                ])
                ->take(3)
                ->values()
            : null,

            // 💬 Dernier message
            'last_message' => $this->messages->first(),

            // 🔔 Non lus
            'unread_count' => $unreadCount,
        ];
    }
}
