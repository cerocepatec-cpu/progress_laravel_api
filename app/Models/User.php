<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $primaryKey = 'member_code';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'password_e_wallet',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'member_code' => 'integer',
            'date' => 'datetime',
            'last_connection' => 'datetime',
            'categorie_id' => 'integer',
            'parent_code' => 'integer',
            'sponsor_code' => 'integer',
            'total_amount_e_wallet' => 'double',
            'actual_level' => 'integer',
            'pdfpaquet' => 'double',
            'city' => 'integer',
            'email_verified_at' => 'datetime',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'categorie_id', 'categorie_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_code', 'member_code');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_code', 'member_code');
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password;
    }
}
