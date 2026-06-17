<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositProduct extends Model
{
    protected $table = 'depositproducts';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'deposit_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'float',
            'quantity_sold' => 'float',
            'price_sold' => 'float',
            'point' => 'float',
            'stock_alert' => 'float',
            'price_gros' => 'float',
            'price_detail' => 'float',
        ];
    }
}

