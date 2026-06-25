<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wekaAccountsTransactions extends Model
{
    use HasFactory;
    protected $fillable=[
    'amount',
    'sold_before',
    'sold_after',
    'type',
    'motif',
    'user_id',
    'member_account_id',
    'member_id',
    'enterprise_id',
    'done_at',
    'account_id',
    'operation_done_by',
    'uuid',
    'fees',
    'transaction_status',
    'sync_status',
    'phone',
    'adresse',
    'imputed_to',	
	'imputed_at',	
	'imputed_by',
    'fund_id',
    'from_to_id',
    'sent_to_id'
    ];

    public function memberAccount()
    {
        return $this->belongsTo(wekamemberaccounts::class, 'member_account_id');
    }

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
