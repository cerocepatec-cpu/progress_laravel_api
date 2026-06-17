<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'member_code' => $this->member_code,
            'user_id' => $this->whenLoaded('user', fn () => $this->user?->id),
            'member_id' => $this->member_id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'pseudo' => $this->pseudo,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'gender' => $this->gender,
            'username' => $this->username,
            'categorie_id' => $this->categorie_id,
            'categorie_name' => $this->whenLoaded('category', fn () => $this->category?->categorie_name),
            'parent_code' => $this->parent_code,
            'sponsor_code' => $this->sponsor_code,
            'e_mobile_number' => $this->e_mobile_number,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'total_amount_e_wallet' => (float) $this->total_amount_e_wallet,
            'inscription_mode' => $this->inscription_mode,
            'member_statute' => $this->member_statute,
            'actual_level' => (int) $this->actual_level,
            'pdfpaquet' => (float) ($this->pdfpaquet ?? 0),
            'adress' => $this->adress,
            'city' => $this->city,
            'registered_at' => $this->date?->toDateTimeString(),
            'last_connection' => optional($this->last_connection)->format('Y-m-d H:i:s')
        ];
    }
}

