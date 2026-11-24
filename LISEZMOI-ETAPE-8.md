# Étape 8 : gains sans plafond, barème des bons & réglages économiques

## Backend

### Mise à jour

```bash
cd arcade-laravel
php artisan migrate
php artisan test
```

Cette migration rend `games.daily_cap` nullable, remet tous les plafonds existants à `NULL` et ajoute le réglage public `interstitial_every_n_games` avec la valeur par défaut `1`. Elle ne modifie pas les prix déjà enregistrés dans la base.

### Ajustement des prix

Les nouveaux prix proposés par le seeder sont au rapport de 4 points par FCFA :

- 500 FCFA : 2 000 points
- 1 000 FCFA : 4 000 points
- 2 500 FCFA : 10 000 points
- 5 000 FCFA : 20 000 points

Pour appliquer ou corriger ces tarifs sur une installation existante, ouvre **Admin → Types de bons** puis utilise **Modifier** sur chaque type. Ne lance pas une migration pour écraser les réglages déjà personnalisés.

### Économie et réglages

Admin → Économie permet notamment de modifier le pourcentage du budget, la barrière d'achat et `interstitial_every_n_games`. Le réglage interstitiel est exposé publiquement via `GET /api/settings/public` sans données sensibles.

### Sécurité publicitaire

Le backend conserve les sponsors en plein écran, l'iframe sandboxée pour les régies et l'alternance sponsor/régie. Il n'attribue aucun point pour les affichages ou clics publicitaires.

## Bilan PHPUnit

- Tests réussis : **61 / 61**
- Assertions : **278**
- Commande : `php artisan test`
