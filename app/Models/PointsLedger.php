<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Grand livre des points : ajout seul.
 */
class PointsLedger extends Model
{
    protected $table = 'points_ledger';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['delta' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('points_ledger est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('points_ledger est en ajout seul.'));
    }
}
