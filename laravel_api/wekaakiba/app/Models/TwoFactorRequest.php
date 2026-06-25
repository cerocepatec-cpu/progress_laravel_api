<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFactorRequest extends Model
{
    use HasFactory;
    
     protected $fillable = [
        'user_id',
        'challenge_id',
        'token',
        'status',

        'device',
        'browser',
        'ip_address',
        'city',
        'country',

        'approved_ip',
        'approved_user_agent',

        'expires_at',
        'approved_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'approved_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
