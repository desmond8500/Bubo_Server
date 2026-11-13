<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marque extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'nom', 'description', 'logo'];

    protected $hidden = ['id', 'user_id'];
}
