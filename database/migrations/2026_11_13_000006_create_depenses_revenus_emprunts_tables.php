<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('article_uuid')->index();
            $table->decimal('quantite', 10, 2)->default(1);
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('revenus', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('description');
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('emprunts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('description');
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprunts');
        Schema::dropIfExists('revenus');
        Schema::dropIfExists('depenses');
    }
};
