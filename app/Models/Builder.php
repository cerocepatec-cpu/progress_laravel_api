<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Builder extends Model
{
    protected $table = 'builder';

    protected $primaryKey = 'id_constructor';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_constructor' => 'integer',
            'parent_code' => 'integer',
        ];
    }
}

