<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TransactionSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $userId;
    public $data;

    public function __construct($userId, $data)
    {
        $this->userId  = $userId;
        $this->data   = $data;
    }

    public function broadcastOn()
    {
        return new Channel('user-transactions');
    }

    public function broadcastAs()
    {
        return 'user.new-transaction';
    }

    public function broadcastWith()
    {
        return [
            'userId'  => $this->userId,
            'transaction'   => $this->data
        ];
    }
}
