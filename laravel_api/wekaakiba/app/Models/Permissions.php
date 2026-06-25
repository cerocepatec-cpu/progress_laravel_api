<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';
    protected $visible = ['id', 'name'];

    protected $fillable = [
        'name',          // requis Spatie
        'guard_name',    // requis Spatie
        'description',   // champ métier
        'enterprise_id', // champ métier
        'user_id',       // créateur
    ];
}