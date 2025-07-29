<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdiAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdi_request_id',
        'question_id',
        'response_content',
    ];

    public function pdiRequest()
    {
        return $this->belongsTo(PdiRequest::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
