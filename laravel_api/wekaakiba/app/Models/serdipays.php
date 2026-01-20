<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class serdipays extends Model
{
    use HasFactory;
    protected $fillable = [
        'token',
        'email',
        'password',
        'merchantCode',
        'c2b_fees',
        'b2c_fees',
        'b2b_fees',
        'ussd_fees',
        'additional_fees',
        'token_endpoint',
        'merchant_payment_endpoint',
        'client_payment_endpoint',
        'merchant_pin',
        'merchant_api_id',
        'withdraw_by_agent_fees',
        'transfert_money_fees',
        'enterprise_id',
        'mode'
    ];

    public static function configFor($mode = 'test')
    {
        $config = self::where('mode', $mode)->first();

        if (!$config && $mode !== 'test') {
            $config = self::where('mode', 'test')->first();
        }

        if (!$config) {
            throw new \Exception("Aucune configuration SerdiPay trouvée. Veuillez configurer au moins un mode.");
        }

        return $config;
    }

    public static function valueExists(string $field, $value): bool
    {
        if (!in_array($field, (new self)->getFillable())) {
            throw new \Exception("Champ '$field' non autorisé ou inexistant dans SerdiPay.");
        }

        return self::where($field, $value)->exists();
    }

    public function checkRequiredFields(array $fields): array
    {
        foreach ($fields as $field) {
            if (empty($this->$field)) {
                return [
                    'ok'    => false,
                    'field' => $field,
                ];
            }
        }

        return [
            'ok'    => true,
            'field' => null,
        ];
    }

    public static function isCurrencyAllowed(string $currency):bool{
         return in_array($currency, ['CDF','USD']);
    }

}
