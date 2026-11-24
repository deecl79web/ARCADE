<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VoucherType;
use Illuminate\Http\Request;

class VoucherTypeController extends Controller
{
    public function index()
    {
        return VoucherType::orderBy('cost_points')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'value_fcfa' => ['required', 'integer', 'min:1'],
            'cost_points' => ['required', 'integer', 'min:1'],
            'min_purchase_fcfa' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'active' => ['sometimes', 'boolean'],
        ]);

        return response()->json(VoucherType::create($data), 201);
    }

    public function update(Request $request, VoucherType $voucherType)
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'value_fcfa' => ['sometimes', 'integer', 'min:1'],
            'cost_points' => ['sometimes', 'integer', 'min:1'],
            'min_purchase_fcfa' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'validity_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $voucherType->update($data);

        return $voucherType;
    }
}
