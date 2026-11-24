<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherScan;
use App\Models\VoucherType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(private PointsService $points, private EconomyService $economy) {}

    public function sign(string $uuid): string
    {
        return hash_hmac('sha256', $uuid, config('arcade.voucher_secret'));
    }

    /** Contenu du QR : "<uuid>.<signature HMAC>" */
    public function qrPayload(Voucher $voucher): string
    {
        return $voucher->uuid.'.'.$voucher->signature;
    }

    public function purchase(User $user, VoucherType $requestedType): Voucher
    {
        return DB::transaction(function () use ($user, $requestedType) {
            // Le budget de bons est global : on sérialise tous les achats avant de regarder le solde
            $this->economy->lockBudget();

            // Verrou sur le joueur : empêche deux achats simultanés avec le même solde
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $this->assertCanPurchase($locked);

            $type = VoucherType::whereKey($requestedType->id)->where('active', true)->first();
            if (! $type) {
                throw ValidationException::withMessages(['voucher_type_id' => "Ce type de bon n'est plus disponible."]);
            }

            if ($this->points->balance($user->id) < $type->cost_points) {
                throw ValidationException::withMessages(['points' => 'Solde de points insuffisant.']);
            }

            if (! $this->economy->canIssue($type->value_fcfa)) {
                throw ValidationException::withMessages([
                    'budget' => 'Les bons sont momentanément indisponibles. Reviens bientôt !',
                ]);
            }

            $uuid = (string) Str::uuid();

            $voucher = Voucher::create([
                'uuid' => $uuid,
                'user_id' => $user->id,
                'voucher_type_id' => $type->id,
                'label' => $type->label,
                'value_fcfa' => $type->value_fcfa,
                'cost_points' => $type->cost_points,
                'min_purchase_fcfa' => $type->min_purchase_fcfa,
                'status' => 'purchased',
                'purchased_at' => now(),
                'expires_at' => $type->validity_days ? now()->addDays($type->validity_days) : null,
                'signature' => $this->sign($uuid),
            ]);

            $this->points->add($user->id, -$type->cost_points, 'voucher_purchase', 'voucher', $voucher->id);

            return $voucher;
        });
    }

    /** Règles anti-abus : ancienneté du compte + plafond d'achats sur 24 h. */
    private function assertCanPurchase(User $user): void
    {
        $minHours = (int) config('arcade.voucher_min_account_age_hours');
        if ($minHours > 0) {
            $eligibleAt = $user->created_at->copy()->addHours($minHours);
            if ($eligibleAt->isFuture()) {
                throw ValidationException::withMessages([
                    'account' => 'Pour éviter les abus, tu pourras acheter un bon à partir du '
                        .$eligibleAt->format('d/m/Y à H:i').'.',
                ]);
            }
        }

        $max = (int) config('arcade.voucher_max_per_day');
        if ($max > 0) {
            $recent = Voucher::where('user_id', $user->id)
                ->where('purchased_at', '>=', now()->subDay())
                ->count();

            if ($recent >= $max) {
                throw ValidationException::withMessages([
                    'voucher_type_id' => "Limite atteinte : {$max} bon(s) maximum par 24 h.",
                ]);
            }
        }
    }

    /**
     * Scan d'un bon par un admin.
     * Retourne ['result' => string, 'voucher' => ?Voucher].
     */
    public function scan(User $admin, string $payload): array
    {
        $payload = trim($payload);
        $parts = explode('.', $payload);

        if (count($parts) !== 2 || ! Str::isUuid($parts[0])) {
            return $this->logScan($admin, null, $payload, 'invalid_format');
        }

        [$uuid, $signature] = $parts;

        if (! hash_equals($this->sign($uuid), $signature)) {
            return $this->logScan($admin, null, $payload, 'invalid_signature');
        }

        $voucher = Voucher::with('user:id,name')->where('uuid', $uuid)->first();
        if (! $voucher) {
            return $this->logScan($admin, null, $payload, 'not_found');
        }

        // Marquage ATOMIQUE : un seul scan peut faire passer purchased -> used
        $affected = Voucher::where('id', $voucher->id)
            ->where('status', 'purchased')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->update([
                'status' => 'used',
                'used_at' => now(),
                'used_by_admin_id' => $admin->id,
            ]);

        $voucher->refresh()->load('user:id,name');

        if ($affected === 1) {
            return $this->logScan($admin, $voucher, $payload, 'ok');
        }

        $result = $voucher->status === 'used' ? 'already_used' : 'expired';

        return $this->logScan($admin, $voucher, $payload, $result);
    }

    private function logScan(User $admin, ?Voucher $voucher, string $payload, string $result): array
    {
        VoucherScan::create([
            'voucher_id' => $voucher?->id,
            'admin_id' => $admin->id,
            'payload' => Str::limit($payload, 250, ''),
            'result' => $result,
            'scanned_at' => now(),
        ]);

        return ['result' => $result, 'voucher' => $voucher];
    }
}
