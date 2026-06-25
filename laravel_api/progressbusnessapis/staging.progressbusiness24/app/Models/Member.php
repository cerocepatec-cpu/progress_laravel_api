<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $table = 'members';

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
            'member_code' => 'integer',
            'categorie_id' => 'integer',
            'parent_code' => 'integer',
            'sponsor_code' => 'integer',
            'total_amount_e_wallet' => 'float',
            'actual_level' => 'integer',
            'pdfpaquet' => 'float',
            'city' => 'integer',
            'date' => 'datetime',
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
