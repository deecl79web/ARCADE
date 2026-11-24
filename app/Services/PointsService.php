<?php

namespace App\Services;

use App\Models\PointsLedger;

class PointsService
{
    public function balance(int $userId): int
    {
        return (int) PointsLedger::where('user_id', $userId)->sum('delta');
    }

    /**
     * À appeler DANS une transaction qui a déjà verrouillé la ligne user (lockForUpdate).
     */
    public function add(int $userId, int $delta, string $reason, ?string $refType = null, ?int $refId = null): PointsLedger
    {
        return PointsLedger::create([
            'user_id' => $userId,
            'delta' => $delta,
            'reason' => $reason,
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
    }
}
