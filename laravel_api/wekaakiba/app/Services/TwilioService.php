<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    public function sendSms($phone, $body)
    {
        $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

        return $twilio->messages->create(
            $phone,
            [
                "from" => env("TWILIO_FROM"),
                "body" => $body
            ]
        );
    }
}
