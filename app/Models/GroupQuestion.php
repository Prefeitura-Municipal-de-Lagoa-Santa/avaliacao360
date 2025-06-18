<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'name',
        'weight',

    ];

    /**
     * Um grupo de questões pertence a um formulário.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Um grupo de questões tem muitas questões.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
