<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('entry_url');                                  // URL de l'index.html du jeu
            $table->boolean('active')->default(true);
            $table->unsignedInteger('max_score')->default(10000);         // au-dessus : score rejeté
            $table->unsignedInteger('points_per_100_score')->default(10); // entiers uniquement
            $table->unsignedInteger('daily_cap')->default(200);           // points max / joueur / jeu / jour
            $table->unsignedInteger('min_duration_seconds')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
