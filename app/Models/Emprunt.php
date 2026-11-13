<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Emprunt extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid', 'description', 'montant', 'mode_paiement', 'date', 'rembourse', 'date_remboursement'];

    protected $hidden = ['id', 'user_id'];

    /** Cohérence : pas de date de remboursement sans remboursement ; sinon la date du jour par défaut. */
    protected static function booted(): void
    {
        static::saving(function (self $emprunt) {
            if (! $emprunt->rembourse) {
                $emprunt->date_remboursement = null;
            } elseif (! $emprunt->date_remboursement) {
                $emprunt->date_remboursement = now()->toDateString();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'montant' => 'float',
            'date' => 'date:Y-m-d',
            'rembourse' => 'boolean',
            'date_remboursement' => 'date:Y-m-d',
        ];
    }
}
