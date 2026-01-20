<?php

namespace App\Models;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\MobileMoneyProviders;

class User extends Authenticatable implements CanResetPassword
{
    use HasFactory, HasApiTokens, Notifiable, HasRoles;

    /**
     * Champs remplissables
     */
    protected $fillable = [
        'name',
        'user_name',
        'email',
        'email_verified_at',
        'user_phone',
        'password',
        'user_type',
        'status',
        'note',
        'avatar',
        'uuid',
        'full_name',
        'pin',
        'collector',
        'sponsored_by',
        'collection_percentage',
        'mobile_access',
        'can_withdraw_on_mobile',
        'can_withdraw_by_agent',
        'adress',
    ];

    /**
     * Champs cachés à la sérialisation
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'laravel_through_key',
        'pin',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Attributs calculés ajoutés automatiquement à JSON
     */
    protected $appends = ['pin_set', 'weak_pin'];
    protected $guard_name = 'sanctum';

    public function getPinSetAttribute(): bool
    {
        $pin = $this->attributes['pin'] ?? null;
        return !empty($pin) && strlen($pin) > 20;
    }

    /**
     * Indique si le PIN est potentiellement faible
     */
    public function getWeakPinAttribute(): bool
    {
        $pin = $this->attributes['pin'] ?? null;

        if (empty($pin)) {
            return false;
        }
        return strlen($pin) < 30;
    }

    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function usersenterprise()
    {
        return $this->hasMany(UsersEnterprise::class, 'user_id', 'id');
    }

    public function mobileMoneyProviders()
    {
        return $this->belongsToMany(MobileMoneyProviders::class, 'users_mobile_money_providers')
            ->withPivot('phone_number', 'status')
            ->withTimestamps();
    }

    public function getMobileMoneyProviderConfigDetails(string $telecom)
    {
        return $this->mobileMoneyProviderConfigs()
            ->whereHas('provider', function ($query) use ($telecom) {
                $query->where('provider', $telecom);
            })
            ->with('provider')
            ->first();
    }

    public static function getUserBy($keyword, $criteria)
    {
        return self::where($criteria, $keyword)->first();
    }

    public function requests()
    {
        return $this->hasMany(requests::class);
    }

    public static function findByIdentifier($login)
    {
        return self::where(function ($query) use ($login) {
                $query->where('user_name', $login)
                      ->orWhere('user_mail', $login);
            })
            ->where('status', 'enabled')
            ->first();
    }

    public function getSelectedFields(array $fields = [])
    {
        $defaultFields = ['id', 'user_name','name', 'full_name','email', 'user_mail', 'user_phone', 'status', 'uuid', 'avatar', 'user_type'];
        $selectedFields = array_unique(array_merge($defaultFields, $fields));
        return collect($this->attributesToArray())->only($selectedFields);
    }

    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

     public function isavailable(): bool
    {
        if ($this->status !=='enabled') {
            return false;
        }else{
            return true;
        }
    }  
}
