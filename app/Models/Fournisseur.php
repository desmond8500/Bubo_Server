<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    protected $table = 'fournisseurs';

    protected $fillable = ['uuid', 'nom', 'contact'];

    protected $hidden = ['id'];
}
