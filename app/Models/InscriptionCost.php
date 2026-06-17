<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InscriptionCost extends Model
{
    protected $table = 'inscription_cost';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}

