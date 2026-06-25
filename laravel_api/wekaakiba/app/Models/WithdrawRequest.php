<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawRequest extends Model
{
    use HasFactory;

    /* =====================================================
     |  MASS ASSIGNMENT
     |=====================================================*/

    protected $fillable = [
    // Acteurs
    'member_id',
    'collector_id',
    'member_account_id',
    'uuid',

    // Données retrait
    'amount',
    'fees',
    'money_id',
    'channel',

    // Historique soldes
    'sold_before',
    'sold_after',

    // Infos complémentaires
    'description',

    // Localisation
    'share_location',
    'latitude',
    'longitude',

    // Durée / validité
    'duration_type',
    'start_time',
    'end_time',

    // Workflow
    'status',
    'expires_at',
    'taken_at',
    'validated_at',
    'completed_at',
];


    /* =====================================================
     |  CASTS
     |=====================================================*/

    protected $casts = [
    'amount'        => 'decimal:2',
    'fees'          => 'decimal:2',
    'sold_before'   => 'decimal:2',
    'sold_after'    => 'decimal:2',

    'share_location'=> 'boolean',

    'expires_at'    => 'datetime',
    'taken_at'      => 'datetime',
    'validated_at'  => 'datetime',
    'completed_at'  => 'datetime',

    // ⚠️ correction ci-dessous
];


    /* =====================================================
     |  RELATIONS
     |=====================================================*/

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function money(): BelongsTo
    {
        return $this->belongsTo(moneys::class, 'money_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WithdrawRequestLog::class);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(WithdrawRequestOtp::class);
    }

    /* =====================================================
     |  SCOPES
     |=====================================================*/

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAvailable($query)
    {
        return $query
            ->pending()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeWithLocation($query)
    {
        return $query->where('share_location', true)
                     ->whereNotNull('latitude')
                     ->whereNotNull('longitude');
    }

    /* =====================================================
     |  HELPERS MÉTIER
     |=====================================================*/

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && now()->greaterThan($this->expires_at);
    }

    public function canBeTaken(): bool
    {
        return $this->status === 'pending'
            && !$this->isExpired();
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'taken';
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'validated';
    }

    /* =====================================================
     |  STATE CHANGERS
     |=====================================================*/

    public function markAsTaken(int $collectorId): void
    {
        $this->update([
            'collector_id' => $collectorId,
            'status'       => 'taken',
            'taken_at'     => now(),
        ]);
    }

    public function markAsValidated(): void
    {
        $this->update([
            'status'       => 'validated',
            'validated_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    public function memberAccount(): BelongsTo
{
    return $this->belongsTo(
        wekamemberaccounts::class,
        'member_account_id'
    );
}


}
