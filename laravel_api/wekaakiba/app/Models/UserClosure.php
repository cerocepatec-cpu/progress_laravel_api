<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',         // Utilisateur qui fait la clôture
        'receiver_id',     // Utilisateur qui réceptionne
        'currency_id',     // Monnaie de la clôture
        'total_amount',    // Total des clôtures pour cette monnaie
        'total_received',  // Total reçu après réception
        'closure_count',   // Nombre de caisses clôturées
        'closure_date',    // Date de clôture (jour)
        'received_at',     // Date de réception
        'closure_note',    // Note de celui qui fait la clôture
        'receiver_note',   // Note de celui qui réceptionne
        'status',          // pending, validated, rejected
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_received' => 'decimal:2',
        'closure_date' => 'date',
        'received_at' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function currency()
    {
        return $this->belongsTo(moneys::class, 'currency_id');
    }

    public static function hasClosedForDate(int $userId, $date): bool
    {
        return self::where('user_id', $userId)
            ->whereDate('closure_date', $date)
            ->exists();
    }

}
