<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoffretVip extends Model
{
    protected $table = 'coffrets_vip';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'member_id' => 'integer',
            'pu' => 'float',
            'total' => 'float',
            'number' => 'integer',
        ];
    }
}

