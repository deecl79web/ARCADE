<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\NetworkAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** Endpoints publics : choisir une pub à afficher + compter impressions/clics des sponsors. */
class AdController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate([
            'position' => ['required', Rule::in(Ad::POSITIONS)],
        ]);
        $position = $data['position'];

        $sponsor = Ad::live()->where('position', $position)->inRandomOrder()->first();

        $network = in_array($position, NetworkAd::POSITIONS, true)
            ? NetworkAd::where('active', true)->where('position', $position)->inRandomOrder()->first()
            : null;

        // Sponsor et régie en alternance quand les deux existent
        if ($sponsor && $network) {
            $choice = $this->nextIsSponsor($position) ? 'sponsor' : 'network';
        } else {
            $choice = $sponsor ? 'sponsor' : ($network ? 'network' : null);
        }

        if ($choice === null) {
            return response()->noContent();
        }

        if ($choice === 'network') {
            // Le code de la régie n'est JAMAIS envoyé ici : il est servi par /api/ad-frame/{id}
            return response()->json([
                'type' => 'network',
                'id' => $network->id,
                'position' => $position,
                'frame_url' => $network->frame_url,
                'width' => $network->width,
                'height' => $network->height,
            ]);
        }

        return response()->json([
            'type' => 'sponsor',
            'id' => $sponsor->id,
            'title' => $sponsor->title,
            'position' => $sponsor->position,
            'image_url' => $sponsor->image_url,
            'link_url' => $sponsor->link_url,
        ]);
    }

    public function impression(Ad $ad)
    {
        if ($ad->isLive()) {
            $ad->increment('impressions');
        }

        return response()->noContent();
    }

    public function click(Ad $ad)
    {
        if ($ad->isLive()) {
            $ad->increment('clicks');
        }

        return response()->noContent();
    }

    /** Alternance sponsor / régie : un compteur par emplacement. */
    private function nextIsSponsor(string $position): bool
    {
        try {
            $key = "ads:alternate:{$position}";
            Cache::add($key, 0);

            return Cache::increment($key) % 2 === 1;
        } catch (\Throwable) {
            return (bool) random_int(0, 1);
        }
    }
}
