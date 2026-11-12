<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'nom', 'prix', 'depense_le', 'marque_uuid', 'fournisseur_uuid'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'prix' => 'float',
            'depense_le' => 'date:Y-m-d',
        ];
    }
}
