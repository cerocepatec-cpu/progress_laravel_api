<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorCrowned extends Model
{
    protected $table = 'ambassador_crowned';

    protected $primaryKey = 'id_plan';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_plan' => 'integer',
            'parent_code' => 'integer',
            'member_code' => 'integer',
        ];
    }
}

