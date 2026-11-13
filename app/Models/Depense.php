<?php

namespace App\Models;

use App\Models\Concerns\HasAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Depense extends Model
{
    use HasAuteur, SoftDeletes;

    protected $fillable = ['uuid', 'article_uuid', 'categorie_uuid', 'quantite', 'montant', 'mode_paiement', 'date'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'quantite' => 'float',
            'montant' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }
}
