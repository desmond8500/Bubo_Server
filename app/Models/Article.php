<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'nom', 'prix', 'description', 'images', 'marque_uuid', 'fournisseur_uuid'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'prix' => 'float',
            'images' => 'array',
        ];
    }
}
