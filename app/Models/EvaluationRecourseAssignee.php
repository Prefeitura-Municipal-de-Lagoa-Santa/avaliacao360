<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRecourseAssignee extends Model
{
    protected $fillable = [
        'recourse_id',
        'person_id', 
        'assigned_by',
        'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function recourse()
    {
        return $this->belongsTo(EvaluationRecourse::class, 'recourse_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(Person::class, 'assigned_by');
    }
}
