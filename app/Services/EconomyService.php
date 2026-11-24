<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\GameSession;
use App\Models\NetworkAd;
use App\Models\PointsLedger;
use App\Models\RevenueEntry;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use Illuminate\Support\Facades\DB;

/**
 * Règle : budget de bons = revenus publicitaires déclarés × pourcentage (50 % par défaut).
 * Pour 500 FCFA de réduction offerts, il faut avoir encaissé 1 000 FCFA de revenus.
 */
class EconomyService
{
    public function percent(): int
    {
        return max(1, min(100, (int) Setting::getValue('budget_percent', 50)));
    }

    public function gateEnabled(): bool
    {
        return (string) Setting::getValue('budget_gate_enabled', '1') === '1';
    }

    public function setPercent(int $percent): void
    {
        Setting::putValue('budget_percent', $percent);
    }

    public function setGate(bool $enabled): void
    {
        Setting::putValue('budget_gate_enabled', $enabled ? '1' : '0');
    }

    public function interstitialEveryNGames(): int
    {
        return max(1, (int) Setting::getValue('interstitial_every_n_games', 1));
    }

    public function setInterstitialEveryNGames(int $games): void
    {
        Setting::putValue('interstitial_every_n_games', max(1, $games));
    }

    public function revenueTotal(): int
    {
        return (int) RevenueEntry::sum('amount_fcfa');
    }

    public function budget(): int
    {
        return intdiv($this->revenueTotal() * $this->percent(), 100);
    }

    /** Valeur des bons qui consomment le budget : utilisés + achetés et non expirés. */
    public function engaged(): int
    {
        return (int) Voucher::query()
            ->where(function ($q) {
                $q->where('status', 'used')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'purchased')
                            ->where(fn ($q3) => $q3->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                    });
            })
            ->sum('value_fcfa');
    }

    public function remaining(): int
    {
        return max(0, $this->budget() - $this->engaged());
    }

    public function canIssue(int $valueFcfa): bool
    {
        return ! $this->gateEnabled() || $valueFcfa <= $this->remaining();
    }

    /** Sérialise tous les achats de bons : le budget est global, pas par joueur. */
    public function lockBudget(): void
    {
        DB::table('settings')->where('key', 'budget_lock')->lockForUpdate()->first();
    }

    /** Tableau de bord de l'admin. */
    public function snapshot(): array
    {
        $revenue = $this->revenueTotal();
        $percent = $this->percent();
        $budget = intdiv($revenue * $percent, 100);
        $engaged = $this->engaged();

        $usedValue = (int) Voucher::where('status', 'used')->sum('value_fcfa');
        $outstanding = (int) Voucher::where('status', 'purchased')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('value_fcfa');
        $released = (int) Voucher::where('status', 'purchased')
            ->where('expires_at', '<=', now())
            ->sum('value_fcfa');

        $distributed = (int) PointsLedger::where('reason', 'game_score')->sum('delta');
        $spent = abs((int) PointsLedger::where('reason', 'voucher_purchase')->sum('delta'));
        $circulation = (int) PointsLedger::sum('delta');
        $sessions = GameSession::where('status', 'completed')->count();

        $averagePointsPerGame = $sessions > 0
            ? $distributed / $sessions
            : null;

        $revenuePerPoint = $distributed > 0 ? $revenue / $distributed : null;
        $revenuePerSession = $sessions > 0 ? $revenue / $sessions : null;

        $types = VoucherType::orderBy('cost_points')->get()->map(function ($type) use ($revenuePerPoint, $percent, $averagePointsPerGame) {
            $needed = $type->value_fcfa * 100 / $percent;
            $recommended = ($revenuePerPoint && $revenuePerPoint > 0)
                ? (int) ceil(round($needed / $revenuePerPoint, 6))
                : null;
            $gamesNeeded = $averagePointsPerGame && $averagePointsPerGame > 0
                ? (int) ceil(round($type->cost_points / $averagePointsPerGame, 6))
                : null;

            return [
                'id' => $type->id,
                'label' => $type->label,
                'value_fcfa' => $type->value_fcfa,
                'cost_points' => $type->cost_points,
                'min_purchase_fcfa' => $type->min_purchase_fcfa,
                'active' => (bool) $type->active,
                'recommended_cost_points' => $recommended,
                'games_needed' => $gamesNeeded,
                'underpriced' => $recommended !== null && $type->cost_points < $recommended,
            ];
        })->values();

        $sponsorImpressions = (int) Ad::sum('impressions');
        $networkViews = (int) NetworkAd::sum('views');
        $adDisplays = $sponsorImpressions + $networkViews;
        $displaysPerCompletedGame = $sessions > 0 ? $adDisplays / $sessions : null;
        $revenuePerAdDisplay = $adDisplays > 0 ? $revenue / $adDisplays : null;

        $topGains = PointsLedger::query()
            ->where('reason', 'game_score')
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('user_id, SUM(delta) as points_gained, COUNT(*) as games_played')
            ->groupBy('user_id')
            ->orderByDesc('points_gained')
            ->limit(10)
            ->get();

        $users = User::whereIn('id', $topGains->pluck('user_id'))->get(['id', 'name', 'registration_ip'])->keyBy('id');
        $ips = $users->pluck('registration_ip')->filter()->unique()->values();
        $sharedIpCounts = $ips->isEmpty()
            ? collect()
            : User::whereIn('registration_ip', $ips)
                ->selectRaw('registration_ip, COUNT(*) as accounts')
                ->groupBy('registration_ip')
                ->pluck('accounts', 'registration_ip');

        $topGainsData = $topGains->map(function ($row) use ($users, $sharedIpCounts) {
            $user = $users->get($row->user_id);
            $ip = $user?->registration_ip;

            return [
                'user_id' => (int) $row->user_id,
                'name' => $user?->name,
                'points_gained' => (int) $row->points_gained,
                'games_played' => (int) $row->games_played,
                'shared_ip_accounts' => $ip ? (int) ($sharedIpCounts[$ip] ?? 1) : 1,
            ];
        })->values();

        return [
            'settings' => [
                'budget_percent' => $percent,
                'gate_enabled' => $this->gateEnabled(),
                'interstitial_every_n_games' => $this->interstitialEveryNGames(),
            ],
            'revenue_total_fcfa' => $revenue,
            'budget_fcfa' => $budget,
            'engaged_fcfa' => $engaged,
            'remaining_fcfa' => max(0, $budget - $engaged),
            'used_value_fcfa' => $usedValue,
            'outstanding_value_fcfa' => $outstanding,
            'released_value_fcfa' => $released,
            'points' => ['distributed' => $distributed, 'spent' => $spent, 'in_circulation' => $circulation],
            'sessions_completed' => $sessions,
            'average_points_per_completed_game' => $averagePointsPerGame !== null ? round($averagePointsPerGame, 2) : null,
            'revenue_per_point_fcfa' => $revenuePerPoint !== null ? round($revenuePerPoint, 4) : null,
            'revenue_per_session_fcfa' => $revenuePerSession !== null ? round($revenuePerSession, 4) : null,
            'ad_displays' => [
                'total' => $adDisplays,
                'sponsor_impressions' => $sponsorImpressions,
                'network_views' => $networkViews,
                'per_completed_game' => $displaysPerCompletedGame !== null ? round($displaysPerCompletedGame, 4) : null,
                'revenue_per_display_fcfa' => $revenuePerAdDisplay !== null ? round($revenuePerAdDisplay, 4) : null,
            ],
            'top_gains_24h' => $topGainsData,
            'voucher_types' => $types,
        ];
    }
}
