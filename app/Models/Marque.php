<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marque extends Model
{
    protected $fillable = ['uuid', 'nom'];

    protected $hidden = ['id'];
}
