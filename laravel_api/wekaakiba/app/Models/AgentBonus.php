<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBonus extends Model
{
    protected $fillable = [
    'agent_id',
    'transaction_id',
    'transaction_type',
    'currency_id',
    'amount',
    'status',
    'paid_at',
    'withdrawn_at',
    'month_key',
    'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    // Relations
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function transaction()
    {
        return $this->morphTo();  // si tu fais un système polymorphe sinon belongsTo simple
    }

    public function currency()
    {
        return $this->belongsTo(moneys::class, 'currency_id');
    }
}
