<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = ['uuid', 'nom', 'description', 'logo'];

    protected $hidden = ['id'];
}
