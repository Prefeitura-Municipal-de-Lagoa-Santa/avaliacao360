<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class EvaluationRecourseLog extends Model
{
    use LogsActivity;
    const UPDATED_AT = null; // ✅ desativa só updated_at

    protected $fillable = ['recourse_id', 'status', 'message'];

    public function recourse()
    {
        return $this->belongsTo(EvaluationRecourse::class, 'recourse_id');
    }
}
