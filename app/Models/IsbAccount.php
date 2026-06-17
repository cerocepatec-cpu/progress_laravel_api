<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsbAccount extends Model
{
    protected $table = 'isb_account';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'isb_code' => 'integer',
        ];
    }
}

