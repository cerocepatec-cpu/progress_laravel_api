<?php

namespace App\Events;

use Illuminate\Support\Facades\Redis;

class RedisTestEvent
{
    public $message;

    public function __construct($message = "Hello from Laravel Event!")
    {
        $this->message = $message;
    }

    public function send()
    {
        // Channel Redis de test
        $channel = 'test-channel';

        // Envoi direct dans Redis
        Redis::publish($channel, json_encode([
            'event' => 'redis.test',
            'message' => $this->message,
            'time' => now()->toDateTimeString(),
        ]));
    }
}
