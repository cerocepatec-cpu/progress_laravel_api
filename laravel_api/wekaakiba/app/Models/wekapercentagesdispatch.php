<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wekapercentagesdispatch extends Model
{
    use HasFactory;
    protected $table='wekapercentagesdispatch';
    protected $fillable=[
        'sponsor'
    ];
}
