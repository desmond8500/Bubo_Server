<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables qui portent un mode de paiement (orange_money, wave, espece, virement, cheque). */
    private array $tables = ['depenses', 'depenses_personnelles', 'revenus', 'emprunts'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('mode_paiement', 20)->nullable()->after('montant');
            });
        }

        Schema::table('emprunts', function (Blueprint $t) {
            $t->boolean('rembourse')->default(false)->after('date');
            $t->date('date_remboursement')->nullable()->after('rembourse');
        });

        // Budget commun : une suite de crédits (le premier est le solde de départ).
        // Le budget de chaque mois se calcule à partir de ces crédits et des dépenses générales.
        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('description')->nullable();
            $t->decimal('montant', 12, 2);
            $t->date('date');
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');

        Schema::table('emprunts', fn (Blueprint $t) => $t->dropColumn(['rembourse', 'date_remboursement']));

        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('mode_paiement'));
        }
    }
};
