<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    use LogsActivity;
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
