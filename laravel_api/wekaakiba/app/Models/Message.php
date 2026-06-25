<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'content',
        'status',
        'delivered_at',
        'seen_at',
        'client_uuid',
        'meta',
        'edited_at',
    ];

     public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    protected $casts = [
        'delivered_at' => 'datetime',
        'seen_at'      => 'datetime',
        'edited_at'    => 'datetime',
        'meta'         => 'array',
    ];

    public function sender() {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
