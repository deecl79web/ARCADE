<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->unique()->constrained('game_sessions')->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->unsignedInteger('points_awarded')->default(0);
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_submissions');
    }
};
