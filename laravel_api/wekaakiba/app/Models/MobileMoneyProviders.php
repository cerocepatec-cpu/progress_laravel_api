<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileMoneyProviders extends Model
{
    use HasFactory;
     protected $fillable = [
        'provider',
        'country',
        'name',
        'metadata',
        'status',
        'enterprise_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'users_mobile_money_providers')
            ->withPivot('phone_number', 'status')
            ->withTimestamps();
    }

    public function getSelectedFields(array $fields = [])
    {
        $defaultFields = ['id', 'provider', 'name'];
        $selectedFields = array_unique(array_merge($defaultFields, $fields));

        // On récupère les attributs normaux du modèle
        $data = collect($this->only($selectedFields));

        // On récupère le metadata bien formaté (tableau d'objets)
        $metadata = is_array($this->metadata) ? $this->metadata : json_decode($this->metadata ?? '[]', true);

        if (is_array($metadata)) {
            foreach ($metadata as $item) {
                // Pour logo avec path
                if (isset($item['key'], $item['path']) && $item['key'] === 'logo') {
                    $data->put('path', $item['path']);
                }

                // Pour d'autres champs dynamiques (ex: display_name, support_number, etc.)
                if (isset($item['key'], $item['value'])) {
                    $data->put($item['key'], $item['value']);
                }
            }
        }

        return $data;
    }

     public function isavailable(): bool
    {
        if ($this->status !== 'enabled') {
            return false;
        }else{
            return true;
        }
    }  
}
