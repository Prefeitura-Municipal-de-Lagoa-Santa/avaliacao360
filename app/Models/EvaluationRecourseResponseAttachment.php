<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class EvaluationRecourseResponseAttachment extends Model
{
    use LogsActivity;
    protected $fillable = ['recourse_id', 'file_path', 'original_name'];

    public function recourse()
    {
        return $this->belongsTo(EvaluationRecourse::class, 'recourse_id');
    }
}
