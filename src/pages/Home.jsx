import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Coins, Gamepad2, ShoppingBag, Ticket } from 'lucide-react';
import api from '../api/client';
import { useAuth } from '../context/AuthContext';
import { SITE_NAME, WHATSAPP_URL } from '../lib/config';
import AdSlot from '../components/AdSlot';

const STEPS = [
  { Icon: Gamepad2, title: '1. Joue', text: 'Des mini-jeux gratuits, directement sur ton téléphone ou ton ordinateur.' },
  { Icon: Coins, title: '2. Gagne des points', text: 'Chaque partie terminée te rapporte des points.' },
  { Icon: Ticket, title: '3. Obtiens un bon', text: 'Échange tes points contre un bon de réduction pour notre boutique.' },
];

export default function Home() {
  const { user } = useAuth();
  const [games, setGames] = useState([]);

  useEffect(() => {
    api.get('/games').then((r) => setGames(r.data.slice(0, 3))).catch(() => {});
  }, []);

  return (
    <>
      <section className="hero">
        <h1>Joue. Gagne.<br />Économise.</h1>
        <p className="lead">
          Joue aux mini-jeux de {SITE_NAME}, cumule des points et transforme-les en bons de réduction sur nos vêtements, accessoires et parfums.
        </p>
        <div className="cta">
          <Link className="btn big" to="/arcade"><Gamepad2 className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> Jouer maintenant</Link>
          {!user && <Link className="btn secondary big" to="/register">Créer mon compte</Link>}
        </div>
      </section>

      <div className="steps">
        {STEPS.map(({ Icon, title, text }) => (
          <div key={title} className="card step">
            <span className="step-icon"><Icon className="icon-glow" size={40} strokeWidth={2} aria-hidden="true" /></span>
            <h3>{title}</h3>
            <p className="muted">{text}</p>
          </div>
        ))}
      </div>

      {games.length > 0 && (
        <>
          <h2>À la une</h2>
          <div className="grid" style={{ marginBottom: 20 }}>
            {games.map((g) => (
              <Link key={g.id} to={`/arcade/${g.slug}`} className="card game-card">
                <div className="thumb">{g.thumbnail_url ? <img src={g.thumbnail_url} alt="" /> : <Gamepad2 className="icon-glow" size={40} strokeWidth={2} aria-hidden="true" />}</div>
                <div className="body"><h3>{g.title}</h3><p className="muted">{g.description}</p></div>
              </Link>
            ))}
          </div>
        </>
      )}

      <AdSlot position="arcade_list" />

      {WHATSAPP_URL && (
        <div className="card shop-cta">
          <h3><ShoppingBag className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Vêtements, accessoires & parfums</h3>
          <p className="muted">Tes bons s'utilisent dans notre boutique. Découvre les produits et commande sur WhatsApp.</p>
          <a className="btn" href={WHATSAPP_URL} target="_blank" rel="noopener noreferrer">Commander sur WhatsApp</a>
        </div>
      )}
    </>
  );
}
