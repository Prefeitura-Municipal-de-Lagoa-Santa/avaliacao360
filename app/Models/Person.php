<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'bond_type',
        'functional_status',
        'cpf',
        'rg_number',
        'admission_date',
        'dismissal_date',
        'current_position',
        'current_function',
        'organizational_unit_id',
        'user_id', // Se você estiver atribuindo a usuários
    ];

    protected $casts = [
        'admission_date' => 'date',
        'dismissal_date' => 'date',
    ];

    /**
     * Get the organizational unit that owns the Person.
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    /**
     * Get the user that owns the Person (if applicable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

