<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_manager'
    ];

    public function people()
    {
        return $this->hasMany(Person::class);
    }
}
