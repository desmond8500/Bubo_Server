<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['marques', 'fournisseurs', 'articles', 'depenses', 'revenus', 'emprunts'];

    /**
     * Chaque ligne appartient à un utilisateur. La colonne est nullable pour ne pas casser
     * les données de test existantes : les lignes sans propriétaire ne sont visibles de personne.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('user_id')->nullable()->after('uuid')->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        }
    }
};
