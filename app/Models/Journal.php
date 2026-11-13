<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Journal d'activité : qui a fait quoi, et quand.
 * Les données privées (revenus, emprunts, dépenses personnelles) sont journalisées sans leur contenu.
 */
class Journal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal';

    protected $fillable = ['user_id', 'action', 'entity', 'entity_uuid', 'libelle', 'details', 'ip'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function enregistrer(
        ?User $user,
        string $action,
        ?string $entity = null,
        ?string $uuid = null,
        ?string $libelle = null,
        ?array $details = null,
    ): void {
        static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_uuid' => $uuid,
            'libelle' => $libelle !== null ? Str::limit($libelle, 255, '…') : null,
            'details' => $details,
            'ip' => request()?->ip(),
        ]);
    }
}
