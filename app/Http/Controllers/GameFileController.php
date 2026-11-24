<?php

namespace App\Http\Controllers;

/**
 * Sert les fichiers d'un jeu avec les en-têtes CORS nécessaires.
 * Indispensable : l'iframe est en sandbox SANS allow-same-origin, donc son origine est "null"
 * et le navigateur exige "Access-Control-Allow-Origin" pour les modules JS, fetch(), textures WebGL...
 */
class GameFileController extends Controller
{
    private const MIME = [
        'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'json' => 'application/json', 'map' => 'application/json',
        'txt' => 'text/plain; charset=utf-8', 'xml' => 'application/xml', 'csv' => 'text/csv',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
        'wasm' => 'application/wasm', 'glb' => 'model/gltf-binary', 'gltf' => 'model/gltf+json',
    ];

    public function show(string $slug, string $version, ?string $path = null)
    {
        abort_unless(
            preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) && preg_match('/^v\d+$/', $version),
            404
        );

        $path = ($path === null || $path === '') ? 'index.html' : $path;

        $base = realpath(public_path("games/{$slug}/{$version}"));
        abort_if($base === false, 404);

        $file = realpath($base.DIRECTORY_SEPARATOR.$path);
        abort_unless(
            $file !== false && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file),
            404
        );

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $headers = [
            'Content-Type' => self::MIME[$ext] ?? 'application/octet-stream',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400', // URL versionnée (v1, v2...)
        ];

        // Si quelqu'un ouvre la page directement (hors iframe), elle reste isolée
        if ($ext === 'html' || $ext === 'htm') {
            $headers['Content-Security-Policy'] = 'sandbox allow-scripts allow-fullscreen';
        }

        return response()->file($file, $headers);
    }
}
