<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'year',
        'type',
        'term_first',
        'term_end',
        'release',
        'release_data',
    ];

    protected $casts = [
    'release' => 'boolean',
    'release_data' => 'datetime',
    'term_first' => 'datetime',
    'term_end' => 'datetime',
];

    /**
     * Get all of the questions for the Form.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function groupQuestions(): HasMany
    {
        return $this->hasMany(GroupQuestion::class);
    }


    
}