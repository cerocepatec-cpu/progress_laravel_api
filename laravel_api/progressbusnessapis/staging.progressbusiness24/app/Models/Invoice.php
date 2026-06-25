<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'total' => 'float',
            'total_received' => 'float',
            'done_at' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
