<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Closure extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',           // Utilisateur qui fait la clôture
        'fund_id',           // La caisse concernée
        'total_amount',      // Total calculé après billetage
        'billages',          // JSON des billets et quantités
        'currency_id',       // Monnaie de la caisse
        'status',            // pending, validated, rejected
        'closed_at',         // Date de clôture
        'received_amount',   // Montant reçu par celui qui valide/receptionne
        'received_at',       // Date de perception/reception
        'closure_note',      // Description ou note de celui qui cloture
        'receiver_note',     // Description ou note de celui qui réceptionne
    ];

    protected $casts = [
        'billages' => 'array',
        'closed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    // Relations
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function fund() {
        return $this->belongsTo(funds::class);
    }

    public function currency() {
        return $this->belongsTo(moneys::class);
    }
}
