<?php

use App\Http\Controllers\AdController;
use App\Http\Controllers\AdFrameController;
use App\Http\Controllers\Admin\AdController as AdminAdController;
use App\Http\Controllers\Admin\EconomyController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\NetworkAdController as AdminNetworkAdController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\VoucherScanController;
use App\Http\Controllers\Admin\VoucherTypeController as AdminVoucherTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameFileController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PublicSettingsController;
use App\Http\Controllers\VoucherController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// --- Auth ---
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// --- Public ---
Route::get('games', [GameController::class, 'index']);
Route::get('games/{slug}', [GameController::class, 'show']);

// Publicités : choix d'une annonce (sponsor ou régie) + compteurs sponsors
Route::get('ads', [AdController::class, 'show'])->middleware('throttle:120,1');
Route::post('ads/{ad}/impression', [AdController::class, 'impression'])->middleware('throttle:120,1');
Route::post('ads/{ad}/click', [AdController::class, 'click'])->middleware('throttle:60,1');

// Page isolée (CSP sandbox) qui contient le code d'une régie, pour un <iframe>
Route::get('ad-frame/{networkAd}', [AdFrameController::class, 'show'])->middleware('throttle:240,1');

// Fichiers des jeux uploadés (servis avec CORS pour l'iframe sandbox)
Route::get('play/{slug}/{version}/{path?}', [GameFileController::class, 'show'])->where('path', '.*');

// --- Public Settings ---
Route::get('settings/public', [PublicSettingsController::class, 'show']);

// --- Joueur connecté ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::get('me/points', [MeController::class, 'points']);
    Route::get('me/vouchers', [VoucherController::class, 'mine']);
    Route::post('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');

    Route::post('game-sessions', [GameSessionController::class, 'store'])->middleware('throttle:20,1');
    Route::post('game-sessions/{id}/score', [GameSessionController::class, 'score'])->middleware('throttle:30,1');

    Route::get('voucher-types', [VoucherController::class, 'types']);
    Route::post('vouchers/purchase', [VoucherController::class, 'purchase'])->middleware('throttle:20,1');
    Route::get('vouchers/{uuid}/pdf', [VoucherController::class, 'pdf']);

    // --- Admin ---
    Route::prefix('admin')->middleware(EnsureAdmin::class)->group(function () {
        Route::post('vouchers/scan', [VoucherScanController::class, 'store'])->middleware('throttle:60,1');
        Route::get('vouchers', [AdminVoucherController::class, 'index']);
        Route::get('voucher-types', [AdminVoucherTypeController::class, 'index']);
        Route::post('voucher-types', [AdminVoucherTypeController::class, 'store']);
        Route::put('voucher-types/{voucherType}', [AdminVoucherTypeController::class, 'update']);

        Route::get('games', [AdminGameController::class, 'index']);
        Route::post('games', [AdminGameController::class, 'store']);
        Route::put('games/{game}', [AdminGameController::class, 'update']);
        Route::post('games/{game}/files', [AdminGameController::class, 'files']);
        Route::delete('games/{game}', [AdminGameController::class, 'destroy']);

        Route::get('ads', [AdminAdController::class, 'index']);
        Route::post('ads', [AdminAdController::class, 'store']);
        Route::put('ads/{ad}', [AdminAdController::class, 'update']);
        Route::post('ads/{ad}/image', [AdminAdController::class, 'image']);
        Route::delete('ads/{ad}', [AdminAdController::class, 'destroy']);

        Route::get('economy', [EconomyController::class, 'show']);
        Route::put('economy/settings', [EconomyController::class, 'updateSettings']);
        Route::post('revenue', [EconomyController::class, 'storeRevenue']);
        Route::delete('revenue/{revenueEntry}', [EconomyController::class, 'destroyRevenue']);

        Route::get('network-ads', [AdminNetworkAdController::class, 'index']);
        Route::post('network-ads', [AdminNetworkAdController::class, 'store']);
        Route::put('network-ads/{networkAd}', [AdminNetworkAdController::class, 'update']);
        Route::delete('network-ads/{networkAd}', [AdminNetworkAdController::class, 'destroy']);
    });
});
