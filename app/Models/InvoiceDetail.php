<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    protected $table = 'invoice_details';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_id' => 'integer',
            'invoice_id' => 'integer',
            'deposit_id' => 'integer',
            'quantity' => 'float',
            'price' => 'float',
            'total' => 'float',
            'benefit' => 'float',
            'stock_history' => 'integer',
            'date_at' => 'date',
            'points' => 'float',
        ];
    }
}

