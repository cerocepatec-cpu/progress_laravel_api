<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class LegacyPassword
{
    public function check(string $plain, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if (preg_match('/^\$2[aby]\$/', $stored) === 1 || str_starts_with($stored, '$argon')) {
            return Hash::check($plain, $stored);
        }

        return hash_equals((string) $stored, $plain);
    }
}
