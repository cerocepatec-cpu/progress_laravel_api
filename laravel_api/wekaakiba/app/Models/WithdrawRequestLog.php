<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdraw_request_id',
        'actor_type',
        'actor_id',
        'action',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function withdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class);
    }
}
