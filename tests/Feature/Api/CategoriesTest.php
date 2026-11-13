<?php

use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function uneCategorie(string $nom = 'Alimentation', array $extra = []): array
{
    return array_merge(['uuid' => (string) Str::uuid(), 'nom' => $nom, 'description' => null], $extra);
}

test('les catégories sont communes : tous les utilisateurs les voient', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['categories' => [uneCategorie('Transport')]])->assertOk();

    Sanctum::actingAs($bob);
    $this->getJson('/api/categories')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nom', 'Transport')
        ->assertJsonPath('0.auteur.name', 'Alice');
});

test('un membre ne modifie pas la catégorie d\'un autre, un lecteur n\'en crée pas', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $lecteur = User::factory()->create(['role' => 'lecteur']);
    $categorie = uneCategorie('Loisirs');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['categories' => [$categorie]])->assertOk();

    Sanctum::actingAs($bob);
    $this->postJson('/api/sync', ['categories' => [array_merge($categorie, ['nom' => 'Piraté'])]])
        ->assertOk()
        ->assertJsonPath('refuses.categories', 1);

    Sanctum::actingAs($lecteur);
    $this->postJson('/api/sync', ['categories' => [uneCategorie('Interdite')]])
        ->assertOk()
        ->assertJsonPath('refuses.categories', 1);

    expect(Categorie::count())->toBe(1)
        ->and(Categorie::first()->nom)->toBe('Loisirs');
});

test('les dépenses générales et personnelles gardent leur catégorie', function () {
    Sanctum::actingAs(User::factory()->create());
    $categorie = uneCategorie('Santé');
    $article = ['uuid' => (string) Str::uuid(), 'nom' => 'Paracétamol', 'prix' => 500];

    $this->postJson('/api/sync', [
        'categories' => [$categorie],
        'articles' => [$article],
        'depenses' => [[
            'uuid' => (string) Str::uuid(),
            'article_uuid' => $article['uuid'],
            'categorie_uuid' => $categorie['uuid'],
            'quantite' => 1,
            'montant' => 500,
            'date' => '2026-11-12',
        ]],
        'depenses_personnelles' => [[
            'uuid' => (string) Str::uuid(),
            'designation' => 'Pharmacie',
            'categorie_uuid' => $categorie['uuid'],
            'quantite' => 1,
            'montant' => 1500,
            'date' => '2026-11-12',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('depenses.0.categorie_uuid', $categorie['uuid'])
        ->assertJsonPath('depenses_personnelles.0.categorie_uuid', $categorie['uuid']);

    expect(Depense::first()->categorie_uuid)->toBe($categorie['uuid']);
});

test('une catégorie sans nom est refusée', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/sync', ['categories' => [['uuid' => (string) Str::uuid()]]])->assertUnprocessable();
});
