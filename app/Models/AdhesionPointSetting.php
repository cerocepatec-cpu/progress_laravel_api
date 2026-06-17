<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdhesionPointSetting extends Model
{
    protected $table = 'adhesionpointsettings';

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

