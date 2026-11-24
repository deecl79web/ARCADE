<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:purchased,used']]);

        $vouchers = Voucher::with('user:id,name,email,registration_ip')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('purchased_at')
            ->paginate(20);

        // Signal anti-fraude : combien de comptes partagent l'IP d'inscription de l'acheteur ?
        $ips = $vouchers->getCollection()->pluck('user.registration_ip')->filter()->unique()->values();
        $counts = $ips->isEmpty()
            ? collect()
            : User::whereIn('registration_ip', $ips)
                ->selectRaw('registration_ip, COUNT(*) as c')
                ->groupBy('registration_ip')
                ->pluck('c', 'registration_ip');

        $vouchers->getCollection()->each(function ($voucher) use ($counts) {
            $voucher->user?->setAttribute(
                'shared_ip_accounts',
                (int) ($counts[$voucher->user->registration_ip] ?? 1)
            );
        });

        return $vouchers;
    }
}
