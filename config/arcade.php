<?php

return [
    // Clé HMAC pour signer les QR codes des bons (par défaut : APP_KEY)
    'voucher_secret' => env('VOUCHER_HMAC_SECRET', env('APP_KEY')),

    // Taille maximale d'un ZIP de jeu, en Ko (50 Mo par défaut)
    'max_zip_kb' => (int) env('GAME_ZIP_MAX_KB', 51200),

    // --- Anti-abus ---
    // Inscriptions max par adresse IP sur 24 h (0 = pas de limite).
    // Attention : sur les réseaux mobiles, beaucoup d'abonnés partagent la même IP publique.
    'max_signups_per_ip_per_day' => (int) env('MAX_SIGNUPS_PER_IP_PER_DAY', 5),

    // Ancienneté minimale du compte avant de pouvoir acheter un bon (0 = désactivé)
    'voucher_min_account_age_hours' => (int) env('VOUCHER_MIN_ACCOUNT_AGE_HOURS', 24),

    // Nombre max de bons achetés par joueur sur 24 h (0 = pas de limite)
    'voucher_max_per_day' => (int) env('VOUCHER_MAX_PER_DAY', 2),

    // Durée de validité d'un token de connexion
    'token_days' => (int) env('TOKEN_DAYS', 30),
];
