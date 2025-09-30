<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'evaluation_id',
        'requester_person_id',
        'requested_person_id',
        'evidencias',
        'assinatura_base64',
        'status',
        'deleted_by',
        'exception_date_first',
        'exception_date_end',
        'released_by',
        'invalidated_by',
        'invalidated_at',
        'invalidation_reason'
    ];

    protected $casts = [
        'invalidated_at' => 'datetime',
        'exception_date_first' => 'date',
        'exception_date_end' => 'date',
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
    public function releasedByUser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function invalidatedBy()
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

}