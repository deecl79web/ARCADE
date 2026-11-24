<?php

namespace App\Http\Controllers;

use App\Models\Game;

class GameController extends Controller
{
    private const PUBLIC_FIELDS = [
        'id', 'slug', 'title', 'description', 'thumbnail_url', 'entry_url',
        'points_per_100_score',
    ];

    public function index()
    {
        return Game::where('active', true)->orderBy('title')->get(self::PUBLIC_FIELDS);
    }

    public function show(string $slug)
    {
        return Game::where('active', true)->where('slug', $slug)->firstOrFail(self::PUBLIC_FIELDS);
    }
}
