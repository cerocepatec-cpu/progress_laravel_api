<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMouvement extends Model
{
    protected $table = 'stockmouvements';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'deposit_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'float',
            'price' => 'float',
            'total' => 'float',
            'done_at' => 'datetime',
            'expiration_date' => 'date',
            'motif' => 'string',
            'stock_before' => 'float',
            'sold' => 'float',
        ];
    }
}

