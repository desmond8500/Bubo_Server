<?php

namespace App\Models;

use App\Models\Concerns\HasAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Crédit du budget commun (donnée commune, visible par tous).
 * Le premier crédit est le solde de départ ; les suivants alimentent le budget au fil du temps.
 */
class Budget extends Model
{
    use HasAuteur, SoftDeletes;

    protected $fillable = ['uuid', 'description', 'montant', 'date'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'montant' => 'float',
            'date' => 'date:Y-m-d',
        ];
    }
}
