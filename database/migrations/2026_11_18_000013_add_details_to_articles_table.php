<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->uuid('categorie_uuid')->nullable()->after('fournisseur_uuid')->index();
            $table->string('reference')->nullable()->after('nom');
            $table->string('unite', 20)->nullable()->after('prix');
            $table->decimal('poids', 10, 3)->nullable()->after('unite');
            $table->text('caracteristiques')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['categorie_uuid']);
            $table->dropColumn(['categorie_uuid', 'reference', 'unite', 'poids', 'caracteristiques']);
        });
    }
};
