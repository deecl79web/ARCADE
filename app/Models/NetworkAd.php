<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkAd extends Model
{
    /**
     * Seulement les bannières. Les emplacements plein écran (début de partie, seconde chance)
     * restent réservés aux sponsors directs : on ne peut pas contrôler ce qu'une régie y affiche.
     */
    public const POSITIONS = ['arcade_list', 'game_below', 'account', 'game_over'];

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    protected $appends = ['frame_url'];

    public function getFrameUrlAttribute(): string
    {
        return url("/api/ad-frame/{$this->id}");
    }
}
