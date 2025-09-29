<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class configs extends Model
{
    use HasFactory;

    protected $fillable = [
        'gradesPeriod',
        'awarePeriod',
        'recoursePeriod',
        'year',
    ];

    // App\Models\Config.php

    public function estaNoPeriodoDeCiencia()
    {
        if (!$this->gradesPeriod || !isset($this->awarePeriod)) {
            return false;
        }
        $startDate = \Carbon\Carbon::parse($this->gradesPeriod);
        $endDate = \Carbon\Carbon::parse($this->gradesPeriod)->addDays($this->awarePeriod);
        $hoje = \Carbon\Carbon::now()->startOfDay();
        return $hoje->between($startDate, $endDate);
    }

}
