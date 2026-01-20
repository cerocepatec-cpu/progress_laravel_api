<?php
// app/Events/UserRealtimeNotification.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRealtimeNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $userId;
    public $title;
    public $message;
    public $level;

    public function __construct($userId, $title, $message, $level = 'info')
    {
        $this->userId  = $userId;
        $this->title   = $title;
        $this->message = $message;
        $this->level   = $level;
    }

    public function broadcastOn()
    {
        return new Channel('user-notifications');
    }

    public function broadcastAs()
    {
        return 'user.notification';
    }

    public function broadcastWith()
    {
        return [
            'userId'  => $this->userId,
            'title'   => $this->title,
            'message' => $this->message,
            'level'   => $this->level,
        ];
    }
}
