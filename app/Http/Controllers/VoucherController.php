<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\EconomyService;
use App\Services\PointsService;
use App\Services\VoucherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $vouchers,
        private PointsService $points,
        private EconomyService $economy,
    ) {}

    /** Catalogue des types de bons actifs, avec leur disponibilité (budget) mais sans chiffres financiers. */
    public function types()
    {
        return VoucherType::where('active', true)->orderBy('cost_points')
            ->get(['id', 'label', 'value_fcfa', 'cost_points', 'validity_days', 'min_purchase_fcfa'])
            ->map(fn ($type) => $type->toArray() + ['available' => $this->economy->canIssue($type->value_fcfa)])
            ->values();
    }

    public function purchase(Request $request)
    {
        $data = $request->validate(['voucher_type_id' => ['required', 'integer', 'exists:voucher_types,id']]);

        $voucher = $this->vouchers->purchase($request->user(), VoucherType::findOrFail($data['voucher_type_id']));

        return response()->json([
            'voucher' => $voucher,
            'balance' => $this->points->balance($request->user()->id),
        ], 201);
    }

    public function mine(Request $request)
    {
        return $request->user()->vouchers()->latest('purchased_at')->get();
    }

    public function pdf(Request $request, string $uuid)
    {
        // Le joueur ne peut télécharger que SES bons
        $voucher = Voucher::with('user:id,name')
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $qr = new QrCode(
            data: $this->vouchers->qrPayload($voucher),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 10,
        );
        $qrDataUri = (new PngWriter)->write($qr)->getDataUri();

        return Pdf::loadView('vouchers.pdf', [
            'voucher' => $voucher,
            'qr' => $qrDataUri,
        ])->setPaper('a5')->download('bon-'.substr($voucher->uuid, 0, 8).'.pdf');
    }
}
