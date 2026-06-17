<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashOperation extends Model
{
    protected $table = 'cash_operations';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cash_id' => 'integer',
            'cash_mount' => 'float',
            'member_code' => 'integer',
            'cash_date' => 'date',
        ];
    }
}

