<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodicMajSetting extends Model
{
    protected $table = 'periodicmajsettings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'citie_id' => 'integer',
            'begin_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}

