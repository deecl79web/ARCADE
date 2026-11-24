<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);               // nom du sponsor / de la campagne
            $table->string('position', 30);             // voir Ad::POSITIONS
            $table->string('image_path');               // ex : /ads/xxxx.png (dans public/)
            $table->string('link_url', 500);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
            $table->index(['position', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
