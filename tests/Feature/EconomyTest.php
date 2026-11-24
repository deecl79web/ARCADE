<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\NetworkAd;
use App\Models\PointsLedger;
use App\Models\RevenueEntry;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use Laravel\Sanctum\Sanctum;
use Tests\ArcadeTestCase;

class EconomyTest extends ArcadeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // On isole la règle du budget : pas de limite d'ancienneté ni de nombre d'achats ici
        config([
            'arcade.voucher_min_account_age_hours' => 0,
            'arcade.voucher_max_per_day' => 0,
        ]);
    }

    private function player(int $points = 0): User
    {
        $user = User::factory()->create(['created_at' => now()->subDays(3)]);

        if ($points > 0) {
            PointsLedger::create(['user_id' => $user->id, 'delta' => $points, 'reason' => 'test']);
        }

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'created_at' => now()->subDays(3)]);
    }

    private function type(array $overrides = []): VoucherType
    {
        return VoucherType::create(array_merge([
            'label' => 'Bon 500',
            'value_fcfa' => 500,
            'cost_points' => 100,
            'min_purchase_fcfa' => 0,
            'validity_days' => 30,
            'active' => true,
        ], $overrides));
    }

    private function revenue(int $amount): RevenueEntry
    {
        return RevenueEntry::create([
            'source' => 'Monetag',
            'amount_fcfa' => $amount,
            'received_on' => now()->toDateString(),
        ]);
    }

    /** Crée $count parties terminées, chacune ayant rapporté $points points. */
    private function completedGames(User $user, int $count, int $points): void
    {
        $game = Game::firstOrCreate(['slug' => 'economy-game'], [
            'title' => 'Economy',
            'entry_url' => '/games/economy/index.html',
            'max_score' => 1000,
            'points_per_100_score' => 10,
            'min_duration_seconds' => 0,
        ]);

        for ($i = 0; $i < $count; $i++) {
            GameSession::create([
                'user_id' => $user->id,
                'game_id' => $game->id,
                'token_hash' => str_repeat('a', 64),
                'started_at' => now()->subMinutes(5),
                'ended_at' => now()->subMinutes(4),
                'status' => 'completed',
            ]);
            PointsLedger::create(['user_id' => $user->id, 'delta' => $points, 'reason' => 'game_score']);
        }
    }

    private function buy(VoucherType $type)
    {
        return $this->postJson('/api/vouchers/purchase', ['voucher_type_id' => $type->id]);
    }

    public function test_purchase_is_blocked_when_no_revenue_is_declared(): void
    {
        Sanctum::actingAs($this->player(5000));

        $this->buy($this->type())->assertStatus(422)->assertJsonValidationErrors('budget');

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_budget_is_half_of_revenue_and_limits_issued_value(): void
    {
        $this->revenue(1000); // budget = 50 % = 500 FCFA de bons
        Sanctum::actingAs($this->player(5000));
        $type = $this->type(['value_fcfa' => 500]);

        $this->buy($type)->assertCreated();
        $this->buy($type)->assertStatus(422)->assertJsonValidationErrors('budget');

        $this->assertDatabaseCount('vouchers', 1);
    }

    public function test_percentage_can_be_changed(): void
    {
        $this->revenue(1000);
        Setting::putValue('budget_percent', '100'); // 1 000 FCFA de bons pour 1 000 de revenus
        Sanctum::actingAs($this->player(5000));
        $type = $this->type(['value_fcfa' => 500]);

        $this->buy($type)->assertCreated();
        $this->buy($type)->assertCreated();
        $this->buy($type)->assertStatus(422)->assertJsonValidationErrors('budget');
    }

    public function test_expired_unused_vouchers_release_their_budget(): void
    {
        $this->revenue(1000);
        Sanctum::actingAs($this->player(5000));
        $type = $this->type(['value_fcfa' => 500]);

        $this->buy($type)->assertCreated();
        $this->buy($type)->assertStatus(422);

        Voucher::query()->update(['expires_at' => now()->subDay()]);

        $this->buy($type)->assertCreated();
    }

    public function test_gate_can_be_disabled(): void
    {
        Setting::putValue('budget_gate_enabled', '0');
        Sanctum::actingAs($this->player(5000));

        $this->buy($this->type())->assertCreated();
    }

    public function test_catalog_exposes_availability_but_no_financial_figures(): void
    {
        $this->revenue(1000); // budget 500
        $this->type(['label' => 'Petit', 'value_fcfa' => 500, 'cost_points' => 100]);
        $this->type(['label' => 'Gros', 'value_fcfa' => 2000, 'cost_points' => 400]);
        Sanctum::actingAs($this->player());

        $json = $this->getJson('/api/voucher-types')->assertOk()->json();
        $byValue = collect($json)->keyBy('value_fcfa');

        $this->assertTrue($byValue[500]['available']);
        $this->assertFalse($byValue[2000]['available']);
        $this->assertArrayNotHasKey('budget_fcfa', $json[0]);
        $this->assertArrayNotHasKey('revenue_total_fcfa', $json[0]);
    }

    public function test_min_purchase_is_copied_to_the_voucher_and_shown_on_scan(): void
    {
        $this->revenue(5000);
        Sanctum::actingAs($this->player(1000));
        $this->buy($this->type(['min_purchase_fcfa' => 7500]))->assertCreated();

        $voucher = Voucher::firstOrFail();
        $this->assertSame(7500, $voucher->min_purchase_fcfa);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/vouchers/scan', ['payload' => $voucher->uuid.'.'.$voucher->signature])
            ->assertOk()
            ->assertJsonPath('voucher.min_purchase_fcfa', 7500);
    }

    public function test_economy_endpoints_are_admin_only(): void
    {
        Sanctum::actingAs($this->player());

        $this->getJson('/api/admin/economy')->assertForbidden();
        $this->postJson('/api/admin/revenue', ['amount_fcfa' => 1000])->assertForbidden();
        $this->putJson('/api/admin/economy/settings', ['budget_percent' => 90])->assertForbidden();
    }

    public function test_admin_snapshot_computes_budget_and_recommended_price(): void
    {
        $this->type(['value_fcfa' => 500, 'cost_points' => 100]);
        // 500 points distribués par le jeu
        PointsLedger::create(['user_id' => $this->player()->id, 'delta' => 500, 'reason' => 'game_score']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/revenue', ['amount_fcfa' => 1000, 'source' => 'Monetag'])->assertCreated();

        $json = $this->getJson('/api/admin/economy')
            ->assertOk()
            ->assertJsonPath('revenue_total_fcfa', 1000)
            ->assertJsonPath('budget_fcfa', 500)
            ->assertJsonPath('remaining_fcfa', 500)
            ->assertJsonPath('points.distributed', 500)
            ->assertJsonPath('settings.budget_percent', 50)
            ->json();

        // 1 000 FCFA de revenu pour 500 points distribués = 2 FCFA par point
        $this->assertEquals(2, $json['revenue_per_point_fcfa']);

        // Bon de 500 FCFA -> il faut avoir généré 1 000 FCFA de revenu -> 500 points
        $this->assertSame(500, $json['voucher_types'][0]['recommended_cost_points']);
    }

    public function test_snapshot_has_no_recommendation_without_data(): void
    {
        $this->type();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/economy')
            ->assertOk()
            ->assertJsonPath('voucher_types.0.recommended_cost_points', null);
    }

    public function test_settings_and_revenue_validation(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/economy/settings', ['budget_percent' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('budget_percent');

        $this->putJson('/api/admin/economy/settings', ['budget_percent' => 30, 'gate_enabled' => false])
            ->assertOk()
            ->assertJsonPath('settings.budget_percent', 30)
            ->assertJsonPath('settings.gate_enabled', false);

        $this->postJson('/api/admin/revenue', ['amount_fcfa' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('amount_fcfa');

        $id = $this->postJson('/api/admin/revenue', ['amount_fcfa' => 2500])->assertCreated()->json('id');
        $this->deleteJson("/api/admin/revenue/{$id}")->assertNoContent();
        $this->assertDatabaseCount('revenue_entries', 0);
    }

    public function test_admin_can_edit_voucher_type_price_and_minimum_purchase(): void
    {
        $type = $this->type();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/voucher-types/{$type->id}", [
            'cost_points' => 4000,
            'min_purchase_fcfa' => 15000,
        ])->assertOk()->assertJsonPath('cost_points', 4000)->assertJsonPath('min_purchase_fcfa', 15000);
    }

    public function test_top_gains_24h_include_games_and_shared_registration_ip(): void
    {
        $a = $this->player();
        $b = $this->player();
        $a->forceFill(['registration_ip' => '10.0.0.8'])->save();
        $b->forceFill(['registration_ip' => '10.0.0.8'])->save();

        PointsLedger::create(['user_id' => $a->id, 'delta' => 120, 'reason' => 'game_score']);
        PointsLedger::create(['user_id' => $a->id, 'delta' => 80, 'reason' => 'game_score']);
        PointsLedger::create(['user_id' => $b->id, 'delta' => 300, 'reason' => 'game_score']);

        Sanctum::actingAs($this->admin());

        $json = $this->getJson('/api/admin/economy')->assertOk()->json();

        $this->assertSame(300, $json['top_gains_24h'][0]['points_gained']);
        $this->assertSame(1, $json['top_gains_24h'][0]['games_played']);
        $this->assertSame(2, $json['top_gains_24h'][0]['shared_ip_accounts']);
    }

    public function test_admin_snapshot_gives_games_needed_underpriced_flag_and_ad_metrics(): void
    {
        $this->type(['label' => 'Bon 500', 'value_fcfa' => 500, 'cost_points' => 2000]);
        $this->type(['label' => 'Trop bas', 'value_fcfa' => 500, 'cost_points' => 100]);

        // 2 parties terminées qui ont rapporté 200 points chacune, pour 1 000 FCFA de revenus
        $this->completedGames($this->player(), 2, 200);
        $this->revenue(1000);

        Ad::create([
            'title' => 'Sponsor', 'position' => 'game_below', 'image_path' => '/ads/x.png',
            'link_url' => 'https://example.com', 'active' => true, 'impressions' => 30,
        ]);
        NetworkAd::create([
            'name' => 'Régie', 'position' => 'game_below', 'html_code' => '<p></p>',
            'width' => 320, 'height' => 100, 'active' => true, 'views' => 10,
        ]);

        Sanctum::actingAs($this->admin());
        $json = $this->getJson('/api/admin/economy')->assertOk()->json();

        $this->assertSame(400, $json['points']['distributed']);
        $this->assertEquals(200, $json['average_points_per_completed_game']);

        $types = collect($json['voucher_types'])->keyBy('label');

        // 1 000 FCFA de revenus pour 400 points = 2,5 FCFA par point ; bon de 500 FCFA => 400 points recommandés
        $this->assertSame(400, $types['Bon 500']['recommended_cost_points']);
        $this->assertFalse($types['Bon 500']['underpriced']);
        $this->assertSame(10, $types['Bon 500']['games_needed']); // 2 000 pts / 200 pts par partie
        $this->assertTrue($types['Trop bas']['underpriced']);     // 100 pts < 400 pts recommandés

        $this->assertSame(40, $json['ad_displays']['total']);
        $this->assertSame(30, $json['ad_displays']['sponsor_impressions']);
        $this->assertSame(10, $json['ad_displays']['network_views']);
        $this->assertEquals(20, $json['ad_displays']['per_completed_game']);       // 40 affichages / 2 parties
        $this->assertEquals(25, $json['ad_displays']['revenue_per_display_fcfa']); // 1 000 FCFA / 40
    }

    public function test_economy_snapshot_without_data_has_no_figures_to_divide_by(): void
    {
        $this->type();
        Sanctum::actingAs($this->admin());

        $json = $this->getJson('/api/admin/economy')->assertOk()->json();

        $this->assertNull($json['average_points_per_completed_game']);
        $this->assertNull($json['ad_displays']['per_completed_game']);
        $this->assertNull($json['ad_displays']['revenue_per_display_fcfa']);
        $this->assertNull($json['voucher_types'][0]['games_needed']);
        $this->assertNull($json['voucher_types'][0]['recommended_cost_points']);
        $this->assertFalse($json['voucher_types'][0]['underpriced']);
        $this->assertSame([], $json['top_gains_24h']);
    }

    public function test_recommended_price_is_not_off_by_one_because_of_float_rounding(): void
    {
        // 100 FCFA de revenus pour 322 points : 500 x 100 / 50 = 1 000 FCFA à générer, soit exactement 3 220 points
        $this->type(['value_fcfa' => 500, 'cost_points' => 100]);
        PointsLedger::create(['user_id' => $this->player()->id, 'delta' => 322, 'reason' => 'game_score']);
        $this->revenue(100);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/economy')
            ->assertOk()
            ->assertJsonPath('voucher_types.0.recommended_cost_points', 3220);
    }

    public function test_public_interstitial_setting_can_be_updated_by_admin(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/economy/settings', ['interstitial_every_n_games' => 3])
            ->assertOk()
            ->assertJsonPath('settings.interstitial_every_n_games', 3);

        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('interstitial_every_n_games', 3);
    }
}
