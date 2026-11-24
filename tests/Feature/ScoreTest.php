<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\PointsLedger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\ArcadeTestCase;

class ScoreTest extends ArcadeTestCase
{
    private function game(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'slug' => 'test-game',
            'title' => 'Test',
            'entry_url' => '/games/test/index.html',
            'max_score' => 1000,
            'points_per_100_score' => 10,
            'min_duration_seconds' => 10,
        ], $overrides));
    }

    private function start(Game $game): array
    {
        return $this->postJson('/api/game-sessions', ['game_id' => $game->id])
            ->assertCreated()
            ->json();
    }

    private function submit(array $session, int $score, ?string $token = null)
    {
        return $this->postJson("/api/game-sessions/{$session['session_id']}/score", [
            'token' => $token ?? $session['token'],
            'score' => $score,
        ]);
    }

    private function balance(User $user): int
    {
        return (int) PointsLedger::where('user_id', $user->id)->sum('delta');
    }

    public function test_valid_score_awards_points(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->start($this->game());

        $this->travel(15)->seconds();

        $this->submit($session, 300)
            ->assertOk()
            ->assertJson(['points_awarded' => 30, 'flagged' => false, 'balance' => 30]);

        $this->assertSame(30, $this->balance($user));
    }

    public function test_game_that_is_too_fast_is_flagged_and_gives_nothing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->start($this->game());

        $this->submit($session, 300)
            ->assertOk()
            ->assertJson(['flagged' => true, 'points_awarded' => 0]);

        $this->assertDatabaseHas('score_submissions', ['flag_reason' => 'too_fast', 'flagged' => true]);
        $this->assertSame(0, $this->balance($user));
    }

    public function test_score_above_maximum_is_flagged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->start($this->game());

        $this->travel(15)->seconds();

        $this->submit($session, 5000)
            ->assertOk()
            ->assertJson(['flagged' => true, 'points_awarded' => 0]);

        $this->assertDatabaseHas('score_submissions', ['flag_reason' => 'score_too_high']);
        $this->assertSame(0, $this->balance($user));
    }

    public function test_a_session_cannot_be_submitted_twice(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $session = $this->start($this->game());

        $this->travel(15)->seconds();

        $this->submit($session, 300)->assertOk();
        $this->submit($session, 300)->assertStatus(422);
    }

    public function test_wrong_token_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $session = $this->start($this->game());

        $this->travel(15)->seconds();

        $this->submit($session, 300, 'mauvais-jeton')->assertForbidden();
        $this->assertSame(0, $this->balance($user));
    }

    public function test_another_players_session_cannot_be_used(): void
    {
        $game = $this->game();

        Sanctum::actingAs(User::factory()->create());
        $session = $this->start($game);

        $this->travel(15)->seconds();

        Sanctum::actingAs(User::factory()->create());
        $this->submit($session, 300)->assertNotFound();
    }

    public function test_three_consecutive_valid_games_all_award_points(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $game = $this->game(['daily_cap' => null]);

        for ($i = 0; $i < 3; $i++) {
            $session = $this->start($game);
            $this->travel(15)->seconds();
            $this->submit($session, 800)
                ->assertOk()
                ->assertJson(['points_awarded' => 80]);
        }

        $this->assertSame(240, $this->balance($user));
    }

    public function test_inactive_game_cannot_be_started(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $game = $this->game(['active' => false]);

        $this->postJson('/api/game-sessions', ['game_id' => $game->id])->assertNotFound();
    }
}
