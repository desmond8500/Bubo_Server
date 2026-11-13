<?php

namespace App\Models;

use App\Models\Concerns\HasAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Catégorie de dépense (donnée commune : partagée par les dépenses générales et personnelles). */
class Categorie extends Model
{
    use HasAuteur, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = ['uuid', 'nom', 'description'];

    protected $hidden = ['id'];
}
