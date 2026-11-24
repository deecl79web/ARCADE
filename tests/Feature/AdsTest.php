<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\NetworkAd;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\ArcadeTestCase;

class AdsTest extends ArcadeTestCase
{
    private function ad(array $overrides = []): Ad
    {
        return Ad::create(array_merge([
            'title' => 'Sponsor',
            'position' => 'game_start',
            'image_path' => '/ads/test.png',
            'link_url' => 'https://example.com',
            'active' => true,
        ], $overrides));
    }

    private function slot(array $overrides = []): NetworkAd
    {
        return NetworkAd::create(array_merge([
            'name' => 'Monetag test',
            'provider' => 'Monetag',
            'position' => 'arcade_list',
            'html_code' => '<script>/* zone */</script>',
            'width' => 320,
            'height' => 100,
            'active' => true,
        ], $overrides));
    }

    // ---------- Sponsors directs ----------

    public function test_only_live_ads_of_the_requested_position_are_served(): void
    {
        $live = $this->ad(['title' => 'Live']);
        $this->ad(['title' => 'Désactivée', 'active' => false]);
        $this->ad(['title' => 'Expirée', 'ends_at' => now()->subDay()]);
        $this->ad(['title' => 'Future', 'starts_at' => now()->addDay()]);
        $this->ad(['title' => 'Autre position', 'position' => 'account']);

        $this->getJson('/api/ads?position=game_start')
            ->assertOk()
            ->assertJsonPath('type', 'sponsor')
            ->assertJsonPath('id', $live->id)
            ->assertJsonPath('link_url', 'https://example.com');
    }

    public function test_no_ad_returns_no_content(): void
    {
        $this->getJson('/api/ads?position=second_chance')->assertNoContent();
    }

    public function test_unknown_position_is_rejected(): void
    {
        $this->getJson('/api/ads?position=partout')->assertStatus(422);
    }

    public function test_impressions_and_clicks_are_counted_only_for_live_ads(): void
    {
        $live = $this->ad();
        $off = $this->ad(['active' => false]);

        $this->postJson("/api/ads/{$live->id}/impression")->assertNoContent();
        $this->postJson("/api/ads/{$live->id}/impression")->assertNoContent();
        $this->postJson("/api/ads/{$live->id}/click")->assertNoContent();
        $this->postJson("/api/ads/{$off->id}/impression")->assertNoContent();

        $this->assertSame(2, $live->fresh()->impressions);
        $this->assertSame(1, $live->fresh()->clicks);
        $this->assertSame(0, $off->fresh()->impressions);
    }

    public function test_only_admins_can_create_ads(): void
    {
        $payload = [
            'title' => 'Boutique X',
            'position' => 'arcade_list',
            'link_url' => 'https://example.com/promo',
            'image' => UploadedFile::fake()->image('banniere.png', 728, 90),
        ];

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/ads', $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/admin/ads', $payload)
            ->assertCreated()
            ->assertJsonPath('position', 'arcade_list');

