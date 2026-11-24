# Arcade — Front (React + Vite)

Interface de la plateforme d'arcade : jeux, solde et progression, bons, espace admin. L'API est dans `arcade-laravel`.

## Démarrage
```bash
npm install
cp .env.example .env        # puis adapte les valeurs
npm run dev                 # http://localhost:5173
npm run build               # production : dossier dist/
```
Après toute modification de `.env`, redémarre `npm run dev`.

## Variables `.env`
| Variable | Rôle |
|---|---|
| `VITE_API_URL` | URL de l'API, ex. `http://localhost:8000/api` |
| `VITE_SITE_NAME` | Nom affiché (en-tête, pied de page) |
| `VITE_WHATSAPP_URL`, `VITE_TELEGRAM_URL`, `VITE_FACEBOOK_URL` | Liens de la boutique (masqués si vides) |
| `VITE_CONTACT` | Contact affiché dans le pied de page et les pages légales |

## Organisation
- `src/pages/` : une page par route ; `src/pages/admin/` : pages réservées aux admins
- `src/components/` : Layout, pied de page, publicités (`AdSlot`, `AdInterstitial`), progression, animations
- `src/context/AuthContext.jsx` : connexion (token Bearer dans le stockage du navigateur)
- `src/api/client.js` : client axios ; `src/lib/` : configuration et formats
- `src/index.css` : thème néon unique (variables CSS en haut du fichier)
- Icônes : `lucide-react` (imports nommés) ; logos de marques dans `components/BrandIcons.jsx`

## Jeux (iframe sandbox)
Le jeu tourne dans un iframe sans `allow-same-origin` : il n'a accès ni au token ni à l'API. Il communique par
`window.parent.postMessage` :
- jeu -> site : `{ type: 'arcade:gameover', score, canContinue? }`
- site -> jeu : `{ type: 'arcade:continue' }` (seconde chance, une fois par partie)

Le jeton de session reste côté React ; le serveur valide le score (durée, score maximum).

## Publicités
Sponsors (image + lien) et régie (code dans un cadre isolé) en alternance. Aucun point n'est lié aux affichages ou
aux clics, et les annonces ne se rafraîchissent que sur une action du joueur (nouvelle partie).

## Mise en ligne
`npm run build`, puis envoie le contenu de `dist/` sur le serveur. Ajoute un `.htaccess` de redirection vers
`index.html` pour que les pages fonctionnent au rechargement (voir `GUIDE-MISE-EN-LIGNE.md`).
