<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LastSignOn extends Model
{
    protected $table = 'last_sign_on';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'member_code' => 'integer',
            'date_hour' => 'datetime',
        ];
    }
}

