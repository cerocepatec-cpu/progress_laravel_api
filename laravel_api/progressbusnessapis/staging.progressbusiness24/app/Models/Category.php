<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';

    protected $primaryKey = 'categorie_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];
}
