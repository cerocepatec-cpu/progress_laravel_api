<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sapphire extends Model
{
    protected $table = 'sapphire';

    protected $primaryKey = 'id_sapphire';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_sapphire' => 'integer',
            'parent_code' => 'integer',
            'member_code' => 'integer',
        ];
    }
}

