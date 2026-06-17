<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositUser extends Model
{
    protected $table = 'depositusers';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'deposit_id' => 'integer',
        ];
    }
}

