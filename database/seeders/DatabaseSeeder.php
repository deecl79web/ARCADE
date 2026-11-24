<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\User;
use App\Models\VoucherType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ⚠️ En production, ne garde pas ce compte : utilise `php artisan arcade:create-admin ton@email.com`
        $admin = User::firstOrNew(['email' => 'admin@arcade.test']);
        $admin->forceFill([
            'name' => 'Admin',
            'password' => 'password',
            'role' => 'admin',
        ])->save();

        // Prix initiaux au rapport 4 points par FCFA : ajustables depuis Admin → Types de bons
        foreach ([
            ['label' => 'Bon 500 FCFA', 'value_fcfa' => 500, 'cost_points' => 2000, 'min_purchase_fcfa' => 7500, 'validity_days' => 30],
            ['label' => 'Bon 1 000 FCFA', 'value_fcfa' => 1000, 'cost_points' => 4000, 'min_purchase_fcfa' => 15000, 'validity_days' => 60],
            ['label' => 'Bon 2 500 FCFA', 'value_fcfa' => 2500, 'cost_points' => 10000, 'min_purchase_fcfa' => 37500, 'validity_days' => 60],
            ['label' => 'Bon 5 000 FCFA', 'value_fcfa' => 5000, 'cost_points' => 20000, 'min_purchase_fcfa' => 75000, 'validity_days' => 90],
        ] as $type) {
            VoucherType::firstOrCreate(['label' => $type['label']], $type);
        }

        // Jeu de démo servi par arcade-front (public/games/demo/index.html)
        Game::firstOrCreate(['slug' => 'demo-clicker'], [
            'title' => 'Demo Clicker',
            'description' => 'Clique le plus vite possible pendant 15 secondes.',
            'entry_url' => '/games/demo/index.html',
            'max_score' => 1500,
            'points_per_100_score' => 20,
            'min_duration_seconds' => 10,
        ]);
    }
}
