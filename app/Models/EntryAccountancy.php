<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryAccountancy extends Model
{
    protected $table = 'entries_accountancy';

    protected $primaryKey = 'id_entry_accountancy';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_entry_accountancy' => 'integer',
            'amount' => 'float',
            'date_entry_accountancy' => 'datetime',
            'point' => 'float',
            'used' => 'integer',
            'adhesion' => 'integer',
        ];
    }
}

