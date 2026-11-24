import { useEffect, useState } from 'react';
import api, { errorMessage } from '../../api/client';
import { fcfa, num } from '../../lib/format';
import { Ticket } from 'lucide-react';

const EMPTY = { label: '', value_fcfa: '', cost_points: '', min_purchase_fcfa: '', validity_days: '' };

const toPayload = (f) => ({
  label: f.label,
  value_fcfa: Number(f.value_fcfa),
  cost_points: Number(f.cost_points),
  min_purchase_fcfa: f.min_purchase_fcfa === '' ? 0 : Number(f.min_purchase_fcfa),
  validity_days: f.validity_days === '' || f.validity_days === null ? null : Number(f.validity_days),
});

function Fields({ form, setForm }) {
  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });
  return (
    <>
      <label className="muted">Libellé</label>
      <input placeholder="ex : Bon 1 000 FCFA" value={form.label} onChange={set('label')} required />
      <label className="muted">Valeur de la réduction (FCFA)</label>
      <input type="number" min="1" value={form.value_fcfa} onChange={set('value_fcfa')} required />
      <label className="muted">Prix en points</label>
      <input type="number" min="1" value={form.cost_points} onChange={set('cost_points')} required />
      <label className="muted">Achat minimum pour l'utiliser (FCFA, 0 = aucun)</label>
      <input type="number" min="0" value={form.min_purchase_fcfa} onChange={set('min_purchase_fcfa')} />
      <label className="muted">Validité en jours (vide = illimitée)</label>
      <input type="number" min="1" value={form.validity_days ?? ''} onChange={set('validity_days')} />
    </>
  );
}

export default function AdminVoucherTypes() {
  const [types, setTypes] = useState([]);
  const [form, setForm] = useState(EMPTY);
  const [editId, setEditId] = useState(null);
  const [draft, setDraft] = useState(null);
  const [msg, setMsg] = useState(null);

  const load = () => api.get('/admin/voucher-types').then((r) => setTypes(r.data));
  useEffect(() => { load(); }, []);

  const create = async (e) => {
    e.preventDefault();
    setMsg(null);
    try {
      await api.post('/admin/voucher-types', toPayload(form));
      setForm(EMPTY);
      setMsg({ ok: true, text: 'Type de bon ajouté.' });
      load();
    } catch (err) {
      setMsg({ ok: false, text: errorMessage(err, 'Création impossible.') });
    }
  };

  const toggle = async (t) => {
    await api.put(`/admin/voucher-types/${t.id}`, { active: !t.active });
    load();
  };

  const startEdit = (t) => {
    setEditId(t.id);
    setDraft({
      label: t.label,
      value_fcfa: t.value_fcfa,
      cost_points: t.cost_points,
      min_purchase_fcfa: t.min_purchase_fcfa,
      validity_days: t.validity_days ?? '',
    });
  };

  const save = async () => {
    setMsg(null);
    try {
      await api.put(`/admin/voucher-types/${editId}`, toPayload(draft));
      setEditId(null);
      setMsg({ ok: true, text: 'Type de bon modifié (les bons déjà vendus ne changent pas).' });
      load();
    } catch (err) {
      setMsg({ ok: false, text: errorMessage(err, 'Modification impossible.') });
    }
  };

  return (
    <>
      <h2><Ticket className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> Types de bons</h2>
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}

      <form className="card" onSubmit={create}>
        <h3>Ajouter un type de bon</h3>
        <Fields form={form} setForm={setForm} />
        <button>Ajouter</button>
      </form>

      {types.map((t) => (
        <div key={t.id} className="card">
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
            <div style={{ flex: 1, minWidth: 220 }}>
              <strong>{t.label}</strong>{' '}
              <span className={`badge ${t.active ? 'purchased' : 'used'}`}>{t.active ? 'Actif' : 'Inactif'}</span>
              <div className="muted">
                {fcfa(t.value_fcfa)} · {num(t.cost_points)} pts · achat min. {t.min_purchase_fcfa > 0 ? fcfa(t.min_purchase_fcfa) : 'aucun'} ·{' '}
                {t.validity_days ? `${t.validity_days} j` : 'sans expiration'}
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              <button className="secondary" onClick={editId === t.id ? () => setEditId(null) : () => startEdit(t)}>
                {editId === t.id ? 'Annuler' : 'Modifier'}
              </button>
              <button className="secondary" onClick={() => toggle(t)}>{t.active ? 'Désactiver' : 'Activer'}</button>
            </div>
          </div>

          {editId === t.id && draft && (
            <div style={{ marginTop: 14 }}>
              <Fields form={draft} setForm={setDraft} />
              <button onClick={save}>Enregistrer</button>
            </div>
          )}
        </div>
      ))}
    </>
  );
}
