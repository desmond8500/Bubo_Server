<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->uuid('categorie_uuid')->nullable()->after('article_uuid')->index();
        });

        Schema::table('depenses_personnelles', function (Blueprint $table) {
            $table->uuid('categorie_uuid')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('depenses_personnelles', fn (Blueprint $t) => $t->dropColumn('categorie_uuid'));
        Schema::table('depenses', fn (Blueprint $t) => $t->dropColumn('categorie_uuid'));
        Schema::dropIfExists('categories');
    }
};
