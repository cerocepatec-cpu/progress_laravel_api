<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WayoutAccountancy extends Model
{
    protected $table = 'wayout_accountancy';

    protected $primaryKey = 'id_entry_accountancy';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_entry_accountancy' => 'integer',
            'amount' => 'float',
            'date_wayout_accountancy' => 'datetime',
        ];
    }
}

