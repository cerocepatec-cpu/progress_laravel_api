<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerdipaysWebhookLog extends Model
{
    use HasFactory;

    protected $table = 'serdipays_webhook_logs';

    protected $fillable = [
        'merchantCode',
        'clientPhone',
        'amount',
        'currency',
        'telecom',
        'token',
        'sessionId',
        'sessionStatus',
        'transactionId',
        'wekatransactionId',
        'status',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'amount' => 'double',
        'sessionStatus' => 'integer',
        'status' => 'string',
    ];

    /**
     * Status helper checkers
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
