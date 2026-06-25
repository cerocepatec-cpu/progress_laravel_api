<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    
    protected $table ='departments';

    protected $fillable = [
        'department_name',
        'description',
        'header_depart',
        'user_id',
        'enterprise_id'
    ];

    // Ajout d'un attribut calculé exposé en JSON
    protected $appends = ['nbrusers'];

    // Relation vers l'utilisateur qui a créé le département
    public function createdby()
    {
        return $this->belongsTo(User::class);
    }

    // Relation vers les demandes associées
    public function requests()
    {
        return $this->hasMany(Requests::class);
    }

    // Relation vers les affectations d'agents
    public function affectations()
    {
        return $this->hasMany(Affectation_users::class, 'department_id');
        // return $this->hasMany(affectation_users::class, 'department_id');
    }

    // Accessor pour compter les agents affectés
    public function getNbrUsersAttribute()
    {
        return $this->affectations()->count();
    }
}

