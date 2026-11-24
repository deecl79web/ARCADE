<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emplacements de régie publicitaire (Monetag, Adsterra...) : code collé par l'admin
        Schema::create('network_ads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                 // ex : "Monetag - bannière liste"
            $table->string('provider', 50)->nullable();  // libellé libre : Monetag, Adsterra...
            $table->string('position', 30);              // voir NetworkAd::POSITIONS
            $table->text('html_code');                   // code fourni par la régie
            $table->unsignedSmallInteger('width')->default(320);
            $table->unsignedSmallInteger('height')->default(100);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('views')->default(0); // chargements du cadre (indicatif)
            $table->timestamps();
            $table->index(['position', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_ads');
    }
};
