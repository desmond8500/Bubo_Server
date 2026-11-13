<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'article_uuid', 'quantite', 'montant', 'date'];

    protected $hidden = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'quantite' => 'float',
            'montant' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }
}
