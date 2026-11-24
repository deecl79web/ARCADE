<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\ArcadeTestCase;

class AuthTest extends ArcadeTestCase
{
    private function register(string $email, array $extra = [])
    {
        return $this->postJson('/api/auth/register', array_merge([
            'name' => 'Joueur',
            'email' => $email,
            'password' => 'motdepasse123',
        ], $extra));
    }

    public function test_register_creates_a_player_and_ignores_a_role_in_the_payload(): void
    {
        $this->register('a@test.cm', ['role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('user.role', 'player');

        $user = User::where('email', 'a@test.cm')->firstOrFail();
        $this->assertSame('player', $user->role);
        $this->assertSame('127.0.0.1', $user->registration_ip);
    }

    public function test_signups_per_ip_are_limited(): void
    {
        config(['arcade.max_signups_per_ip_per_day' => 2]);

        $this->register('a@test.cm')->assertCreated();
        $this->register('b@test.cm')->assertCreated();
        $this->register('c@test.cm')->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_signup_limit_can_be_disabled(): void
    {
        config(['arcade.max_signups_per_ip_per_day' => 0]);

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->register("{$name}@test.cm")->assertCreated();
        }
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $this->register('a@test.cm')->assertCreated();

        $this->postJson('/api/auth/login', ['email' => 'a@test.cm', 'password' => 'faux-mot-de-passe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_tokens_have_an_expiration_date(): void
    {
        config(['arcade.token_days' => 30]);

        $this->register('a@test.cm')->assertCreated();

        $token = PersonalAccessToken::firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/vouchers/purchase', ['voucher_type_id' => 1])->assertUnauthorized();
    }

    public function test_change_password_revokes_other_sessions(): void
    {
        $this->register('a@test.cm')->assertCreated();
        $tokenB = $this->postJson('/api/auth/login', ['email' => 'a@test.cm', 'password' => 'motdepasse123'])
            ->assertOk()
            ->json('token');

        $this->assertSame(2, PersonalAccessToken::count());

        $this->withToken($tokenB)
            ->postJson('/api/me/password', [
                'current_password' => 'motdepasse123',
                'password' => 'nouveau-mdp-456',
            ])
            ->assertOk();

        $user = User::where('email', 'a@test.cm')->firstOrFail();
        $this->assertTrue(Hash::check('nouveau-mdp-456', $user->password));
        $this->assertSame(1, PersonalAccessToken::count()); // seul le token utilisé survit
    }
}
