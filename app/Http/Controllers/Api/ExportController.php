<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Fournisseur;
use App\Models\Journal;
use App\Models\Marque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement des données communes du catalogue : /api/export/{marques|fournisseurs|articles}?format=csv|json.
 * Le CSV utilise « ; » comme séparateur et un BOM UTF-8 pour s'ouvrir directement dans Excel.
 */
class ExportController extends Controller
{
    public const TYPES = ['marques', 'fournisseurs', 'articles'];

    public function __invoke(Request $request, string $type): JsonResponse|StreamedResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $format = $request->query('format', 'json');
        abort_unless(in_array($format, ['json', 'csv'], true), 422, 'Format inconnu : utilisez json ou csv.');

        $rows = $this->rows($type);

        Journal::enregistrer($request->user(), 'export', $type, null, count($rows).' ligne(s) ('.$format.')');

        if ($format === 'json') {
            return response()->json($rows);
        }

        return $this->csv($type, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $type): array
    {
        return match ($type) {
            'marques' => Marque::orderBy('nom')->get()
                ->map(fn (Marque $m) => ['nom' => $m->nom, 'description' => $m->description, 'logo' => $m->logo])
                ->all(),

            'fournisseurs' => Fournisseur::orderBy('nom')->get()
                ->map(fn (Fournisseur $f) => ['nom' => $f->nom, 'description' => $f->description, 'logo' => $f->logo])
                ->all(),

            'articles' => $this->articles(),
        };
    }

    /** @return list<array<string, mixed>> */
    private function articles(): array
    {
        $marques = Marque::pluck('nom', 'uuid');
        $fournisseurs = Fournisseur::pluck('nom', 'uuid');

        return Article::orderBy('nom')->get()
            ->map(fn (Article $a) => [
                'nom' => $a->nom,
                'prix' => $a->prix,
                'description' => $a->description,
                'marque' => $marques[$a->marque_uuid] ?? null,
                'fournisseur' => $fournisseurs[$a->fournisseur_uuid] ?? null,
                'images' => $a->images ?? [],
            ])
            ->all();
    }

    private function csv(string $type, array $rows): StreamedResponse
    {
        $colonnes = match ($type) {
            'articles' => ['nom', 'prix', 'description', 'marque', 'fournisseur', 'images'],
            default => ['nom', 'description', 'logo'],
        };

        return response()->streamDownload(function () use ($colonnes, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $colonnes, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $c) => $this->cellule($row[$c] ?? null), $colonnes), ';');
            }

            fclose($out);
        }, "{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Aplatit la valeur et neutralise les formules (=, +, -, @) qu'un tableur exécuterait à l'ouverture. */
    private function cellule(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode('|', $value);
        }

        $texte = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $texte) && ! is_numeric($texte) ? "'".$texte : $texte;
    }
}
