<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermutationMouvement extends Model
{
    protected $table = 'permutation_mouvements';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'id_permutation' => 'integer',
            'old_parent_code' => 'integer',
            'new_parent_code' => 'integer',
            'date_permutation' => 'datetime',
        ];
    }
}

