import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Coins, Gamepad2 } from 'lucide-react';
import api from '../api/client';
import AdSlot from '../components/AdSlot';

export default function Arcade() {
  const [games, setGames] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/games').then((r) => setGames(r.data)).finally(() => setLoading(false));
  }, []);

  return (
    <>
      <AdSlot position="arcade_list" />
      <h2><Gamepad2 className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Les jeux</h2>
      {loading && <p className="muted">Chargement…</p>}
      {!loading && games.length === 0 && <p className="muted">Aucun jeu disponible pour le moment.</p>}
      <div className="grid">
        {games.map((g) => (
          <Link key={g.id} to={`/arcade/${g.slug}`} className="card game-card">
            <div className="thumb">{g.thumbnail_url ? <img src={g.thumbnail_url} alt="" /> : <Gamepad2 className="icon-glow" size={40} strokeWidth={2} aria-hidden="true" />}</div>
            <div className="body">
              <h3>{g.title}</h3>
              <p className="muted">{g.description}</p>
              <span className="pill"><Coins className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Points à chaque partie</span>
            </div>
          </Link>
        ))}
      </div>
    </>
  );
}
