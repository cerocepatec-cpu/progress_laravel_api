<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAccount extends Model
{
    protected $table = 'transactions_accounts';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'amount' => 'float',
            'date_transaction' => 'datetime',
        ];
    }
}

