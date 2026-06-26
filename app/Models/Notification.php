<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'id_notification';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'id_notification' => 'integer',
        'date_notification' => 'datetime',
    ];
}