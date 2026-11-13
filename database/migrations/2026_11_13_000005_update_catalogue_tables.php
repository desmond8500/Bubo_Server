<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marques', function (Blueprint $table) {
            $table->text('description')->nullable()->after('nom');
            $table->string('logo', 500)->nullable()->after('description');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn('contact');
            $table->text('description')->nullable()->after('nom');
            $table->string('logo', 500)->nullable()->after('description');
        });

        // La date de dépense est désormais portée par la table "depenses".
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('depense_le');
            $table->text('description')->nullable()->after('prix');
            $table->json('images')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['description', 'images']);
            $table->date('depense_le')->nullable();
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['description', 'logo']);
            $table->string('contact')->nullable();
        });

        Schema::table('marques', function (Blueprint $table) {
            $table->dropColumn(['description', 'logo']);
        });
    }
};
