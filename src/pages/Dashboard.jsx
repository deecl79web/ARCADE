import { useEffect, useState } from 'react';
import { Coins, Download, History, Lock, Ticket } from 'lucide-react';
import api, { errorMessage } from '../api/client';
import AdSlot from '../components/AdSlot';
import AnimatedNumber from '../components/AnimatedNumber';
import NextVoucherBar from '../components/NextVoucherBar';
import { fcfa } from '../lib/format';

export const REASONS = { game_score: 'Partie jouée', voucher_purchase: 'Achat de bon' };

export function voucherStatus(v) {
  if (v.status === 'used') return { key: 'used', label: 'Utilisé' };
  if (v.is_expired) return { key: 'expired', label: 'Expiré' };
  return { key: 'purchased', label: 'Disponible' };
}

export async function downloadVoucherPdf(uuid) {
  const res = await api.get(`/vouchers/${uuid}/pdf`, { responseType: 'blob' });
  const url = URL.createObjectURL(res.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = `bon-${uuid.slice(0, 8)}.pdf`;
  a.click();
  URL.revokeObjectURL(url);
}

function ChangePassword() {
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [msg, setMsg] = useState(null);
  const submit = async (e) => {
    e.preventDefault();
    setMsg(null);
    try {
      await api.post('/me/password', { current_password: current, password: next });
      setCurrent(''); setNext('');
      setMsg({ ok: true, text: 'Mot de passe modifié. Les autres appareils ont été déconnectés.' });
    } catch (err) { setMsg({ ok: false, text: errorMessage(err, 'Modification impossible.') }); }
  };
  return (
    <form className="card" onSubmit={submit}>
      <h3><Lock className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Changer mon mot de passe</h3>
      <input type="password" placeholder="Mot de passe actuel" value={current} onChange={(e) => setCurrent(e.target.value)} required autoComplete="current-password" />
      <input type="password" placeholder="Nouveau mot de passe (8 caractères min.)" value={next} onChange={(e) => setNext(e.target.value)} required minLength={8} autoComplete="new-password" />
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}
      <button>Modifier</button>
    </form>
  );
}

export default function Dashboard() {
  const [points, setPoints] = useState(null);
  const [vouchers, setVouchers] = useState([]);
  const [error, setError] = useState('');
  useEffect(() => {
    Promise.all([api.get('/me/points'), api.get('/me/vouchers')])
      .then(([p, v]) => { setPoints(p.data); setVouchers(v.data); })
      .catch((err) => setError(errorMessage(err)));
  }, []);
  const download = async (uuid) => { try { await downloadVoucherPdf(uuid); } catch (err) { setError(errorMessage(err, 'Téléchargement impossible.')); } };
  if (!points) return <p className="muted">{error || 'Chargement…'}</p>;
  return (
    <>
      <AdSlot position="account" />
      <div className="card">
        <p className="muted" style={{ marginBottom: 0 }}>Mon solde</p>
        <div className="balance"><Coins className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> <AnimatedNumber value={points.balance} /> <span style={{ fontSize: '1.2rem' }}>points</span></div>
        <NextVoucherBar big />
      </div>
      {error && <p className="error">{error}</p>}
      <h2><Ticket className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Mes bons</h2>
      {vouchers.length === 0 ? (
        <div className="card"><p className="muted" style={{ margin: 0 }}>Aucun bon pour le moment. Joue pour gagner des points.</p></div>
      ) : (
        <div className="tickets" style={{ marginBottom: 16 }}>
          {vouchers.map((v) => {
            const s = voucherStatus(v);
            return (
              <div key={v.uuid} className={`ticket ${s.key === 'purchased' ? '' : 'locked'}`}>
                <div className="amount">{fcfa(v.value_fcfa)}</div>
                <div className="muted">{v.label} · {v.uuid.slice(0, 8)}</div>
                <p style={{ margin: '8px 0' }}><span className={`badge ${s.key}`}>{s.label}</span></p>
                <p className="muted" style={{ margin: '0 0 8px' }}>{v.min_purchase_fcfa > 0 ? `Dès ${fcfa(v.min_purchase_fcfa)} d'achat · ` : ''}{v.expires_at ? `jusqu'au ${new Date(v.expires_at).toLocaleDateString('fr-FR')}` : 'sans expiration'}</p>
                {s.key === 'purchased' && <button onClick={() => download(v.uuid)}><Download className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> PDF</button>}
              </div>
            );
          })}
        </div>
      )}
      <h2><History className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Historique des points</h2>
      <div className="card table-wrap"><table><thead><tr><th>Date</th><th>Motif</th><th>Points</th></tr></thead><tbody>{points.history.map((h) => <tr key={h.id}><td>{new Date(h.created_at).toLocaleString('fr-FR')}</td><td>{REASONS[h.reason] || h.reason}</td><td className={h.delta > 0 ? 'success' : 'error'}>{h.delta > 0 ? `+${h.delta}` : h.delta}</td></tr>)}</tbody></table></div>
      <ChangePassword />
    </>
  );
}