        // nettoyage du fichier réellement écrit dans public/ads
        $ad = Ad::firstOrFail();
        @unlink(public_path(ltrim($ad->image_path, '/')));
    }

    public function test_dangerous_link_schemes_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/admin/ads', [
            'title' => 'Piège',
            'position' => 'arcade_list',
            'link_url' => 'javascript:alert(1)',
            'image' => UploadedFile::fake()->image('b.png', 728, 90),
        ])->assertStatus(422)->assertJsonValidationErrors('link_url');

        $this->assertDatabaseCount('ad_placements', 0);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/admin/ads', [
            'title' => 'Dates inversées',
            'position' => 'arcade_list',
            'link_url' => 'https://example.com',
            'starts_at' => '2026-12-10T10:00:00Z',
            'ends_at' => '2026-12-01T10:00:00Z',
            'image' => UploadedFile::fake()->image('b.png', 728, 90),
        ])->assertStatus(422)->assertJsonValidationErrors('ends_at');

        $this->assertDatabaseCount('ad_placements', 0);
    }

    // ---------- Régie (Monetag...) ----------

    public function test_network_slot_is_served_when_there_is_no_sponsor(): void
    {
        $slot = $this->slot();

        $this->getJson('/api/ads?position=arcade_list')
            ->assertOk()
            ->assertJsonPath('type', 'network')
            ->assertJsonPath('id', $slot->id)
            ->assertJsonPath('width', 320)
            ->assertJsonPath('height', 100)
            ->assertJsonStructure(['frame_url'])
            ->assertJsonMissingPath('html_code'); // le code de la régie n'est jamais exposé ici
    }

    public function test_sponsor_and_network_alternate(): void
    {
        $this->ad(['position' => 'arcade_list']);
        $this->slot();

        $types = [];
        for ($i = 0; $i < 4; $i++) {
            $types[] = $this->getJson('/api/ads?position=arcade_list')->assertOk()->json('type');
        }

        $this->assertSame(['sponsor', 'network', 'sponsor', 'network'], $types);
    }

    public function test_inactive_network_slot_is_not_served(): void
    {
        $this->slot(['active' => false]);

        $this->getJson('/api/ads?position=arcade_list')->assertNoContent();
    }

    public function test_network_slots_are_never_used_for_fullscreen_positions(): void
    {
        // Même si une ligne existe en base, game_start / second_chance restent réservés aux sponsors
        $this->slot(['position' => 'game_start']);
        $this->slot(['position' => 'second_chance']);

        $this->getJson('/api/ads?position=game_start')->assertNoContent();
        $this->getJson('/api/ads?position=second_chance')->assertNoContent();
    }

    public function test_ad_frame_is_sandboxed_counts_views_and_hides_inactive_slots(): void
    {
        $slot = $this->slot(['html_code' => '<p id="x">bonjour-regie</p>']);

        $response = $this->get("/api/ad-frame/{$slot->id}")->assertOk();

        $this->assertStringContainsString('bonjour-regie', $response->getContent());
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertStringNotContainsString('allow-same-origin', $csp);
        $this->assertSame(1, $slot->fresh()->views);

        // L'aperçu admin ne compte pas
        $this->get("/api/ad-frame/{$slot->id}?preview=1")->assertOk();
        $this->assertSame(1, $slot->fresh()->views);

        $off = $this->slot(['active' => false]);
        $this->get("/api/ad-frame/{$off->id}")->assertNotFound();
    }

    public function test_only_admins_manage_network_slots(): void
    {
        $payload = [
            'name' => 'Monetag - liste',
            'provider' => 'Monetag',
            'position' => 'arcade_list',
            'html_code' => '<script>/* zone */</script>',
            'width' => 320,
            'height' => 100,
        ];

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/network-ads', $payload)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson('/api/admin/network-ads', $payload)
            ->assertCreated()
            ->assertJsonPath('position', 'arcade_list')
            ->json('id');

        $this->putJson("/api/admin/network-ads/{$id}", ['active' => false])
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->deleteJson("/api/admin/network-ads/{$id}")->assertNoContent();
        $this->assertDatabaseCount('network_ads', 0);
    }

    public function test_network_slot_validation(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $base = [
            'name' => 'Test',
            'position' => 'arcade_list',
            'html_code' => '<script></script>',
            'width' => 320,
            'height' => 100,
        ];

        $this->postJson('/api/admin/network-ads', array_merge($base, ['position' => 'game_start']))
            ->assertStatus(422)->assertJsonValidationErrors('position');

        $this->postJson('/api/admin/network-ads', array_merge($base, ['html_code' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('html_code');

        $this->postJson('/api/admin/network-ads', array_merge($base, ['width' => 5000]))
            ->assertStatus(422)->assertJsonValidationErrors('width');

        $this->assertDatabaseCount('network_ads', 0);
    }
}
