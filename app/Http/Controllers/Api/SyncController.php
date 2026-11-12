<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Fournisseur;
use App\Models\Marque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    private const MODELS = [
        'marques' => Marque::class,
        'fournisseurs' => Fournisseur::class,
        'articles' => Article::class,
    ];

    /** Reçoit les lignes créées hors ligne (upsert par uuid) et renvoie l'état complet du serveur. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marques' => ['array'],
            'marques.*.uuid' => ['required', 'uuid'],
            'marques.*.nom' => ['required', 'string', 'max:255'],

            'fournisseurs' => ['array'],
            'fournisseurs.*.uuid' => ['required', 'uuid'],
            'fournisseurs.*.nom' => ['required', 'string', 'max:255'],
            'fournisseurs.*.contact' => ['nullable', 'string', 'max:255'],

            'articles' => ['array'],
            'articles.*.uuid' => ['required', 'uuid'],
            'articles.*.nom' => ['required', 'string', 'max:255'],
            'articles.*.prix' => ['required', 'numeric', 'min:0'],
            'articles.*.depense_le' => ['required', 'date'],
            'articles.*.marque_uuid' => ['nullable', 'uuid'],
            'articles.*.fournisseur_uuid' => ['nullable', 'uuid'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (self::MODELS as $key => $model) {
                $fillable = (new $model)->getFillable();

                foreach ($data[$key] ?? [] as $row) {
                    $model::updateOrCreate(
                        ['uuid' => $row['uuid']],
                        Arr::only($row, $fillable)
                    );
                }
            }
        });

        return response()->json([
            'marques' => Marque::orderBy('nom')->get(),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(),
            'articles' => Article::orderByDesc('depense_le')->orderByDesc('id')->get(),
        ]);
    }
}
