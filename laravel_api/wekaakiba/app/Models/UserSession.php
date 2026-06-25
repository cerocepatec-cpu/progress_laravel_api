<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;
      protected $fillable = [
        'user_id',
        'device_type',
        'device_name',
        'ip_address',
        'user_agent',
        'access_token_id',
        'status',
        'revoked_at'
    ];

    protected $casts = [
        'revoked_at' => 'datetime'
    ];

     public function token()
    {
        return $this->belongsTo(
            \Laravel\Sanctum\PersonalAccessToken::class,
            'access_token_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
