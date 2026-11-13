<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dépenses libres, sans article du catalogue (ex. coiffeur, transport).
        Schema::create('depenses_personnelles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->decimal('quantite', 10, 2)->default(1);
            $table->decimal('montant', 12, 2);
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses_personnelles');
    }
};
