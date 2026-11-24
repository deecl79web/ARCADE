# Arcade — API (Laravel)

API de la plateforme d'arcade : les joueurs gagnent des points en jouant à des mini-jeux HTML5, puis les
échangent contre des bons de réduction (PDF + QR code) validés par l'admin. Le front est dans `arcade-front`.

## Prérequis
- PHP 8.2 ou plus, avec les extensions `gd`, `zip`, `mbstring`, `pdo_mysql` (ou `pdo_sqlite` pour les tests), `fileinfo`
- Composer, MySQL / MariaDB

## Installation (développement)
```bash
composer install
cp .env.example .env
php artisan key:generate
# configure la base de données dans .env, puis :
php artisan migrate --seed          # le seeder crée admin@arcade.test / password : LOCAL UNIQUEMENT
php artisan serve                   # http://localhost:8000
```
En production : pas de `--seed`. Crée ton admin avec `php artisan arcade:create-admin ton@email.com`.

## Réglages `.env` propres à l'application
| Variable | Rôle | Défaut |
|---|---|---|
| `VOUCHER_HMAC_SECRET` | Clé de signature des QR codes. **Ne jamais la changer** une fois des bons émis | `APP_KEY` |
| `MAX_SIGNUPS_PER_IP_PER_DAY` | Inscriptions max par IP sur 24 h (0 = illimité) | 5 |
| `VOUCHER_MIN_ACCOUNT_AGE_HOURS` | Ancienneté minimale du compte avant un bon (0 = désactivé) | 24 |
| `VOUCHER_MAX_PER_DAY` | Bons max par joueur sur 24 h (0 = illimité) | 2 |
| `TOKEN_DAYS` | Durée de validité d'une connexion | 30 |
| `GAME_ZIP_MAX_KB` | Taille max d'un ZIP de jeu (Ko) | 51200 |

## Organisation du code
- `app/Services/` : la logique métier
  - `PointsService` : grand livre des points (ajout seul, solde = somme)
  - `ScoreService` : sessions de jeu, durée minimale, score maximum, gain de points (aucun plafond)
  - `VoucherService` : achat de bons (transaction + verrous), QR signé, scan atomique
  - `EconomyService` : budget de bons, prix recommandés, indicateurs de l'admin
  - `GameFilesService` : extraction sécurisée des ZIP de jeux
- `app/Http/Controllers/` (+ `Admin/`) : contrôleurs fins ; routes dans `routes/api.php`
- `app/Models/` : Eloquent ; `database/migrations/` : schéma

## Règles métier à connaître
- **Points** : jamais de décimales ; `points_ledger` ne se modifie ni ne se supprime.
- **Budget de bons** : budget = revenus déclarés (Admin → Économie) × pourcentage (50 % par défaut). Un achat est refusé
  si la valeur du bon dépasse le budget restant. Les revenus se saisissent à la main, une fois encaissés.
- **Anti-abus** : limite d'inscriptions par IP, ancienneté du compte, bons par jour, score maximum et durée minimale par
  jeu, jeton de session que le jeu (iframe) ne voit jamais.
- **Jeux** : ZIP validé avant extraction (extensions autorisées, anti zip-slip, taille), servi par `/api/play/...`
  avec les en-têtes CORS nécessaires à l'iframe sandbox.
- **Publicités** : sponsors (image + lien) et régie (code collé par l'admin, servi dans une page isolée par CSP `sandbox`).

## Tests
```bash
php artisan test
```
`phpunit.xml` doit utiliser `DB_CONNECTION=sqlite` et `DB_DATABASE=:memory:` : les tests refusent de tourner autrement,
car ils vident la base.

## Mise en ligne
Voir `GUIDE-MISE-EN-LIGNE.md` (hébergement, `.env` de production, sauvegardes, dépannage).
