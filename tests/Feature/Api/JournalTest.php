<?php

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('le journal enregistre les actions sans exposer le contenu privé', function () {
    $membre = User::factory()->create(['name' => 'Marie']);

    Sanctum::actingAs($membre);
    $this->postJson('/api/sync', [
        'articles' => [['uuid' => (string) Str::uuid(), 'nom' => 'Sel', 'prix' => 200]],
        'revenus' => [['uuid' => (string) Str::uuid(), 'description' => 'Salaire secret', 'montant' => 1, 'date' => '2026-11-01']],
    ])->assertOk();

    $article = Journal::where('entity', 'articles')->first();
    expect($article->action)->toBe('create')
        ->and($article->libelle)->toBe('Sel')
        ->and($article->user_id)->toBe($membre->id);

    $revenu = Journal::where('entity', 'revenus')->first();
    expect($revenu->action)->toBe('create')
        ->and($revenu->libelle)->toBeNull();
});

test('le journal est réservé aux administrateurs', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $membre = User::factory()->create();

    Sanctum::actingAs($membre);
    $this->postJson('/api/sync', [
        'revenus' => [['uuid' => (string) Str::uuid(), 'description' => 'Salaire secret', 'montant' => 1, 'date' => '2026-11-01']],
    ])->assertOk();
    $this->getJson('/api/journal')->assertForbidden();

    Sanctum::actingAs($admin);
    $reponse = $this->getJson('/api/journal')->assertOk()->assertJsonCount(1, 'data');

    expect($reponse->getContent())->not->toContain('Salaire secret');
});

test('les connexions réussies et échouées sont journalisées', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'mauvais'])->assertUnprocessable();
    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

    expect(Journal::where('action', 'login_echec')->count())->toBe(1)
        ->and(Journal::where('action', 'login')->where('user_id', $user->id)->count())->toBe(1);
});

test('un changement de rôle est journalisé', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $membre = User::factory()->create(['name' => 'Moussa']);

    Sanctum::actingAs($admin);
    $this->patchJson("/api/users/{$membre->id}", ['role' => 'lecteur'])->assertOk();

    $ligne = Journal::where('action', 'role')->first();
    expect($ligne->libelle)->toContain('Moussa')->toContain('lecteur')
        ->and($ligne->user_id)->toBe($admin->id);
});

test('une synchronisation inchangée ne produit pas de nouvelle ligne de journal', function () {
    Sanctum::actingAs(User::factory()->create());
    $article = ['uuid' => (string) Str::uuid(), 'nom' => 'Huile', 'prix' => 1500];

    $this->postJson('/api/sync', ['articles' => [$article]])->assertOk();
    $this->postJson('/api/sync', ['articles' => [$article]])->assertOk();

    expect(Journal::where('entity', 'articles')->count())->toBe(1);
});
