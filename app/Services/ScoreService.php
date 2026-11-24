<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\ScoreSubmission;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScoreService
{
    public function __construct(private PointsService $points) {}

    /** Démarre une session : retourne [GameSession, jeton en clair]. */
    public function start(User $user, Game $game): array
    {
        // Les anciennes sessions encore actives de ce joueur sur ce jeu sont abandonnées
        GameSession::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('status', 'active')
            ->update(['status' => 'abandoned', 'ended_at' => now()]);

        $token = Str::random(48);

        $session = GameSession::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'token_hash' => hash('sha256', $token),
            'started_at' => now(),
            'status' => 'active',
        ]);

        return [$session, $token];
    }

    /** Valide et enregistre un score. Retourne un tableau de résultat. */
    public function submit(User $user, int $sessionId, string $token, int $score): array
    {
        return DB::transaction(function () use ($user, $sessionId, $token, $score) {
            // Verrou sur le joueur : sérialise les opérations sur son grand livre
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $session = GameSession::whereKey($sessionId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                $this->fail('Session introuvable.', 404);
            }
            if ($session->status !== 'active') {
                $this->fail('Cette partie est déjà terminée.', 422);
            }
            if (! hash_equals($session->token_hash, hash('sha256', $token))) {
                $this->fail('Jeton de session invalide.', 403);
            }

            $game = Game::findOrFail($session->game_id);
            $elapsed = now()->timestamp - $session->started_at->timestamp;

            $flagReason = null;
            if ($elapsed < $game->min_duration_seconds) {
                $flagReason = 'too_fast';
            } elseif ($score > $game->max_score) {
                $flagReason = 'score_too_high';
            }

            $awarded = 0;
            if (! $flagReason) {
                $awarded = intdiv($score * $game->points_per_100_score, 100);
            }

            $submission = ScoreSubmission::create([
                'game_session_id' => $session->id,
                'score' => $score,
                'points_awarded' => $awarded,
                'flagged' => $flagReason !== null,
                'flag_reason' => $flagReason,
            ]);

            if ($awarded > 0) {
                $this->points->add($user->id, $awarded, 'game_score', 'score_submission', $submission->id);
            }

            $session->update([
                'status' => $flagReason ? 'rejected' : 'completed',
                'ended_at' => now(),
            ]);

            return [
                'score' => $score,
                'points_awarded' => $awarded,
                'flagged' => $flagReason !== null,
                'balance' => $this->points->balance($user->id),
            ];
        });
    }

    private function fail(string $message, int $status): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
