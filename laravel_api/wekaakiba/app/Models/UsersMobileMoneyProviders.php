<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersMobileMoneyProviders extends Model
{
    use HasFactory;
     protected $fillable = [
        'user_id',
        'mobile_money_provider_id',
        'phone_number',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(MobileMoneyProviders::class, 'mobile_money_provider_id');
    }
}
