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
        'current_instance',
        'stage',
        'workflow_stage',
        'last_returned_by_user_id',
        'last_returned_to_instance',
        'last_returned_at',
    'last_return_message',
        'response',
        'responded_at',
    // Comissão (decisão mascarada no status global)
    'commission_decision',
    'commission_response',
    'commission_decided_at',
    'clarification_response',
    'clarification_responded_at',
        // Extended workflow fields
        'dgp_decision',
        'dgp_decided_at',
        'dgp_notes',
        'first_ack_at',
        'is_second_instance',
        'second_instance_requested_at',
        'second_instance_text',
        'secretary_decision',
        'secretary_decided_at',
        'secretary_notes',
        'second_ack_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'last_returned_at' => 'datetime',
    'commission_decided_at' => 'datetime',
    'clarification_responded_at' => 'datetime',
        'dgp_decided_at' => 'datetime',
        'first_ack_at' => 'datetime',
        'is_second_instance' => 'boolean',
        'second_instance_requested_at' => 'datetime',
        'secretary_decided_at' => 'datetime',
        'second_ack_at' => 'datetime',
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

    public function lastReturnedBy()
    {
        return $this->belongsTo(User::class, 'last_returned_by_user_id');
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
