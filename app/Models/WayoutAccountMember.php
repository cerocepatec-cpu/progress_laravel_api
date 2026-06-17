<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WayoutAccountMember extends Model
{
    protected $table = 'wayout_account_member';

    protected $primaryKey = 'id_wayout';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_wayout' => 'integer',
            'amount' => 'float',
            'date_wayout' => 'datetime',
        ];
    }
}

