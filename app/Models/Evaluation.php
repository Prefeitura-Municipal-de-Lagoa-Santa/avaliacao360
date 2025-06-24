<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasFactory;

    // Apenas os campos que a avaliação representa
    protected $fillable = [
        'type',
        'form_id',
        'evaluated_person_id',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function evaluated(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'evaluated_person_id');
    }

    // O relacionamento com o avaliador foi removido daqui
    // e agora vive apenas em EvaluationRequest

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}