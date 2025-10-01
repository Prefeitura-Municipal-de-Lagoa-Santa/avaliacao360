<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class EvaluationRecourse extends Model
{
    use LogsActivity;
    protected $fillable = [
        'evaluation_id',
        'person_id',
        'user_id',
        'text',
        'status',
        'stage',
        'response',
        'responded_at',
        'commission_decision',
        'commission_response',
        'commission_decided_at',
        'director_decision',
        'director_response',
        'director_decided_at',
        'secretary_decision',
        'secretary_response',
        'secretary_decided_at',
        'ack_first_at',
        'ack_final_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'commission_decided_at' => 'datetime',
        'director_decided_at' => 'datetime',
        'secretary_decided_at' => 'datetime',
        'ack_first_at' => 'datetime',
        'ack_final_at' => 'datetime',
    ];

    public function evaluation()
    {
        return $this->belongsTo(EvaluationRequest::class, 'evaluation_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(EvaluationRecourseAttachment::class, 'recourse_id');
    }

    public function responseAttachments()
    {
        return $this->hasMany(EvaluationRecourseResponseAttachment::class, 'recourse_id');
    }

    public function assignees()
    {
        return $this->hasMany(EvaluationRecourseAssignee::class, 'recourse_id');
    }

    public function responsiblePersons()
    {
        return $this->belongsToMany(Person::class, 'evaluation_recourse_assignees', 'recourse_id', 'person_id')
                    ->withTimestamps()
                    ->withPivot('assigned_by', 'assigned_at');
    }

    public function logs()
    {
        return $this->hasMany(EvaluationRecourseLog::class, 'recourse_id');
    }

}
