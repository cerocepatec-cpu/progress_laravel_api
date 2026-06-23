<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $table = 'cities';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $fillable = [
        'country_id',
        'name',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'country_id' => 'integer',
        ];
    }
}
