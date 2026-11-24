<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
        });

        DB::table('settings')->insert([
            ['key' => 'budget_percent', 'value' => '50'],      // % des revenus pub utilisable en bons
            ['key' => 'budget_gate_enabled', 'value' => '1'],  // 1 = bloquer les achats hors budget
            ['key' => 'budget_lock', 'value' => '1'],          // ligne verrouillée pour sérialiser les achats
        ]);

        // Revenus publicitaires réellement encaissés / validés, saisis par l'admin
        Schema::create('revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->default('Régie');
            $table->unsignedBigInteger('amount_fcfa');
            $table->date('received_on');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('voucher_types', function (Blueprint $table) {
            $table->unsignedInteger('min_purchase_fcfa')->default(0)->after('cost_points');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedInteger('min_purchase_fcfa')->default(0)->after('cost_points');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('min_purchase_fcfa'));
        Schema::table('voucher_types', fn (Blueprint $table) => $table->dropColumn('min_purchase_fcfa'));
        Schema::dropIfExists('revenue_entries');
        Schema::dropIfExists('settings');
    }
};
