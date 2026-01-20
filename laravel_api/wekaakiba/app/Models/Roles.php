<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'name',
        'description',
        'user_id',
        'enterprise_id',
        'guard_name'
    ];
}
