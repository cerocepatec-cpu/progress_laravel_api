<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawRequestOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdraw_request_id',
        'target',
        'otp_hash',
        'expires_at',
        'validated',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'validated'  => 'boolean',
    ];

    public function withdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class);
    }

    /* =====================================================
     |  HELPERS OTP
     |=====================================================*/

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function validateOtp(string $plainOtp): bool
    {
        if ($this->validated || $this->isExpired()) {
            return false;
        }

        if (!password_verify($plainOtp, $this->otp_hash)) {
            return false;
        }

        $this->update(['validated' => true]);

        return true;
    }
}
