<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RevenueEntry;
use App\Services\EconomyService;
use Illuminate\Http\Request;

class EconomyController extends Controller
{
    public function __construct(private EconomyService $economy) {}

    public function show()
    {
        return response()->json($this->payload());
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'budget_percent' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'gate_enabled' => ['sometimes', 'boolean'],
            'interstitial_every_n_games' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if (array_key_exists('budget_percent', $data)) {
            $this->economy->setPercent((int) $data['budget_percent']);
        }
        if (array_key_exists('gate_enabled', $data)) {
            $this->economy->setGate($request->boolean('gate_enabled'));
        }
        if (array_key_exists('interstitial_every_n_games', $data)) {
            $this->economy->setInterstitialEveryNGames((int) $data['interstitial_every_n_games']);
        }

        return response()->json($this->payload());
    }

    public function storeRevenue(Request $request)
    {
        $data = $request->validate([
            'amount_fcfa' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'source' => ['nullable', 'string', 'max:50'],
            'received_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = RevenueEntry::create([
            'amount_fcfa' => $data['amount_fcfa'],
            'source' => $data['source'] ?? 'Régie',
            'received_on' => $data['received_on'] ?? now()->toDateString(),
            'note' => $data['note'] ?? null,
        ]);

        return response()->json($entry, 201);
    }

    public function destroyRevenue(RevenueEntry $revenueEntry)
    {
        $revenueEntry->delete();

        return response()->noContent();
    }

    private function payload(): array
    {
        return $this->economy->snapshot() + [
            'revenue_entries' => RevenueEntry::orderByDesc('received_on')->orderByDesc('id')->limit(50)->get(),
        ];
    }
}
