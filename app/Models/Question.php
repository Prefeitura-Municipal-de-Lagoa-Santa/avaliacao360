<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    use HasFactory, LogsActivity;

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

    /**
     * Accessor para manter compatibilidade com código existente.
     */
    public function getTextAttribute()
    {
        return $this->text_content;
    }
}