<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationRecourseLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['recourse_id', 'status', 'message', 'created_at'];

    public function recourse()
    {
        return $this->belongsTo(EvaluationRecourse::class, 'recourse_id');
    }
}
