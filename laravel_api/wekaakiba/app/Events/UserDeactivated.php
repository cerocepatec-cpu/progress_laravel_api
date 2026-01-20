<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeactivated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function broadcastOn()
    { 
        return new Channel('user-status');
    }

    public function broadcastAs()
    {
        return 'user.deactivated';
    }

    public function broadcastWith()
    {
        return [
            'userId' => $this->userId
        ];
    }
}
