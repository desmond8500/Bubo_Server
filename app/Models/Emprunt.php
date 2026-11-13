<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Emprunt extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'description', 'montant', 'date'];

    protected $hidden = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'montant' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }
}
