<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class funds extends Model
{
    use HasFactory;
    protected $fillable = [
        'sold',
        'description',
        'money_id',
        'user_id',
        'principal',
        'enterprise_id',
        'type',
        'fund_status'
    ];

     /**
     * Récupère les soldes totaux d'un utilisateur,
     * groupés par devise, avec l’abréviation de la monnaie.
     */
    public static function getUserBalancesGroupedByMoney(int $userId)
    {
        return self::query()
            ->join('moneys', 'funds.money_id', '=', 'moneys.id')
            ->select(
                'funds.money_id',
                'moneys.abreviation',
                DB::raw('SUM(funds.sold) as total_sold')
            )
            ->where('funds.user_id', $userId)
            ->groupBy('funds.money_id', 'moneys.abreviation')
            ->get();
    }

     public function isavailable(): bool
    {
        if ($this->fund_status !== 'enabled') {
            return false;
        }else{
            return true;
        }
    }  
    
    public function haveTheSameMoneyWith($moneyId): bool
    {
        if ($this->money_id !==$moneyId) {
            return false;
        }else{
            return true;
        }
    } 
    
    public function canMakeOperation($user): bool
    {
        if ($this->user_id !==$user->id) {
            return false;
        }else{
            return true;
        }
    }

    public static function getAutomaticFund(int $moneyId)
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $enterprise_id = app(\App\Http\Controllers\Controller::class)->getEse($user->id)['id'] ?? null;
        if (!$enterprise_id) {
            return null;
        }

        return self::where('type', 'automatic')
            ->where('enterprise_id', $enterprise_id)
            ->where('money_id', $moneyId)
            ->where('fund_status', 'enabled')
            ->first();
    }

    public static function myFundWithMoney($moneyId,$userId){
         return self::where('user_id', $userId)
        ->where('money_id', $moneyId)
        ->where('fund_status', 'enabled')
        ->first();
    }

    /**
     * Récupère toutes les caisses d'un utilisateur avec les infos de la monnaie.
     *
     * @param int $userId
     * @return \Illuminate\Support\Collection
     */
    public static function getUserFundsWithMoney(int $userId)
    {
        return self::query()
            ->join('moneys', 'funds.money_id', '=', 'moneys.id')
            ->select(
                'funds.id',
                'funds.description',
                'funds.sold',
                'funds.money_id',
                'moneys.abreviation',
                'moneys.name as money_name',
                'funds.fund_status',
                'funds.type'
            )
            ->where('funds.user_id', $userId)
            ->where('funds.fund_status', 'enabled')
            ->get();
    }

    public function enterprise()
    {
        return $this->belongsTo(Enterprises::class, 'enterprise_id');
    }

    public function currency()
    {
        return $this->belongsTo(moneys::class);
    }

}
