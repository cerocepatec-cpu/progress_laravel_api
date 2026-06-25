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
        'two_factor_enabled',
        'two_factor_channel',
        'phone_verified_at',
        'timezone'
    ];

    /**
     * Champs cachés à la sérialisation
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone_verified_at',
        'email_verified_at',
        'laravel_through_key',
        'pin'
    ];

    /**
     * Casts
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_enabled'=>'boolean',
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

    public function enterprises()
    {
        return $this->belongsToMany(
            Enterprises::class,
            'usersenterprises',
            'user_id',
            'enterprise_id'
        );
    }

    public function usersenterprise()
    {
        return $this->hasMany(UsersEnterprise::class, 'user_id', 'id');
    }

   public function mobileMoneyProviders()
    {
        return $this->belongsToMany(
            MobileMoneyProviders::class,
            'users_mobile_money_providers',
            'user_id',
            'mobile_money_provider_id'
        )
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
    
   public static function allCollectorsFromEnterprise(int $enterpriseId): array
    {
        return self::query()
            ->whereIn(
                'id',
                usersenterprise::where('enterprise_id', $enterpriseId)
                    ->pluck('user_id')
            )
            ->where('collector',true)
            ->where('status', 'enabled')
            ->pluck('id')
            ->toArray();
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function currentSession(): ?UserSession
    {
        return $this->sessions()
            ->where('status', 'active')
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->first();
    }

    public function currentSessionByDevice(string $deviceType): ?UserSession
    {
        return $this->sessions()
            ->where('device_type', $deviceType)
            ->where('status', 'active')
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->first();
    }

    public function preferences()
    {
        return $this->hasOne(\App\Models\UserPreference::class);
    }

    public function conversations()
    {
        return $this->hasMany(ConversationParticipant::class);
    }


}
