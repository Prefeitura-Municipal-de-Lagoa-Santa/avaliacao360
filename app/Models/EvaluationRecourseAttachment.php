<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class EvaluationRecourseAttachment extends Model
{
    use LogsActivity;
    protected $fillable = ['recourse_id', 'file_path', 'original_name', 'context'];

    public function recourse()
    {
        return $this->belongsTo(EvaluationRecourse::class, 'recourse_id');
    }
}

