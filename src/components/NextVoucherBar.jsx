import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Coins, PartyPopper } from 'lucide-react';
import api from '../api/client';
import { useAuth } from '../context/AuthContext';
import { num } from '../lib/format';

/** Calcule la prochaine récompense visée à partir du solde. */
export function useNextVoucher() {
  const { user } = useAuth();
  const [types, setTypes] = useState([]);

  useEffect(() => {
    if (!user) return;
    api.get('/voucher-types').then((r) => setTypes(r.data)).catch(() => {});
  }, [user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!user || types.length === 0) return null;

  const balance = user.points_balance || 0;
  const sorted = [...types].sort((a, b) => a.cost_points - b.cost_points);
  const target = sorted.find((t) => t.cost_points > balance) || sorted[sorted.length - 1];
  const reachable = sorted.filter((t) => t.cost_points <= balance && t.available);

  return {
    target,
    balance,
    percent: Math.min(100, Math.round((balance / target.cost_points) * 100)),
    canBuy: reachable.length > 0,
  };
}

/** Barre de progression vers le prochain bon (compacte sous la navigation, ou grande dans « Mon espace »). */
export default function NextVoucherBar({ big = false }) {
  const info = useNextVoucher();
  if (!info) return null;

  const { target, balance, percent, canBuy } = info;

  if (big) {
    return (
      <div className="next-big">
        <p className="muted" style={{ marginBottom: 6 }}>
          {canBuy ? (
            <>
              <PartyPopper className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Tu as assez de points pour un bon
            </>
          ) : (
            `Prochain bon : ${target.label} (${num(target.cost_points)} pts)`
          )}
        </p>
        <div className={`progress ${percent >= 100 ? 'full' : ''}`}><span style={{ width: `${percent}%` }} /></div>
      </div>
    );
  }

  return (
    <div className="next-bar">
      <div className="inner">
        <span className="label">
          {canBuy ? (
            <>
              <PartyPopper className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Tu peux acheter un bon <Link to="/vouchers">Voir les bons</Link>
            </>
          ) : (
            <>
              <Coins className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Prochain bon : <strong>{target.label}</strong> — {num(balance)} / {num(target.cost_points)} pts
            </>
          )}
        </span>
        <div className={`progress ${percent >= 100 ? 'full' : ''}`} role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent}>
          <span style={{ width: `${percent}%` }} />
        </div>
      </div>
    </div>
  );
}
