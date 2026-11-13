<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Depense;
use App\Models\DepensePersonnelle;
use App\Models\Emprunt;
use App\Models\Fournisseur;
use App\Models\Marque;
use App\Models\Revenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public const MODELS = [
        'marques' => Marque::class,
        'fournisseurs' => Fournisseur::class,
        'articles' => Article::class,
        'depenses' => Depense::class,
        'depenses_personnelles' => DepensePersonnelle::class,
        'revenus' => Revenu::class,
        'emprunts' => Emprunt::class,
    ];

    /** Champs images : l'app envoie une data URL, le serveur renvoie l'URL publique du fichier. */
    private const IMAGE_FIELDS = [
        'marques' => ['logo'],
        'fournisseurs' => ['logo'],
        'articles' => ['images'],
    ];

    /** Reçoit les lignes créées hors ligne (upsert par uuid) et renvoie l'état complet du serveur. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($data, $userId) {
            foreach (self::MODELS as $key => $model) {
                $fillable = (new $model)->getFillable();

                foreach ($data[$key] ?? [] as $row) {
                    $existing = $model::withTrashed()->where('uuid', $row['uuid'])->first();

                    // Un uuid qui appartient à un autre utilisateur est ignoré.
                    if ($existing && (int) $existing->user_id !== $userId) {
                        continue;
                    }

                    if ($row['deleted'] ?? false) {
                        $existing?->delete();
                        continue;
                    }

                    // Une ligne déjà supprimée côté serveur le reste.
                    if ($existing?->trashed()) {
                        continue;
                    }

                    $attributes = Arr::only($row, $fillable);

                    foreach (self::IMAGE_FIELDS[$key] ?? [] as $field) {
                        if (array_key_exists($field, $attributes)) {
                            $attributes[$field] = $this->storeImages($attributes[$field]);
                        }
                    }

                    $record = $existing ?? new $model;
                    $record->fill($attributes);
                    $record->user_id = $userId;
                    $record->save();
                }
            }
        });

        return response()->json(
            collect(self::MODELS)->map(
                fn (string $model) => $model::where('user_id', $userId)->orderByDesc('id')->get()
            )
        );
    }

    private function rules(): array
    {
        $image = ['nullable', 'string', 'max:3000000'];

        return [
            ...$this->common('marques'),
            'marques.*.nom' => ['required', 'string', 'max:255'],
            'marques.*.description' => ['nullable', 'string'],
            'marques.*.logo' => $image,

            ...$this->common('fournisseurs'),
            'fournisseurs.*.nom' => ['required', 'string', 'max:255'],
            'fournisseurs.*.description' => ['nullable', 'string'],
            'fournisseurs.*.logo' => $image,

            ...$this->common('articles'),
            'articles.*.nom' => ['required', 'string', 'max:255'],
            'articles.*.prix' => ['required', 'numeric', 'min:0'],
            'articles.*.description' => ['nullable', 'string'],
            'articles.*.images' => ['nullable', 'array', 'max:5'],
            'articles.*.images.*' => ['string', 'max:3000000'],
            'articles.*.marque_uuid' => ['nullable', 'uuid'],
            'articles.*.fournisseur_uuid' => ['nullable', 'uuid'],

            ...$this->common('depenses'),
            'depenses.*.article_uuid' => ['required', 'uuid'],
            'depenses.*.quantite' => ['required', 'numeric', 'min:0'],
            'depenses.*.montant' => ['required', 'numeric', 'min:0'],
            'depenses.*.date' => ['required', 'date'],

            ...$this->common('depenses_personnelles'),
            'depenses_personnelles.*.designation' => ['required', 'string', 'max:255'],
            'depenses_personnelles.*.quantite' => ['required', 'numeric', 'min:0'],
            'depenses_personnelles.*.montant' => ['required', 'numeric', 'min:0'],
            'depenses_personnelles.*.date' => ['required', 'date'],

            ...$this->common('revenus'),
            'revenus.*.description' => ['required', 'string', 'max:255'],
            'revenus.*.montant' => ['required', 'numeric', 'min:0'],
            'revenus.*.date' => ['required', 'date'],

            ...$this->common('emprunts'),
            'emprunts.*.description' => ['required', 'string', 'max:255'],
            'emprunts.*.montant' => ['required', 'numeric', 'min:0'],
            'emprunts.*.date' => ['required', 'date'],
        ];
    }

    private function common(string $key): array
    {
        return [
            $key => ['sometimes', 'array'],
            "$key.*.uuid" => ['required', 'uuid'],
            "$key.*.deleted" => ['sometimes', 'boolean'],
        ];
    }

    private function storeImages(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => $this->storeImage($v), $value)));
        }

        return is_string($value) ? $this->storeImage($value) : null;
    }

    private function storeImage(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Image déjà hébergée : on conserve son URL.
        if (preg_match('#^https?://#', $value)) {
            return $value;
        }

        if (! preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#s', $value, $m)) {
            return null;
        }

        $binary = base64_decode($m[2], true);

        if ($binary === false || @getimagesizefromstring($binary) === false) {
            return null;
        }

        $extension = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $path = 'images/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        return Storage::disk('public')->url($path);
    }
}
