<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class LegacyPassword
{
    public function isHashed(?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        return preg_match('/^\$2[aby]\$/', $stored) === 1 || str_starts_with($stored, '$argon');
    }

    public function check(string $plain, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        if ($this->isHashed($stored)) {
            return Hash::check($plain, $stored);
        }

        return hash_equals((string) $stored, $plain);
    }

    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public function hashForStorage(?string $value, ?string $fallback = null): string
    {
        $value = (string) ($value ?? $fallback ?? '');

        if ($value === '') {
            return '';
        }

        if ($this->isHashed($value)) {
            return $value;
        }

        return $this->hash($value);
    }
}
