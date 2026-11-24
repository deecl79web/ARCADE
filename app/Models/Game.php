<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    // Les jeux uploadés sont stockés en chemin relatif ("/api/play/...") et
    // exposés en URL absolue : le domaine peut changer sans migration.
    protected function entryUrl(): Attribute
    {
        return Attribute::get(
            fn (?string $value) => $value && str_starts_with($value, '/api/play/') ? url($value) : $value
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(
            fn (?string $value) => $value && str_starts_with($value, '/games/') ? url($value) : $value
        );
    }

    public function sessions()
    {
        return $this->hasMany(GameSession::class);
    }
}
