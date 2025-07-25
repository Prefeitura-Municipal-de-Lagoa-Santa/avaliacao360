<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRecourse extends Model
{
    protected $fillable = [
        'evaluation_id',
        'person_id',
        'user_id',
        'text',
        'status',
        'response',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
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

    public function logs()
    {
        return $this->hasMany(EvaluationRecourseLog::class, 'recourse_id');
    }

}
