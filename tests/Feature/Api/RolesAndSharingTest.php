<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function articleData(string $nom = 'Riz', array $extra = []): array
{
    return array_merge(['uuid' => (string) Str::uuid(), 'nom' => $nom, 'prix' => 500], $extra);
}

function depenseGenerale(string $articleUuid, array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'article_uuid' => $articleUuid,
        'quantite' => 2,
        'montant' => 1000,
        'date' => '2026-11-12',
    ], $extra);
}

test('le premier compte inscrit est administrateur, les suivants sont membres', function () {
    $this->postJson('/api/register', ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'motdepasse123'])
        ->assertCreated()
        ->assertJsonPath('user.role', 'admin');

    $this->postJson('/api/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'motdepasse123'])
        ->assertCreated()
        ->assertJsonPath('user.role', 'membre');
});

test('les données communes sont visibles par tous, avec leur auteur', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create();
    $article = articleData('Riz');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', [
        'articles' => [$article],
        'depenses' => [depenseGenerale($article['uuid'])],
    ])->assertOk();

    Sanctum::actingAs($bob);
    $this->getJson('/api/articles')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.nom', 'Riz')
        ->assertJsonPath('0.user_id', $alice->id)
        ->assertJsonPath('0.auteur.name', 'Alice');
    $this->getJson('/api/depenses')->assertOk()->assertJsonCount(1);

    $this->postJson('/api/sync', [])
        ->assertOk()
        ->assertJsonCount(1, 'articles')
        ->assertJsonCount(1, 'depenses')
        ->assertJsonPath('moi.id', $bob->id);
});

test('un membre ne modifie ni ne supprime les données communes des autres', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $article = articleData('Riz');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['articles' => [$article]])->assertOk();

    Sanctum::actingAs($bob);
    $this->postJson('/api/sync', ['articles' => [array_merge($article, ['nom' => 'Piraté'])]])
        ->assertOk()
        ->assertJsonPath('refuses.articles', 1);

    $this->postJson('/api/sync', ['articles' => [array_merge($article, ['deleted' => true])]])
        ->assertOk()
        ->assertJsonPath('refuses.articles', 1);

    expect(Article::where('uuid', $article['uuid'])->value('nom'))->toBe('Riz');
});

test('un administrateur peut corriger les données communes sans changer leur auteur', function () {
    $alice = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $article = articleData('Riz');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['articles' => [$article]])->assertOk();

    Sanctum::actingAs($admin);
    $this->postJson('/api/sync', ['articles' => [array_merge($article, ['nom' => 'Riz parfumé'])]])
        ->assertOk()
        ->assertJsonCount(0, 'refuses');

    $enregistre = Article::where('uuid', $article['uuid'])->first();
    expect($enregistre->nom)->toBe('Riz parfumé')
        ->and($enregistre->user_id)->toBe($alice->id);
});

test('le propriétaire gère ses propres données communes', function () {
    $alice = User::factory()->create();
    $article = articleData('Sucre');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['articles' => [$article]])->assertOk();
    $this->postJson('/api/sync', ['articles' => [array_merge($article, ['nom' => 'Sucre roux'])]])
        ->assertOk()
        ->assertJsonPath('articles.0.nom', 'Sucre roux');
    $this->postJson('/api/sync', ['articles' => [array_merge($article, ['deleted' => true])]])
        ->assertOk()
        ->assertJsonCount(0, 'articles');
});

test('un lecteur consulte les données communes mais n\'en crée pas, et garde ses données privées', function () {
    $alice = User::factory()->create();
    $lecteur = User::factory()->create(['role' => 'lecteur']);

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['articles' => [articleData('Riz')]])->assertOk();

    Sanctum::actingAs($lecteur);
    $this->postJson('/api/sync', [
        'articles' => [articleData('Lait')],
        'revenus' => [['uuid' => (string) Str::uuid(), 'description' => 'Bourse', 'montant' => 5000, 'date' => '2026-11-01']],
    ])
        ->assertOk()
        ->assertJsonPath('refuses.articles', 1)
        ->assertJsonCount(1, 'articles')
        ->assertJsonCount(1, 'revenus');

    expect(Article::count())->toBe(1);
});

test('seul un administrateur gère les rôles', function () {
    $membre = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    Sanctum::actingAs($membre);
    $this->getJson('/api/users')->assertForbidden();
    $this->patchJson("/api/users/{$membre->id}", ['role' => 'admin'])->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/api/users')->assertOk()->assertJsonCount(2);
    $this->patchJson("/api/users/{$membre->id}", ['role' => 'lecteur'])
        ->assertOk()
        ->assertJsonPath('role', 'lecteur');
    expect($membre->fresh()->role)->toBe('lecteur');

    $this->patchJson("/api/users/{$membre->id}", ['role' => 'roi'])->assertUnprocessable();
});

test('le dernier administrateur ne peut pas être rétrogradé', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Sanctum::actingAs($admin);
    $this->patchJson("/api/users/{$admin->id}", ['role' => 'membre'])->assertUnprocessable();

    expect($admin->fresh()->role)->toBe('admin');
});
