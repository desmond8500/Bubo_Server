<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\DepensePersonnelle;
use App\Models\Emprunt;
use App\Models\Fournisseur;
use App\Models\Journal;
use App\Models\Marque;
use App\Models\Revenu;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    /** Modes de paiement acceptés (dépenses, revenus et emprunts). */
    public const MODES_PAIEMENT = ['orange_money', 'wave', 'espece', 'virement', 'cheque'];

    public const MODELS = [
        'budgets' => Budget::class,
        'categories' => Categorie::class,
        'marques' => Marque::class,
        'fournisseurs' => Fournisseur::class,
        'articles' => Article::class,
        'depenses' => Depense::class,
        'depenses_personnelles' => DepensePersonnelle::class,
        'revenus' => Revenu::class,
        'emprunts' => Emprunt::class,
    ];

    /**
     * Données communes (budget, catégories, catalogue et dépenses générales) : visibles par tous les utilisateurs connectés.
     * Toutes les autres tables sont privées : chacun ne voit et ne modifie que ses propres lignes.
     */
    public const SHARED = ['budgets', 'categories', 'marques', 'fournisseurs', 'articles', 'depenses'];

    /** Champs images : l'app envoie une data URL, le serveur renvoie l'URL publique du fichier. */
    private const IMAGE_FIELDS = [
        'marques' => ['logo'],
        'fournisseurs' => ['logo'],
        'articles' => ['images'],
    ];

    /**
     * Reçoit les lignes créées ou modifiées hors ligne (upsert par uuid) et renvoie l'état du serveur.
     * Droits sur les données communes : un lecteur n'écrit pas, un membre gère ses propres lignes,
     * un administrateur peut tout corriger. Les écritures refusées sont comptées dans "refuses".
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $user = $request->user();
        $refuses = [];

        DB::transaction(function () use ($data, $user, &$refuses) {
            foreach (self::MODELS as $key => $model) {
                $shared = in_array($key, self::SHARED, true);
                $fillable = (new $model)->getFillable();

                foreach ($data[$key] ?? [] as $row) {
                    $existing = $model::withTrashed()->where('uuid', $row['uuid'])->first();

                    if (! $this->peutEcrire($user, $shared, $existing)) {
                        $refuses[$key] = ($refuses[$key] ?? 0) + 1;
                        continue;
                    }

                    if ($row['deleted'] ?? false) {
                        if ($existing && ! $existing->trashed()) {
                            $existing->delete();
                            Journal::enregistrer($user, 'delete', $key, $row['uuid'], $shared ? $this->libelle($existing) : null);
                        }
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

                    if ($existing && ! $record->isDirty()) {
                        continue; // rien n'a changé : pas de sauvegarde, pas de ligne de journal
                    }

                    // L'auteur ne change jamais, même quand un administrateur corrige la ligne.
                    if (! $existing) {
                        $record->user_id = $user->id;
                    }

                    $record->save();

                    Journal::enregistrer(
                        $user,
                        $existing ? 'update' : 'create',
                        $key,
                        $row['uuid'],
                        $shared ? $this->libelle($record) : null,
                    );
                }
            }
        });

        $reponse = collect(self::MODELS)->map(function (string $model, string $key) use ($user) {
            $requete = in_array($key, self::SHARED, true)
                ? $model::with('auteur:id,name')
                : $model::where('user_id', $user->id);

            return $requete->orderByDesc('id')->get();
        })->all();

        $reponse['refuses'] = (object) $refuses;
        $reponse['moi'] = $user->only(['id', 'name', 'email', 'role']);

        return response()->json($reponse);
    }

    private function peutEcrire(User $user, bool $shared, ?Model $existing): bool
    {
        if (! $existing) {
            return ! $shared || $user->canWriteShared();
        }

        if ($shared) {
            return $user->isAdmin() || ((int) $existing->user_id === $user->id && $user->canWriteShared());
        }

        return (int) $existing->user_id === $user->id;
    }

    /** Libellé lisible pour le journal (uniquement pour les données communes). */
    private function libelle(Model $model): string
    {
        if ($model instanceof Depense) {
            $article = Article::withTrashed()->where('uuid', $model->article_uuid)->value('nom');

            return trim(($article ?? 'article inconnu').' – '.$model->montant);
        }

        if ($model instanceof Budget) {
            return trim(($model->description ?: 'Crédit du budget').' – '.$model->montant);
        }

        return (string) ($model->nom ?? $model->uuid);
    }

    private function rules(): array
    {
        $image = ['nullable', 'string', 'max:3000000'];
        $mode = ['nullable', Rule::in(self::MODES_PAIEMENT)];

        return [
            ...$this->common('budgets'),
            'budgets.*.description' => ['nullable', 'string', 'max:255'],
            'budgets.*.montant' => ['required', 'numeric', 'gt:0'],
            'budgets.*.date' => ['required', 'date'],

            ...$this->common('categories'),
            'categories.*.nom' => ['required', 'string', 'max:255'],
            'categories.*.description' => ['nullable', 'string'],

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
            'depenses.*.categorie_uuid' => ['nullable', 'uuid'],
            'depenses.*.quantite' => ['required', 'numeric', 'min:0'],
            'depenses.*.montant' => ['required', 'numeric', 'min:0'],
            'depenses.*.mode_paiement' => $mode,
            'depenses.*.date' => ['required', 'date'],

            ...$this->common('depenses_personnelles'),
            'depenses_personnelles.*.designation' => ['required', 'string', 'max:255'],
            'depenses_personnelles.*.categorie_uuid' => ['nullable', 'uuid'],
            'depenses_personnelles.*.quantite' => ['required', 'numeric', 'min:0'],
            'depenses_personnelles.*.montant' => ['required', 'numeric', 'min:0'],
            'depenses_personnelles.*.mode_paiement' => $mode,
            'depenses_personnelles.*.date' => ['required', 'date'],

            ...$this->common('revenus'),
            'revenus.*.description' => ['required', 'string', 'max:255'],
            'revenus.*.montant' => ['required', 'numeric', 'min:0'],
            'revenus.*.mode_paiement' => $mode,
            'revenus.*.date' => ['required', 'date'],

            ...$this->common('emprunts'),
            'emprunts.*.description' => ['required', 'string', 'max:255'],
            'emprunts.*.montant' => ['required', 'numeric', 'min:0'],
            'emprunts.*.mode_paiement' => $mode,
            'emprunts.*.date' => ['required', 'date'],
            'emprunts.*.rembourse' => ['sometimes', 'boolean'],
            'emprunts.*.date_remboursement' => ['nullable', 'date'],
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
