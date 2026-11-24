<?php

namespace App\Http\Controllers;

use App\Models\NetworkAd;
use Illuminate\Http\Request;

/**
 * Sert le code d'une régie dans une page HTML isolée, destinée à un <iframe sandbox>.
 * La directive CSP "sandbox" (sans allow-same-origin) donne à cette page une origine "nulle" :
 * le script de la régie ne peut pas lire le localStorage du site (donc ni le token des joueurs)
 * ni appeler ton API en leur nom — même si quelqu'un ouvre cette URL directement.
 */
class AdFrameController extends Controller
{
    public function show(Request $request, NetworkAd $networkAd)
    {
        abort_unless($networkAd->active, 404);

        if (! $request->boolean('preview')) {
            $networkAd->increment('views');
        }

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<style>html,body{margin:0;padding:0;background:transparent;overflow:hidden}</style>'
            .'</head><body>'.$networkAd->html_code.'</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
