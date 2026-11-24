<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    public const POSITIONS = [
        'arcade_list',   // bannière en haut de la liste des jeux
        'game_below',    // bannière sous le jeu
        'account',       // bannière sur « Mon espace » et la page des bons
        'game_start',    // plein écran avant chaque partie
        'game_over',     // bannière sous le score en fin de partie
        'second_chance', // plein écran pour continuer la partie
    ];

    protected $table = 'ad_placements';

    protected $guarded = [];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url'];

    protected $casts = [
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function getImageUrlAttribute(): string
    {
        return url($this->image_path);
    }

    public function scopeLive($query)
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isLive(): bool
    {
        return $this->active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gte(now()));
    }
}
