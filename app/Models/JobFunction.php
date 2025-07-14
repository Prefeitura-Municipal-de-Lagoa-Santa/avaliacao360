<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    protected $fillable = [
        'name',
        'type',
    ];

    public function people()
    {
        return $this->hasMany(Person::class);
    }
}
