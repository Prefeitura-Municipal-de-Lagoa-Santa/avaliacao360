<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class User extends Authenticatable implements LdapAuthenticatable
{
    use HasFactory, Notifiable, AuthenticatesWithLdap;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        // --- CAMPOS MESCLADOS PARA O UPLOAD DE CSV ---
        'registration_number',
        'bond_type',
        'functional_status',
        'cpf',
        'rg_number',
        'admission_date',
        'dismissal_date',
        'current_position',
        'current_function',
        'allocation_code',
        'allocation_name',
        // --- FIM DA MESCLAGEM ---
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // --- CASTS MESCLADOS PARA DATAS ---
            'admission_date' => 'date',
            'dismissal_date' => 'date',
            // --- FIM DA MESCLAGEM ---
        ];
    }
}
