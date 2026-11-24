<?php

namespace App\Http\Controllers;

use App\Services\EconomyService;

class PublicSettingsController extends Controller
{
    public function __construct(private EconomyService $economy) {}

    public function show(): array
    {
        return [
            'interstitial_every_n_games' => $this->economy->interstitialEveryNGames(),
        ];
    }
}
