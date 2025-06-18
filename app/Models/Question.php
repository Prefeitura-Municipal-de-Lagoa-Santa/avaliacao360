<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory;

     protected $fillable = [
        'group_question_id', // Alterado de form_id
        'text_content',
        'weight',
    ];

    /**
     * A questão agora pertence a um GroupQuestion.
     */
    public function groupQuestion(): BelongsTo
    {
        return $this->belongsTo(GroupQuestion::class);
    }
}