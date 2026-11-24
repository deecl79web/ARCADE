import { useEffect, useState } from 'react';
import { Coins, Download, Lock, PartyPopper, Ticket } from 'lucide-react';
import api, { errorMessage } from '../api/client';
import { useAuth } from '../context/AuthContext';
import AdSlot from '../components/AdSlot';
import { fcfa, num } from '../lib/format';
import { downloadVoucherPdf } from './Dashboard';

export default function Vouchers() {
  const { user, refreshUser } = useAuth();
  const [types, setTypes] = useState([]);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState('');
  const [bought, setBought] = useState(null);

  useEffect(() => {
    api.get('/voucher-types').then((r) => setTypes(r.data));
  }, [bought]);

  const buy = async (type) => {
    if (!window.confirm(`Échanger ${num(type.cost_points)} points contre « ${type.label} » ?`)) return;
    setBusyId(type.id);
    setError('');
    setBought(null);
    try {
      const { data } = await api.post('/vouchers/purchase', { voucher_type_id: type.id });
      setBought(data.voucher);
      await refreshUser();
    } catch (err) {
      setError(errorMessage(err, 'Achat impossible.'));
    } finally {
      setBusyId(null);
    }
  };

  const balance = user.points_balance || 0;

  return (
    <>
      <AdSlot position="account" />

      <h2><Ticket className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Mes récompenses</h2>
      <p className="muted"><Coins className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Ton solde : <strong style={{ color: 'var(--gold)' }}>{num(balance)} points</strong></p>
      {error && <p className="error">{error}</p>}

      {bought && (
        <div className="card reward" style={{ borderColor: 'var(--lime)' }}>
          <p className="success" style={{ fontSize: '1.2rem', fontWeight: 800 }}><PartyPopper className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Bon obtenu</p>
          <p className="muted">Télécharge le PDF et envoie-le au vendeur pour profiter de ta réduction.</p>
          <button onClick={() => downloadVoucherPdf(bought.uuid)}><Download className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> Télécharger le PDF</button>
        </div>
      )}

      <div className="tickets">
        {types.map((t) => {
          const enough = balance >= t.cost_points;
          const percent = Math.min(100, Math.round((balance / t.cost_points) * 100));
          const blocked = !t.available;

          return (
            <div key={t.id} className={`ticket ${blocked ? 'locked' : ''}`}>
              <div className="amount">{fcfa(t.value_fcfa)}</div>
              <div className="muted">{t.label}</div>
              <span className="cost"><Coins className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> {num(t.cost_points)} pts</span>
              <p className="muted" style={{ margin: '0 0 8px' }}>
                {t.min_purchase_fcfa > 0 ? `Dès ${fcfa(t.min_purchase_fcfa)} d'achat` : 'Sans achat minimum'}
                {t.validity_days ? ` · valable ${t.validity_days} jours` : ''}
              </p>
              {!enough && !blocked && (
                <div className="progress" style={{ marginBottom: 10 }}><span style={{ width: `${percent}%` }} /></div>
              )}
              <button disabled={busyId === t.id || !enough || blocked} onClick={() => buy(t)}>
                {blocked ? <><Lock className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> Bientôt disponible</> : enough ? 'Obtenir ce bon' : `Encore ${num(t.cost_points - balance)} pts`}
              </button>
            </div>
          );
        })}
      </div>
      {types.some((t) => !t.available) && (
        <p className="muted" style={{ marginTop: 14 }}>
          Certains bons sont momentanément indisponibles : la quantité de bons est limitée pour garder le service durable. Reviens bientôt !
        </p>
      )}
    </>
  );
}
