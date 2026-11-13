<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepensePersonnelle extends Model
{
    use SoftDeletes;

    protected $table = 'depenses_personnelles';

    protected $fillable = ['uuid', 'designation', 'categorie_uuid', 'quantite', 'montant', 'mode_paiement', 'date'];

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
