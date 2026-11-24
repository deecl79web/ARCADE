<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 24px; }
    body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
    .card { border: 2px solid #111; padding: 20px; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .muted { color: #666; font-size: 10px; }
    .value { font-size: 30px; font-weight: bold; margin: 14px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 5px 0; vertical-align: top; }
    td.label { color: #666; width: 32%; }
    .qr { text-align: center; margin-top: 16px; }
    .note { margin-top: 10px; font-size: 10px; color: #666; text-align: center; }
</style>
</head>
<body>
<div class="card">
    <h1>{{ config('app.name') }} — Bon de réduction</h1>
    <div class="muted">Bon à usage unique</div>

    <div class="value">{{ number_format($voucher->value_fcfa, 0, ',', ' ') }} FCFA</div>

    <table>
        <tr><td class="label">Client</td><td>{{ $voucher->user->name }}</td></tr>
        <tr><td class="label">Type</td><td>{{ $voucher->label }}</td></tr>
        <tr><td class="label">ID du bon</td><td>{{ $voucher->uuid }}</td></tr>
        <tr><td class="label">Acheté le</td><td>{{ $voucher->purchased_at->format('d/m/Y H:i') }}</td></tr>
        @if($voucher->min_purchase_fcfa > 0)
        <tr><td class="label">Achat minimum</td><td>{{ number_format($voucher->min_purchase_fcfa, 0, ',', ' ') }} FCFA</td></tr>
        @endif
        @if($voucher->expires_at)
        <tr><td class="label">Valable jusqu'au</td><td>{{ $voucher->expires_at->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <div class="qr"><img src="{{ $qr }}" width="200" height="200" alt="QR"></div>
    <div class="note">Envoyez ce PDF au vendeur pour valider votre réduction.</div>
</div>
</body>
</html>
