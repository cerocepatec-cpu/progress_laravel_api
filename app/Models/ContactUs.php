<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactUs extends Model
{
    use HasFactory;

    /**
     * Nom de la table.
     */
    protected $table = 'contact_us';

    /**
     * Clé primaire.
     */
    protected $primaryKey = 'id';

    /**
     * Incrémentation.
     */
    public $incrementing = true;

    /**
     * Type de la clé.
     */
    protected $keyType = 'int';

    /**
     * Gestion des timestamps Laravel.
     */
    public $timestamps = true;

    /**
     * Champs autorisés en insertion massive.
     */
    protected $fillable = [
        'contact_names',
        'contact_email',
        'contact_phone',
        'business_type',
        'contact_message',
        'contact_date',
    ];

    /**
     * Conversion automatique des dates.
     */
    protected $casts = [
        'contact_date' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}