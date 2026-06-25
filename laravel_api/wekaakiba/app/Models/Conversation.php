<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;
       protected $fillable = [
        'type',
        'title',
        'status',
        'locked_at',
        'closed_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function participants() {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages() {
        return $this->hasMany(Message::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot([
                'role',
                'last_read_at',
                'notifications_enabled',
                'muted',
                'muted_until',
                'pinned',
                'pinned_at',
                'archived',
                'archived_at',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

}
