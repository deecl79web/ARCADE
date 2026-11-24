<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('daily_cap')->nullable()->default(null)->change();
        });

        DB::table('games')->update(['daily_cap' => null]);

        DB::table('settings')->updateOrInsert(
            ['key' => 'interstitial_every_n_games'],
            ['value' => '1'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('games')->whereNull('daily_cap')->update(['daily_cap' => 200]);

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedInteger('daily_cap')->default(200)->nullable(false)->change();
        });

        DB::table('settings')->where('key', 'interstitial_every_n_games')->delete();
    }
};
