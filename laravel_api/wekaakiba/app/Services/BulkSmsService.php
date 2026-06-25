<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BulkSmsService
{
    public function send(array $messages)
    {
        $response = Http::withBasicAuth(
            config('services.bulksms.username'),
            config('services.bulksms.password')
        )
        ->withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->post(
            'https://api.bulksms.com/v1/messages',
            $messages,
            [
                'auto-unicode' => 'true',
                'longMessageMaxParts' => 30,
            ]
        );

        if ($response->failed()) {
            throw new \Exception(
                'BulkSMS error: ' . $response->body()
            );
        }

        return $response->json();
    }
}
