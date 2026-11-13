<?php

use App\Models\Article;
use App\Models\Fournisseur;
use App\Models\Journal;
use App\Models\Marque;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function catalogue(): void
{
    $marque = Marque::create(['uuid' => (string) Str::uuid(), 'nom' => 'Nike', 'description' => 'Sport']);
    $fournisseur = Fournisseur::create(['uuid' => (string) Str::uuid(), 'nom' => 'Marché Sandaga']);

    Article::create([
        'uuid' => (string) Str::uuid(),
        'nom' => 'Chaussures',
        'prix' => 25000,
        'description' => 'Pointure 42',
        'marque_uuid' => $marque->uuid,
        'fournisseur_uuid' => $fournisseur->uuid,
        'images' => ['https://exemple.test/a.jpg', 'https://exemple.test/b.jpg'],
    ]);
}

test('les exports exigent une connexion', function () {
    $this->getJson('/api/export/articles')->assertUnauthorized();
});

test('l\'export JSON des articles contient les noms de marque et de fournisseur', function () {
    catalogue();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/export/articles')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nom', 'Chaussures')
        ->assertJsonPath('0.marque', 'Nike')
        ->assertJsonPath('0.fournisseur', 'Marché Sandaga')
        ->assertJsonCount(2, '0.images');
});

test('l\'export CSV a un BOM, des en-têtes et le séparateur point-virgule', function () {
    catalogue();
    Sanctum::actingAs(User::factory()->create());

    $reponse = $this->get('/api/export/articles?format=csv')->assertOk();
    $csv = $reponse->streamedContent();

    expect($reponse->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('nom;prix;description;marque;fournisseur;images')
        ->and($csv)->toContain('Chaussures;25000;"Pointure 42";Nike;"Marché Sandaga";https://exemple.test/a.jpg|https://exemple.test/b.jpg');
});

test('les exports de marques et de fournisseurs fonctionnent', function () {
    catalogue();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/export/marques')->assertOk()->assertJsonPath('0.nom', 'Nike');
    $this->getJson('/api/export/fournisseurs')->assertOk()->assertJsonPath('0.nom', 'Marché Sandaga');
    $this->get('/api/export/marques?format=csv')->assertOk();
});

test('un type ou un format inconnu est refusé', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/export/utilisateurs')->assertNotFound();
    $this->getJson('/api/export/articles?format=xml')->assertUnprocessable();
});

test('le CSV neutralise les formules', function () {
    Marque::create(['uuid' => (string) Str::uuid(), 'nom' => '=HYPERLINK("http://piege")']);
    Sanctum::actingAs(User::factory()->create());

    $csv = $this->get('/api/export/marques?format=csv')->assertOk()->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->not->toContain(";=HYPERLINK")
        ->and($csv)->not->toMatch('/^=HYPERLINK/m');
});

test('un export est journalisé', function () {
    catalogue();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/export/articles')->assertOk();

    $ligne = Journal::where('action', 'export')->first();
    expect($ligne->entity)->toBe('articles')
        ->and($ligne->user_id)->toBe($user->id)
        ->and($ligne->libelle)->toContain('1 ligne');
});
