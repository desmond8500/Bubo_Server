<?php

use App\Models\Revenu;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function ligne(string $description, array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'description' => $description,
        'montant' => 1000,
        'date' => '2026-11-01',
    ], $extra);
}

test('les routes de l\'API exigent un token', function () {
    $this->postJson('/api/sync', [])->assertUnauthorized();
    $this->getJson('/api/revenus')->assertUnauthorized();
    $this->getJson('/api/me')->assertUnauthorized();
});

test('un utilisateur ne voit que ses revenus et ses emprunts', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', [
        'revenus' => [ligne('Salaire Alice')],
        'emprunts' => [ligne('Prêt Alice')],
    ])->assertOk();

    Sanctum::actingAs($bob);
    $this->getJson('/api/revenus')->assertOk()->assertJsonCount(0);
    $this->getJson('/api/emprunts')->assertOk()->assertJsonCount(0);

    $this->postJson('/api/sync', ['revenus' => [ligne('Salaire Bob')]])
        ->assertOk()
        ->assertJsonCount(1, 'revenus')
        ->assertJsonPath('revenus.0.description', 'Salaire Bob')
        ->assertJsonCount(0, 'emprunts');

    Sanctum::actingAs($alice);
    $this->getJson('/api/revenus')->assertOk()->assertJsonCount(1)->assertJsonPath('0.description', 'Salaire Alice');
});

test('un uuid appartenant à un autre utilisateur est ignoré', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $revenu = ligne('Salaire Alice');

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['revenus' => [$revenu]])->assertOk();

    Sanctum::actingAs($bob);
    $this->postJson('/api/sync', ['revenus' => [array_merge($revenu, ['description' => 'Piraté'])]])
        ->assertOk()
        ->assertJsonCount(0, 'revenus');

    expect(Revenu::where('uuid', $revenu['uuid'])->value('description'))->toBe('Salaire Alice');
});

test('une ligne marquée deleted est supprimée pour son propriétaire', function () {
    Sanctum::actingAs(User::factory()->create());
    $revenu = ligne('Prime');

    $this->postJson('/api/sync', ['revenus' => [$revenu]])->assertOk()->assertJsonCount(1, 'revenus');

    $this->postJson('/api/sync', ['revenus' => [array_merge($revenu, ['deleted' => true])]])
        ->assertOk()
        ->assertJsonCount(0, 'revenus');

    expect(Revenu::withTrashed()->count())->toBe(1);
});

test('les dépenses personnelles sont synchronisées et privées', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', [
        'depenses_personnelles' => [[
            'uuid' => (string) Str::uuid(),
            'designation' => 'Coiffeur',
            'quantite' => 1,
            'montant' => 3000,
            'date' => '2026-11-10',
        ]],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'depenses_personnelles')
        ->assertJsonPath('depenses_personnelles.0.designation', 'Coiffeur');

    Sanctum::actingAs($bob);
    $this->getJson('/api/depenses_personnelles')->assertOk()->assertJsonCount(0);
});

test('une ligne invalide est refusée', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/sync', ['revenus' => [['uuid' => (string) Str::uuid(), 'description' => 'Sans montant']]])
        ->assertUnprocessable();
});
