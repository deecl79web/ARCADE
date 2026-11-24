<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherScanController extends Controller
{
    private const MESSAGES = [
        'ok' => 'Bon valide : marqué comme utilisé.',
        'already_used' => 'Ce bon a déjà été utilisé.',
        'expired' => 'Ce bon est expiré.',
        'invalid_format' => 'QR code non reconnu.',
        'invalid_signature' => 'QR code falsifié ou invalide.',
        'not_found' => 'Bon introuvable.',
    ];

    public function __construct(private VoucherService $vouchers) {}

    public function store(Request $request)
    {
        $data = $request->validate(['payload' => ['required', 'string', 'max:500']]);

        ['result' => $result, 'voucher' => $voucher] = $this->vouchers->scan($request->user(), $data['payload']);

        return response()->json([
            'result' => $result,
            'message' => self::MESSAGES[$result],
            'voucher' => $voucher ? [
                'uuid' => $voucher->uuid,
                'label' => $voucher->label,
                'value_fcfa' => $voucher->value_fcfa,
                'min_purchase_fcfa' => $voucher->min_purchase_fcfa,
                'client' => $voucher->user?->name,
                'status' => $voucher->status,
                'used_at' => $voucher->used_at,
                'expires_at' => $voucher->expires_at,
            ] : null,
        ], $result === 'ok' ? 200 : 422);
    }
}
