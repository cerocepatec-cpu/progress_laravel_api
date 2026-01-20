<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class moneys extends Model
{
    use HasFactory;
    protected $fillable = [
        'abreviation',
        'principal',
        'money_name',
        'enterprise_id'
    ];

    protected $casts = [
        'billages' => 'array', // permet d'accéder directement sous forme de tableau
    ];

    public function bonuses()
    {
        return $this->hasMany(AgentBonus::class, 'currency_id');
    }

}
