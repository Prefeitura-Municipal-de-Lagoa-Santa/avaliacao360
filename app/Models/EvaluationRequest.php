<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'requester_person_id',
        'requested_person_id',
        'evidencias',
        'assinatura_base64',
        'status',
        'deleted_by',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    // ALTERADO: Relacionamento agora é com Person
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requester_person_id');
    }

    // ALTERADO: Relacionamento agora é com Person
    public function requested(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requested_person_id');
    }

    public function requestedPerson()
    {
        return $this->belongsTo(Person::class, 'requested_person_id');
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

}