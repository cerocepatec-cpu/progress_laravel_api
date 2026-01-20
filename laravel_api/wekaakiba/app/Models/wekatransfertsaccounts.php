<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wekatransfertsaccounts extends Model
{
    use HasFactory;

        protected $fillable = [
            'enterprise',
            'done_by',
            'validated_by',
            'source',
            'destination',
            'source_currency_id',
            'destination_currency_id',
            'original_amount',
            'converted_amount',
            'conversion_rate',
            'pin',
            'transfert_status',
            'motif',
        ];

        protected $casts = [
            'original_amount' => 'decimal:2',
            'converted_amount' => 'decimal:2',
            'conversion_rate' => 'decimal:6',
        ];

        // Relations

        public function sourceAccount()
        {
            return $this->belongsTo(wekamemberaccounts::class, 'source');
        }

        public function destinationAccount()
        {
            return $this->belongsTo(Wekamemberaccounts::class, 'destination');
        }

        public function sourceCurrency()
        {
            return $this->belongsTo(moneys::class, 'source_currency_id');
        }

        public function destinationCurrency()
        {
            return $this->belongsTo(moneys::class, 'destination_currency_id');
        }

        public function enterprise()
        {
            return $this->belongsTo(Enterprises::class, 'enterprise');
        }

        public function doneBy()
        {
            return $this->belongsTo(User::class, 'done_by');
        }

        public function validatedBy()
        {
            return $this->belongsTo(User::class, 'validated_by');
        }
}
