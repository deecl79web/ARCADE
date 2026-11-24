<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_type_id')->constrained()->restrictOnDelete();
            // Copie figée des infos du type au moment de l'achat
            $table->string('label', 100);
            $table->unsignedInteger('value_fcfa');
            $table->unsignedInteger('cost_points');
            $table->string('status', 20)->default('purchased'); // purchased | used
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature', 64);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
