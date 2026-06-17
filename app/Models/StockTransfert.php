<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfert extends Model
{
    protected $table = 'stocktransferts';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'deposit_sender' => 'integer',
            'deposit_receiver' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'float',
            'quantity_received' => 'float',
            'received_at' => 'datetime',
        ];
    }
}

