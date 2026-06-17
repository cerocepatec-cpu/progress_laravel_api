<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidationMemberExpiration extends Model
{
    protected $table = 'validation_member_expiration';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'days_fixed' => 'integer',
        ];
    }
}

