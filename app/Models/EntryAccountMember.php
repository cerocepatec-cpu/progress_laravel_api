<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryAccountMember extends Model
{
    protected $table = 'entry_account_member';

    protected $primaryKey = 'id_entry';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_entry' => 'integer',
            'amount' => 'float',
            'date_entry' => 'datetime',
        ];
    }
}

