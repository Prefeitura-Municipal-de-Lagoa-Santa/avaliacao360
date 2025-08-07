<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Form extends Model
{
    use HasFactory, LogsActivity;

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
        'year' => 'string',
        'release' => 'boolean',
        'release_data' => 'datetime',
        'term_first' => 'datetime',
        'term_end' => 'datetime',
    ];

    public function groupQuestions(): HasMany
    {
        return $this->hasMany(GroupQuestion::class);
    }

    public function getYearFormattedAttribute(): string
    {
        if (is_numeric($this->year)) {
            return (string) $this->year;
        }

        try {
            return Carbon::parse($this->year)->format('Y');
        } catch (\Exception $e) {
            return '—';
        }
    }
}
