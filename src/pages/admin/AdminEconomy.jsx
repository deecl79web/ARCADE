import { useEffect, useState } from 'react';
import api, { errorMessage } from '../../api/client';
import { fcfa, num } from '../../lib/format';
import { BarChart3, X } from 'lucide-react';

const today = () => new Date().toISOString().slice(0, 10);

function Stat({ label, value, hint, accent }) {
  return (
    <div className="card" style={{ marginBottom: 0 }}>
      <p className="muted" style={{ marginBottom: 2 }}>{label}</p>
      <div style={{ fontSize: '1.5rem', fontWeight: 900, color: accent || 'var(--text)' }}>{value}</div>
      {hint && <p className="muted" style={{ margin: '4px 0 0' }}>{hint}</p>}
    </div>
  );
}

export default function AdminEconomy() {
  const [data, setData] = useState(null);
  const [msg, setMsg] = useState(null);
  const [percent, setPercent] = useState(50);
  const [form, setForm] = useState({ amount_fcfa: '', source: 'Monetag', received_on: today(), note: '' });
  const [interstitialEvery, setInterstitialEvery] = useState(1);

  const load = () => api.get('/admin/economy').then((r) => {
    setData(r.data);
    setPercent(r.data.settings.budget_percent);
    setInterstitialEvery(r.data.settings.interstitial_every_n_games ?? 1);
  });
  useEffect(() => { load(); }, []);

  const ok = (text) => { setMsg({ ok: true, text }); load(); };
  const ko = (err, fallback) => setMsg({ ok: false, text: errorMessage(err, fallback) });

  const saveSettings = async (patch) => {
    try {
      await api.put('/admin/economy/settings', patch);
      ok('Réglage enregistré.');
    } catch (err) { ko(err, 'Enregistrement impossible.'); }
  };

  const addRevenue = async (e) => {
    e.preventDefault();
    try {
      await api.post('/admin/revenue', {
        amount_fcfa: Number(form.amount_fcfa),
        source: form.source || null,
        received_on: form.received_on || null,
        note: form.note || null,
      });
      setForm({ amount_fcfa: '', source: form.source, received_on: today(), note: '' });
      ok('Revenu ajouté.');
    } catch (err) { ko(err, 'Ajout impossible.'); }
  };

  const removeRevenue = async (id) => {
    if (!window.confirm('Supprimer cette ligne de revenu ?')) return;
    try {
      await api.delete(`/admin/revenue/${id}`);
      ok('Ligne supprimée.');
    } catch (err) { ko(err, 'Suppression impossible.'); }
  };

  const applyPrice = async (type) => {
    if (!window.confirm(`Passer « ${type.label} » à ${num(type.recommended_cost_points)} points ?`)) return;
    try {
      await api.put(`/admin/voucher-types/${type.id}`, { cost_points: type.recommended_cost_points });
      ok('Prix mis à jour.');
    } catch (err) { ko(err, 'Mise à jour impossible.'); }
  };

  if (!data) return <p className="muted">Chargement…</p>;

  const usedPercent = data.budget_fcfa > 0 ? Math.min(100, Math.round((data.engaged_fcfa / data.budget_fcfa) * 100)) : 100;

  return (
    <>
      <h2><BarChart3 className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Économie des bons</h2>
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}

      <div className="card">
        <p style={{ marginTop: 0 }}>
          <strong>Règle :</strong> les bons ne peuvent être achetés que dans la limite de{' '}
          <strong>{data.settings.budget_percent} %</strong> de tes revenus publicitaires. Avec 50 %, pour 500 FCFA de
          réduction, il faut avoir encaissé 1 000 FCFA. Saisis uniquement les revenus <em>réellement encaissés ou
          validés</em> par la régie (pas l'estimation du tableau de bord).
        </p>
        <div className="progress"><span style={{ width: `${usedPercent}%`, background: usedPercent > 85 ? 'linear-gradient(90deg,#ffd34d,#ff5c7a)' : undefined }} /></div>
        <p className="muted" style={{ marginTop: 6 }}>
          Budget utilisé : {fcfa(data.engaged_fcfa)} sur {fcfa(data.budget_fcfa)} — reste{' '}
          <strong style={{ color: 'var(--lime)' }}>{fcfa(data.remaining_fcfa)}</strong>
        </p>
      </div>

      <div className="grid" style={{ marginBottom: 16 }}>
        <Stat label="Revenus déclarés" value={fcfa(data.revenue_total_fcfa)} />
        <Stat label="Budget de bons" value={fcfa(data.budget_fcfa)} hint={`${data.settings.budget_percent} % des revenus`} accent="var(--gold)" />
        <Stat label="Reste disponible" value={fcfa(data.remaining_fcfa)} accent="var(--lime)" />
        <Stat label="Bons en circulation" value={fcfa(data.outstanding_value_fcfa)} hint="achetés, pas encore utilisés" />
        <Stat label="Bons utilisés" value={fcfa(data.used_value_fcfa)} />
        <Stat label="Bons expirés (budget libéré)" value={fcfa(data.released_value_fcfa)} />
      </div>

      <div className="card">
        <h3>Réglages</h3>
        <label className="muted">Part des revenus utilisable en bons (%)</label>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <input type="number" min="1" max="100" value={percent} onChange={(e) => setPercent(e.target.value)} style={{ maxWidth: 120, marginBottom: 0 }} />
          <button className="secondary" onClick={() => saveSettings({ budget_percent: Number(percent) })}>Enregistrer</button>
        </div>
        <p className="muted" style={{ marginTop: 12 }}>
          Barrière de budget : <strong>{data.settings.gate_enabled ? 'ACTIVE' : 'DÉSACTIVÉE'}</strong>.{' '}
          {data.settings.gate_enabled
            ? "Les achats sont bloqués quand le budget est épuisé."
            : "Les joueurs peuvent acheter des bons sans limite de budget : à n'utiliser que pour tester."}
        </p>
        <button className={data.settings.gate_enabled ? 'secondary' : ''} onClick={() => saveSettings({ gate_enabled: !data.settings.gate_enabled })}>
          {data.settings.gate_enabled ? 'Désactiver la barrière (tests)' : 'Réactiver la barrière'}
        </button>

        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap', marginTop: 14 }}>
          <label className="muted" style={{ marginBottom: 0 }}>Annonce plein écran toutes les</label>
          <input type="number" min="1" value={interstitialEvery} onChange={(e) => setInterstitialEvery(e.target.value)} style={{ maxWidth: 90, marginBottom: 0 }} />
          <span className="muted">partie(s)</span>
          <button className="secondary" onClick={() => saveSettings({ interstitial_every_n_games: Math.max(1, Number(interstitialEvery)) })}>Enregistrer</button>
        </div>
      </div>

      <div className="card">
        <h3>Points et revenus observés</h3>
        <div className="table-wrap">
          <table>
            <tbody>
              <tr><td>Points distribués par les jeux</td><td>{num(data.points.distributed)}</td></tr>
              <tr><td>Points dépensés en bons</td><td>{num(data.points.spent)}</td></tr>
              <tr><td>Points en circulation (soldes des joueurs)</td><td>{num(data.points.in_circulation)}</td></tr>
              <tr><td>Parties terminées</td><td>{num(data.sessions_completed)}</td></tr>
              <tr><td>Revenu moyen par point distribué</td><td>{data.revenue_per_point_fcfa === null ? '—' : `${data.revenue_per_point_fcfa} FCFA`}</td></tr>
              <tr><td>Revenu moyen par partie</td><td>{data.revenue_per_session_fcfa === null ? '—' : `${data.revenue_per_session_fcfa} FCFA`}</td></tr>
              <tr><td>Points moyens par partie terminée</td><td>{data.average_points_per_completed_game === null ? '—' : `${data.average_points_per_completed_game} pts`}</td></tr>
              <tr><td>Affichages publicitaires totaux</td><td>{num(data.ad_displays.total)}</td></tr>
              <tr><td>Affichages publicitaires par partie terminée</td><td>{data.ad_displays.per_completed_game === null ? '—' : data.ad_displays.per_completed_game}</td></tr>
              <tr><td>Revenu moyen par affichage</td><td>{data.ad_displays.revenue_per_display_fcfa === null ? '—' : `${data.ad_displays.revenue_per_display_fcfa} FCFA`}</td></tr>
            </tbody>
          </table>
        </div>
        <p className="muted" style={{ marginBottom: 0 }}>
          Ce sont des moyennes sur toute la période. Les paiements des régies arrivent avec du retard : attends plusieurs
          semaines de données avant de te fier à ces chiffres.
        </p>
      </div>

      <div className="card">
        <h3>Prix des bons : actuel et recommandé</h3>
        <p className="muted">
          Prix recommandé = les points dépensés doivent avoir « rapporté » le revenu nécessaire pour financer le bon
          (valeur ÷ {data.settings.budget_percent} %). Il se calcule dès que des revenus et des points existent.
        </p>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Bon</th><th>Valeur</th><th>Prix actuel</th><th>Parties nécessaires</th><th>Recommandé</th><th /></tr></thead>
            <tbody>
              {data.voucher_types.map((t) => (
                <tr key={t.id}>
                  <td>{t.label}{!t.active && <> <span className="badge used">inactif</span></>}</td>
                  <td>{fcfa(t.value_fcfa)}</td>
                  <td>{num(t.cost_points)} pts{t.underpriced && <><br /><span className="badge expired">Sous-évalué : tu perds de l'argent</span></>}</td>
                  <td>{t.games_needed === null ? '—' : num(t.games_needed)}</td>
                  <td>{t.recommended_cost_points === null ? '—' : `${num(t.recommended_cost_points)} pts`}</td>
                  <td>
                    {t.recommended_cost_points !== null && t.recommended_cost_points !== t.cost_points && (
                      <button className="secondary" onClick={() => applyPrice(t)}>Appliquer</button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <form className="card" onSubmit={addRevenue}>
        <h3>Déclarer un revenu publicitaire</h3>
        <label className="muted">Montant encaissé / validé (FCFA) *</label>
        <input type="number" min="1" value={form.amount_fcfa} onChange={(e) => setForm({ ...form, amount_fcfa: e.target.value })} required />
        <label className="muted">Source</label>
        <input value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })} placeholder="Monetag, sponsor…" />
        <label className="muted">Date</label>
        <input type="date" value={form.received_on} onChange={(e) => setForm({ ...form, received_on: e.target.value })} />
        <label className="muted">Note (optionnelle)</label>
        <input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
        <button>Ajouter</button>
      </form>

      <div className="card">
        <h3>Derniers revenus déclarés</h3>
        {data.revenue_entries.length === 0 ? <p className="muted">Aucun revenu déclaré : les bons restent bloqués tant que le budget est à 0.</p> : (
          <div className="table-wrap">
            <table>
              <thead><tr><th>Date</th><th>Source</th><th>Montant</th><th>Note</th><th /></tr></thead>
              <tbody>
                {data.revenue_entries.map((r) => (
                  <tr key={r.id}>
                    <td>{new Date(r.received_on).toLocaleDateString('fr-FR')}</td>
                    <td>{r.source}</td>
                    <td>{fcfa(r.amount_fcfa)}</td>
                    <td className="muted">{r.note}</td>
                    <td><button className="danger" onClick={() => removeRevenue(r.id)} aria-label="Supprimer cette ligne"><X className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /></button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="card">
        <h3>Plus gros gains des dernières 24 h</h3>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Joueur</th><th>Points gagnés</th><th>Parties</th><th>Comptes sur l'IP d'inscription</th></tr></thead>
            <tbody>
              {(data.top_gains_24h || []).map((row) => (
                <tr key={row.user_id || row.id || row.email}>
                  <td>{row.name || row.email || `Utilisateur ${row.user_id}`}</td>
                  <td>{num(row.points_gained)} pts</td>
                  <td>{num(row.games_played)}</td>
                  <td>{num(row.shared_ip_accounts)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {(data.top_gains_24h || []).length === 0 && <p className="muted">Aucun gain enregistré sur les dernières 24 heures.</p>}
        <p className="muted" style={{ marginBottom: 0 }}>Tableau indicatif uniquement : aucune action automatique n'est appliquée.</p>
      </div>
    </>
  );
}
