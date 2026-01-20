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
    public static function calculateFee(float $amount, int $money_id, string $type = 'withdraw'): ?array
    {
        $user = Auth::user();
        if (!$user) {
            return null; // pas d'utilisateur connecté
        }

        // Récupère l'entreprise de l'utilisateur
        $enterprise_id = app(\App\Http\Controllers\Controller::class)->getEse($user->id)['id'] ?? null;
        if (!$enterprise_id) {
            return null; // pas d'enterprise trouvée
        }

        // Filtrage des tranches par devise et entreprise
        $fee = self::where('money_id', $money_id)
            ->where('enterprise_id', $enterprise_id)
            ->where('min_amount', '<=', $amount)
            ->where(function($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                    ->orWhereNull('max_amount'); // tranche illimitée
            })
            ->first();

        if (!$fee) {
            return null;
        }

        $percent = $type === 'send' ? $fee->send_percent : $fee->withdraw_percent;
        $feeAmount = ($percent / 100) * $amount;

        return [
            'percent' => $percent,
            'fee' => round($feeAmount, 2),
        ];
    }
}
