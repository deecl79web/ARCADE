<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\ScoreService;
use Illuminate\Http\Request;

class GameSessionController extends Controller
{
    public function __construct(private ScoreService $scores) {}

    public function store(Request $request)
    {
        $data = $request->validate(['game_id' => ['required', 'integer', 'exists:games,id']]);

        $game = Game::where('active', true)->findOrFail($data['game_id']);

        [$session, $token] = $this->scores->start($request->user(), $game);

        return response()->json([
            'session_id' => $session->id,
            'token' => $token,
        ], 201);
    }

    public function score(Request $request, int $id)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'score' => ['required', 'integer', 'min:0', 'max:2000000000'],
        ]);

        return response()->json(
            $this->scores->submit($request->user(), $id, $data['token'], (int) $data['score'])
        );
    }
}
