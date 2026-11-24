<?php

namespace App\Http\Controllers;

use App\Services\PointsService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private PointsService $points) {}

    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json($user->only(['id', 'name', 'email', 'role']) + [
            'points_balance' => $this->points->balance($user->id),
        ]);
    }

    public function points(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'balance' => $this->points->balance($user->id),
            'history' => $user->pointsLedger()->latest('id')->limit(50)
                ->get(['id', 'delta', 'reason', 'created_at']),
        ]);
    }
}
