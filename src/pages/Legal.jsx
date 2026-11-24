import { Link, useParams } from 'react-router-dom';
import { CONTACT, SITE_NAME } from '../lib/config';

/*
 * MODÈLES de pages légales, à faire relire et adapter (nom de l'exploitant, adresse, droit applicable).
 * Je ne suis pas juriste : ces textes donnent une base, pas un avis juridique.
 */
const PAGES = {
  conditions: {
    title: "Conditions d'utilisation",
    body: (
      <>
        <p>En créant un compte sur {SITE_NAME}, tu acceptes les conditions suivantes.</p>
        <h3>Le service</h3>
        <ul>
          <li>{SITE_NAME} propose des mini-jeux gratuits. Jouer te permet de gagner des points.</li>
          <li>Les points n'ont aucune valeur monétaire : ils ne sont ni remboursables, ni transférables, ni échangeables contre de l'argent.</li>
          <li>Les points peuvent être échangés contre des bons de réduction, selon les règles de la page « Règles des bons ».</li>
        </ul>
        <h3>Ton compte</h3>
        <ul>
          <li>Un seul compte par personne. Les informations données à l'inscription doivent être exactes.</li>
          <li>Tu es responsable de la confidentialité de ton mot de passe.</li>
        </ul>
        <h3>Fair-play</h3>
        <ul>
          <li>Il est interdit de tricher : scripts, automatisation, comptes multiples, exploitation d'une faille ou falsification de scores.</li>
          <li>En cas de triche constatée ou soupçonnée, nous pouvons annuler des points ou des bons, et suspendre ou supprimer le compte.</li>
        </ul>
        <h3>Publicités</h3>
        <p>Le site affiche des publicités, y compris de partenaires tiers. Nous ne maîtrisons pas le contenu des sites vers lesquels elles renvoient.</p>
        <h3>Disponibilité et modifications</h3>
        <p>Le service est fourni « en l'état », sans garantie de disponibilité permanente. Nous pouvons modifier les jeux, les règles et les récompenses à tout moment.</p>
        <h3>Contact</h3>
        <p>{CONTACT || 'Renseigne VITE_CONTACT dans le fichier .env du front pour afficher ton contact ici.'}</p>
      </>
    ),
  },
  confidentialite: {
    title: 'Politique de confidentialité',
    body: (
      <>
        <h3>Données collectées</h3>
        <ul>
          <li>Nom, adresse e-mail et mot de passe (stocké sous forme chiffrée).</li>
          <li>Adresse IP au moment de l'inscription (prévention des abus et des comptes multiples).</li>
          <li>Historique de tes parties, de tes points et de tes bons.</li>
        </ul>
        <h3>Pourquoi</h3>
        <ul>
          <li>Faire fonctionner le service (compte, points, bons).</li>
          <li>Lutter contre la triche et les abus.</li>
          <li>Afficher des publicités et mesurer leur audience.</li>
        </ul>
        <h3>Publicité et services tiers</h3>
        <p>Nos partenaires publicitaires peuvent utiliser des cookies ou des identifiants pour afficher et mesurer les annonces. Consulte leurs politiques pour en savoir plus.</p>
        <h3>Partage et conservation</h3>
        <ul>
          <li>Nous ne vendons pas tes données personnelles.</li>
          <li>Tes données sont conservées tant que ton compte existe, puis supprimées sur demande.</li>
        </ul>
        <h3>Tes droits</h3>
        <p>Tu peux demander l'accès, la correction ou la suppression de tes données en nous contactant : {CONTACT || '(ajoute ton contact dans VITE_CONTACT)'}.</p>
      </>
    ),
  },
  bons: {
    title: 'Règles des bons de réduction',
    body: (
      <>
        <ul>
          <li>Un bon s'obtient en échangeant des points. Son prix en points est indiqué dans la page « Bons ».</li>
          <li>Chaque bon est unique, à usage unique, et porte un identifiant et un QR code.</li>
          <li>Pour l'utiliser, envoie le PDF du bon au vendeur lors de ta commande. Le vendeur le vérifie et le marque comme utilisé.</li>
          <li>Certains bons exigent un achat minimum et/ou ont une date limite : ces conditions sont écrites sur le bon.</li>
          <li>Un bon expiré, déjà utilisé, falsifié ou obtenu par triche est refusé.</li>
          <li>Un bon n'a pas de valeur en argent : il ne peut ni être remboursé ni converti en espèces.</li>
          <li>Pour garder le service durable, la quantité de bons disponibles peut être limitée ; certains bons peuvent être momentanément indisponibles.</li>
          <li>Tu peux acheter plusieurs bons, dans la limite du nombre par 24 heures affiché dans la page « Bons ».</li>
        </ul>
      </>
    ),
  },
};

export default function Legal() {
  const { page } = useParams();
  const content = PAGES[page];

  if (!content) return <p>Page introuvable. <Link to="/">Retour à l'accueil</Link></p>;

  return (
    <div className="card legal">
      <h2>{content.title}</h2>
      <p className="muted">Dernière mise à jour : novembre 2026</p>
      {content.body}
    </div>
  );
}
