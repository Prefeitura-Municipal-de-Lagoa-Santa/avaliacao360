<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdiRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdi_id',
        'person_id',
        'manager_id',
        'status',
        'manager_signature_base64',
        'manager_signed_at',
        'person_signature_base64',
        'person_signed_at',
    ];

    protected $casts = [
        'manager_signed_at' => 'datetime',
        'person_signed_at' => 'datetime',
    ];

    public function pdi(): BelongsTo
    {
        return $this->belongsTo(Pdi::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'manager_id');
    }
     public function answers(): HasMany
    {
        return $this->hasMany(PdiAnswer::class);
    }
}