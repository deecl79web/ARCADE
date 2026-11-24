<?php

namespace Tests\Feature;

use App\Models\PointsLedger;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use Laravel\Sanctum\Sanctum;
use Tests\ArcadeTestCase;

class VoucherTest extends ArcadeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'arcade.voucher_min_account_age_hours' => 24,
            'arcade.voucher_max_per_day' => 2,
        ]);

        // La barrière de budget est testée séparément (EconomyTest)
        Setting::putValue('budget_gate_enabled', '0');
    }

    private function player(int $points = 0, bool $oldAccount = true): User
    {
        $user = User::factory()->create([
            'created_at' => $oldAccount ? now()->subDays(3) : now(),
        ]);

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
            'label' => 'Bon 1000',
            'value_fcfa' => 1000,
            'cost_points' => 500,
            'validity_days' => 30,
            'active' => true,
        ], $overrides));
    }

    private function buy(VoucherType $type)
    {
        return $this->postJson('/api/vouchers/purchase', ['voucher_type_id' => $type->id]);
    }

    private function payload(Voucher $voucher): string
    {
        return $voucher->uuid.'.'.$voucher->signature;
    }

    public function test_purchase_deducts_points_and_creates_voucher(): void
    {
        $user = $this->player(1200);
        Sanctum::actingAs($user);

        $this->buy($this->type())
            ->assertCreated()
            ->assertJson(['balance' => 700]);

        $this->assertDatabaseHas('vouchers', ['user_id' => $user->id, 'status' => 'purchased', 'value_fcfa' => 1000]);
        $this->assertDatabaseHas('points_ledger', ['user_id' => $user->id, 'delta' => -500, 'reason' => 'voucher_purchase']);
    }

    public function test_purchase_fails_with_insufficient_points(): void
    {
        Sanctum::actingAs($this->player(100));

        $this->buy($this->type())->assertStatus(422)->assertJsonValidationErrors('points');
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_purchase_fails_for_a_brand_new_account(): void
    {
        Sanctum::actingAs($this->player(5000, oldAccount: false));

        $this->buy($this->type())->assertStatus(422)->assertJsonValidationErrors('account');
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_daily_purchase_limit_is_enforced(): void
    {
        Sanctum::actingAs($this->player(5000));
        $type = $this->type();

        $this->buy($type)->assertCreated();
        $this->buy($type)->assertCreated();
        $this->buy($type)->assertStatus(422)->assertJsonValidationErrors('voucher_type_id');

        $this->assertDatabaseCount('vouchers', 2);
    }

    public function test_inactive_voucher_type_cannot_be_bought(): void
    {
        Sanctum::actingAs($this->player(5000));

        $this->buy($this->type(['active' => false]))->assertStatus(422);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_admin_scan_marks_voucher_used_and_second_scan_is_refused(): void
    {
        Sanctum::actingAs($this->player(1000));
        $this->buy($this->type())->assertCreated();
        $voucher = Voucher::firstOrFail();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/vouchers/scan', ['payload' => $this->payload($voucher)])
            ->assertOk()
            ->assertJson(['result' => 'ok']);

        $this->assertSame('used', $voucher->fresh()->status);

        $this->postJson('/api/admin/vouchers/scan', ['payload' => $this->payload($voucher)])
            ->assertStatus(422)
            ->assertJson(['result' => 'already_used']);

        $this->assertDatabaseCount('voucher_scans', 2);
    }

    public function test_tampered_signature_is_refused_and_voucher_stays_valid(): void
    {
        Sanctum::actingAs($this->player(1000));
        $this->buy($this->type())->assertCreated();
        $voucher = Voucher::firstOrFail();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/vouchers/scan', [
            'payload' => $voucher->uuid.'.'.str_repeat('0', 64),
        ])->assertStatus(422)->assertJson(['result' => 'invalid_signature']);

        $this->assertSame('purchased', $voucher->fresh()->status);
    }

    public function test_expired_voucher_is_refused(): void
    {
        Sanctum::actingAs($this->player(1000));
        $this->buy($this->type())->assertCreated();
        $voucher = Voucher::firstOrFail();
        $voucher->update(['expires_at' => now()->subDay()]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/vouchers/scan', ['payload' => $this->payload($voucher)])
            ->assertStatus(422)
            ->assertJson(['result' => 'expired']);

        $this->assertSame('purchased', $voucher->fresh()->status);
    }

    public function test_non_admin_cannot_scan(): void
    {
        Sanctum::actingAs($this->player(1000));
        $this->buy($this->type())->assertCreated();
        $voucher = Voucher::firstOrFail();

        $this->postJson('/api/admin/vouchers/scan', ['payload' => $this->payload($voucher)])
            ->assertForbidden();

        $this->assertSame('purchased', $voucher->fresh()->status);
    }

    public function test_pdf_is_only_available_to_the_owner(): void
    {
        $owner = $this->player(1000);
        Sanctum::actingAs($owner);
        $this->buy($this->type())->assertCreated();
        $voucher = Voucher::firstOrFail();

        $this->get("/api/vouchers/{$voucher->uuid}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs($this->player());
        $this->get("/api/vouchers/{$voucher->uuid}/pdf")->assertNotFound();
    }

    public function test_admin_voucher_list_flags_shared_registration_ip(): void
    {
        $a = $this->player(1000);
        $b = $this->player(1000);
        $a->forceFill(['registration_ip' => '10.0.0.9'])->save();
        $b->forceFill(['registration_ip' => '10.0.0.9'])->save();

        Sanctum::actingAs($a);
        $this->buy($this->type())->assertCreated();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/vouchers')
            ->assertOk()
            ->assertJsonPath('data.0.user.shared_ip_accounts', 2);
    }
}
