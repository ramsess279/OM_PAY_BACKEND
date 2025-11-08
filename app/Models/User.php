<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Champs remplissables en masse.
     */
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'code_pin',
        'role',
    ];

    /**
     * Champs cachés lors du retour JSON.
     */
    protected $hidden = [
        'code_pin',
        'remember_token',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'id' => 'string',
    ];
}
