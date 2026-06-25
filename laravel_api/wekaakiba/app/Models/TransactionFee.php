<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TransactionFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'enterprise_id',
        'setby',
        'money_id',
        'min_amount',
        'max_amount',
        'withdraw_percent',
        'send_percent',
    ];

    // 🔗 Relations
    public function enterprise()
    {
        return $this->belongsTo(Enterprises::class);
    }

    public function setbyUser()
    {
        return $this->belongsTo(User::class, 'setby');
    }

    public function money()
    {
        return $this->belongsTo(moneys::class);
    }

    /**
     * Retourne le pourcentage applicable selon le montant, la devise et le type
     *
     * @param float $amount
     * @param int $money_id
     * @param string $type 'withdraw' ou 'send'
     * @return float|null
     */
   public static function calculateFee(
    float $amount,
    int $money_id,
    string $type = 'withdraw'
    ): array {
        $user = Auth::user();

        // 🔐 Sécurité minimale
        if (!$user) {
            return [
                'percent' => 0,
                'fee'     => 0,
            ];
        }

        // 🏢 Entreprise
        $enterprise_id = app(\App\Http\Controllers\Controller::class)
            ->getEse($user->id)['id'] ?? null;

        if (!$enterprise_id) {
            return [
                'percent' => 0,
                'fee'     => 0,
            ];
        }

        // 💰 Recherche tranche applicable
        $fee = self::where('money_id', $money_id)
            ->where('enterprise_id', $enterprise_id)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                    ->orWhereNull('max_amount');
            })
            ->orderBy('min_amount', 'desc') // 🔥 important si plusieurs tranches
            ->first();

        // ❌ Aucune tranche → 0 frais
        if (!$fee) {
            return [
                'percent' => 0,
                'fee'     => 0,
            ];
        }

        // 📊 Pourcentage selon le type
        $percent = match ($type) {
            'send'     => (float) $fee->send_percent,
            'withdraw' => (float) $fee->withdraw_percent,
            default    => 0,
        };

        $feeAmount = round(($percent / 100) * $amount, 2);

        return [
            'percent' => $percent,
            'fee'     => $feeAmount,
        ];
    }

}
