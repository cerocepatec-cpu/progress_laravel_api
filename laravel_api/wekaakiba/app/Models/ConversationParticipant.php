<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationParticipant extends Model
{
    use HasFactory;

        public $timestamps = false;
    protected $fillable = [
        'conversation_id',
        'user_id',
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
    ];

    protected $casts = [
        'last_read_at'           => 'datetime',
        'muted_until'            => 'datetime',
        'pinned_at'              => 'datetime',
        'archived_at'            => 'datetime',
        'joined_at'              => 'datetime',
        'left_at'                => 'datetime',
        'notifications_enabled'  => 'boolean',
        'muted'                  => 'boolean',
        'pinned'                 => 'boolean',
        'archived'               => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function markAsReadAt(\Carbon\Carbon $time): void
    {
        $this->update([
            'last_read_at' => $time,
        ]);
    }

}
