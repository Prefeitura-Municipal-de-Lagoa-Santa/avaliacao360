<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acknowledgment extends Model
{
    use LogsActivity;
    protected $fillable = [
        'person_id',
        'year',
        'signed_at',
        'signature_base64',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
