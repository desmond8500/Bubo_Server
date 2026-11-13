<?php

use App\Models\Budget;
use App\Models\Emprunt;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function unCredit(float $montant = 100000, array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'description' => 'Solde de départ',
        'montant' => $montant,
        'date' => '2026-11-01',
    ], $extra);
}

function unEmprunt(array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'description' => 'Prêt à Moussa',
        'montant' => 50000,
        'date' => '2026-11-05',
    ], $extra);
}

test('le budget est commun : tous le voient avec son auteur', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['budgets' => [unCredit(250000)]])->assertOk();

    Sanctum::actingAs($bob);
    $this->getJson('/api/budgets')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.montant', 250000)
        ->assertJsonPath('0.auteur.name', 'Alice');
});

test('un membre ajoute des crédits, un lecteur non, et un crédit doit être positif', function () {
    $membre = User::factory()->create();
    $lecteur = User::factory()->create(['role' => 'lecteur']);

    Sanctum::actingAs($membre);
    $this->postJson('/api/sync', ['budgets' => [unCredit(10000), unCredit(5000, ['description' => 'Complément'])]])
        ->assertOk()
        ->assertJsonCount(2, 'budgets');

    $this->postJson('/api/sync', ['budgets' => [unCredit(0)]])->assertUnprocessable();
    $this->postJson('/api/sync', ['budgets' => [unCredit(-5)]])->assertUnprocessable();

    Sanctum::actingAs($lecteur);
    $this->postJson('/api/sync', ['budgets' => [unCredit(999)]])
        ->assertOk()
        ->assertJsonPath('refuses.budgets', 1);

    expect(Budget::count())->toBe(2);
});

test('un membre ne modifie pas le crédit d\'un autre', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $credit = unCredit(10000);

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['budgets' => [$credit]])->assertOk();

    Sanctum::actingAs($bob);
    $this->postJson('/api/sync', ['budgets' => [array_merge($credit, ['montant' => 1])]])
        ->assertOk()
        ->assertJsonPath('refuses.budgets', 1);

    expect((float) Budget::where('uuid', $credit['uuid'])->value('montant'))->toBe(10000.0);
});

test('le mode de paiement est accepté pour les quatre types et refusé s\'il est inconnu', function () {
    Sanctum::actingAs(User::factory()->create());
    $article = ['uuid' => (string) Str::uuid(), 'nom' => 'Riz', 'prix' => 500];

    $this->postJson('/api/sync', [
        'articles' => [$article],
        'depenses' => [[
            'uuid' => (string) Str::uuid(), 'article_uuid' => $article['uuid'], 'quantite' => 1,
            'montant' => 500, 'date' => '2026-11-12', 'mode_paiement' => 'wave',
        ]],
        'depenses_personnelles' => [[
            'uuid' => (string) Str::uuid(), 'designation' => 'Taxi', 'quantite' => 1,
            'montant' => 1500, 'date' => '2026-11-12', 'mode_paiement' => 'orange_money',
        ]],
        'revenus' => [['uuid' => (string) Str::uuid(), 'description' => 'Salaire', 'montant' => 1, 'date' => '2026-11-01', 'mode_paiement' => 'virement']],
        'emprunts' => [unEmprunt(['mode_paiement' => 'cheque'])],
    ])
        ->assertOk()
        ->assertJsonPath('depenses.0.mode_paiement', 'wave')
        ->assertJsonPath('depenses_personnelles.0.mode_paiement', 'orange_money')
        ->assertJsonPath('revenus.0.mode_paiement', 'virement')
        ->assertJsonPath('emprunts.0.mode_paiement', 'cheque');

    $this->postJson('/api/sync', ['revenus' => [[
        'uuid' => (string) Str::uuid(), 'description' => 'X', 'montant' => 1, 'date' => '2026-11-01', 'mode_paiement' => 'bitcoin',
    ]]])->assertUnprocessable();

    // Le mode de paiement reste facultatif.
    $this->postJson('/api/sync', ['revenus' => [[
        'uuid' => (string) Str::uuid(), 'description' => 'Prime', 'montant' => 1, 'date' => '2026-11-02',
    ]]])->assertOk();
});

test('un prêt peut être marqué remboursé, avec la date du jour par défaut', function () {
    Sanctum::actingAs(User::factory()->create());
    $pret = unEmprunt();

    $this->postJson('/api/sync', ['emprunts' => [$pret]])
        ->assertOk()
        ->assertJsonPath('emprunts.0.rembourse', false)
        ->assertJsonPath('emprunts.0.date_remboursement', null);

    $this->postJson('/api/sync', ['emprunts' => [array_merge($pret, ['rembourse' => true])]])
        ->assertOk()
        ->assertJsonPath('emprunts.0.rembourse', true)
        ->assertJsonPath('emprunts.0.date_remboursement', now()->toDateString());

    $this->postJson('/api/sync', ['emprunts' => [array_merge($pret, ['rembourse' => true, 'date_remboursement' => '2026-11-20'])]])
        ->assertOk()
        ->assertJsonPath('emprunts.0.date_remboursement', '2026-11-20');
});

test('annuler un remboursement efface sa date', function () {
    Sanctum::actingAs(User::factory()->create());
    $pret = unEmprunt(['rembourse' => true, 'date_remboursement' => '2026-11-20']);

    $this->postJson('/api/sync', ['emprunts' => [$pret]])->assertOk();
    $this->postJson('/api/sync', ['emprunts' => [array_merge($pret, ['rembourse' => false])]])
        ->assertOk()
        ->assertJsonPath('emprunts.0.rembourse', false)
        ->assertJsonPath('emprunts.0.date_remboursement', null);

    expect(Emprunt::first()->date_remboursement)->toBeNull();
});

test('les emprunts restent privés', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/sync', ['emprunts' => [unEmprunt()]])->assertOk();

    Sanctum::actingAs($bob);
    $this->getJson('/api/emprunts')->assertOk()->assertJsonCount(0);
});
