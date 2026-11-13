<?php

namespace App\Models;

use App\Models\Concerns\HasAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use HasAuteur, SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = ['uuid', 'nom', 'description', 'logo'];

    protected $hidden = ['id'];
}
