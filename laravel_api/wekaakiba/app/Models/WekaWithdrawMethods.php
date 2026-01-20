<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WekaWithdrawMethods extends Model
{
    use HasFactory;
    protected $fillable = [
        'method_name',
        'description',
        'status',
        'metadata',
        'enterprise_id'
    ];
}
