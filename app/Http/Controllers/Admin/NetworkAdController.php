<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkAd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkAdController extends Controller
{
    public function index()
    {
        return NetworkAd::latest('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(creating: true));

        $ad = NetworkAd::create([
            'name' => $data['name'],
            'provider' => $data['provider'] ?? 'Régie',
            'position' => $data['position'],
            'html_code' => $data['html_code'],
            'width' => $data['width'],
            'height' => $data['height'],
            'active' => $request->boolean('active', true),
        ]);

        return response()->json($ad, 201);
    }

    public function update(Request $request, NetworkAd $networkAd)
    {
        $data = $request->validate($this->rules(creating: false));

        $networkAd->update($data);

        return $networkAd;
    }

    public function destroy(NetworkAd $networkAd)
    {
        $networkAd->delete();

        return response()->noContent();
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:100'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:50'],
            'position' => [$req, Rule::in(NetworkAd::POSITIONS)],
            'html_code' => [$req, 'string', 'max:10000'],
            'width' => [$req, 'integer', 'min:50', 'max:1200'],
            'height' => [$req, 'integer', 'min:20', 'max:900'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
