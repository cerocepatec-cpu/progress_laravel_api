<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MajPointSetting extends Model
{
    protected $table = 'majpointsettings';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'pointvalue' => 'float',
        ];
    }
}

