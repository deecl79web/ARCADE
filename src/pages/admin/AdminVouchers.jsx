import { useEffect, useState } from 'react';
import api from '../../api/client';
import { voucherStatus } from '../Dashboard';
import { AlertTriangle } from 'lucide-react';

export default function AdminVouchers() {
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [data, setData] = useState(null);

  useEffect(() => {
    api.get('/admin/vouchers', { params: { status: status || undefined, page } })
      .then((r) => setData(r.data));
  }, [status, page]);

  return (
    <>
      <h2>Bons émis</h2>
      <select value={status} onChange={(e) => { setPage(1); setStatus(e.target.value); }} style={{ maxWidth: 220 }}>
        <option value="">Tous les statuts</option>
        <option value="purchased">Achetés (non utilisés)</option>
        <option value="used">Utilisés</option>
      </select>

      <div className="card">
        <table>
          <thead><tr><th>Client</th><th>Bon</th><th>Valeur</th><th>Acheté</th><th>Statut</th></tr></thead>
          <tbody>
            {data?.data.map((v) => {
              const s = voucherStatus(v);
              const shared = v.user?.shared_ip_accounts || 1;
              return (
                <tr key={v.uuid}>
                  <td>
                    {v.user?.name}<br /><span className="muted">{v.user?.email}</span>
                    {shared > 1 && (
                      <div>
                        <span className="badge warn" title={`IP d'inscription : ${v.user?.registration_ip}`}>
                          <AlertTriangle className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> {shared} comptes sur la même IP
                        </span>
                      </div>
                    )}
                  </td>
                  <td>{v.label}<br /><span className="muted">{v.uuid.slice(0, 8)}</span></td>
                  <td>{v.value_fcfa} FCFA</td>
                  <td>{new Date(v.purchased_at).toLocaleDateString('fr-FR')}</td>
                  <td>
                    <span className={`badge ${s.key}`}>{s.label}</span>
                    {v.used_at && <div className="muted">{new Date(v.used_at).toLocaleString('fr-FR')}</div>}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        <p className="muted">
          « Même IP » est un simple indice (les abonnés d'un même opérateur mobile partagent parfois une IP) :
          vérifie avant de refuser un client.
        </p>
      </div>

      {data && (
        <div>
          <button className="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Précédent</button>{' '}
          <span className="muted">Page {data.current_page} / {data.last_page}</span>{' '}
          <button className="secondary" disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)}>Suivant</button>
        </div>
      )}
    </>
  );
}
