import { useEffect, useState } from 'react';
import api, { errorMessage } from '../../api/client';
import { NetworkFrame } from '../../components/AdSlot';

const POSITIONS = {
  arcade_list: 'Liste des jeux (bannière)',
  game_below: 'Sous le jeu (bannière)',
  account: 'Mon espace + page des bons (bannière)',
  game_over: 'Fin de partie (sous le score)',
};

const NEW_SLOT = {
  name: '',
  provider: 'Monetag',
  position: 'arcade_list',
  width: 320,
  height: 100,
  html_code: '',
};

const textareaStyle = {
  width: '100%', minHeight: 140, background: '#0f1020', color: '#eee', border: '1px solid #333760',
  borderRadius: 8, padding: 10, marginBottom: 10, fontFamily: 'monospace', fontSize: 12,
};

function SlotCard({ slot, onChanged, onError }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);

  const startEdit = () => {
    setDraft({
      name: slot.name,
      provider: slot.provider || '',
      position: slot.position,
      width: slot.width,
      height: slot.height,
      html_code: slot.html_code,
    });
    setEditing(true);
  };

  const toggle = async () => {
    try {
      await api.put(`/admin/network-ads/${slot.id}`, { active: !slot.active });
      onChanged(slot.active ? 'Emplacement désactivé.' : 'Emplacement activé.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const save = async () => {
    try {
      await api.put(`/admin/network-ads/${slot.id}`, {
        name: draft.name,
        provider: draft.provider || null,
        position: draft.position,
        width: Number(draft.width),
        height: Number(draft.height),
        html_code: draft.html_code,
      });
      setEditing(false);
      onChanged('Emplacement enregistré.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const remove = async () => {
    if (!window.confirm(`Supprimer « ${slot.name} » ?`)) return;
    try {
      await api.delete(`/admin/network-ads/${slot.id}`);
      onChanged('Emplacement supprimé.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  return (
    <div className="card">
      <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 220 }}>
          <strong>{slot.name}</strong>{' '}
          <span className={`badge ${slot.active ? 'purchased' : 'used'}`}>{slot.active ? 'Actif' : 'Désactivé'}</span>
          <div className="muted">{slot.provider || 'Régie'} · {POSITIONS[slot.position]}</div>
          <div className="muted">Cadre {slot.width}×{slot.height} px · {slot.views} chargement(s)</div>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="secondary" onClick={toggle}>{slot.active ? 'Désactiver' : 'Activer'}</button>
          <button className="secondary" onClick={editing ? () => setEditing(false) : startEdit}>{editing ? 'Annuler' : 'Modifier'}</button>
          <button style={{ background: '#8a3b2a' }} onClick={remove}>Supprimer</button>
        </div>
      </div>

      {slot.active && (
        <div style={{ marginTop: 12 }}>
          <span className="ad-label">Aperçu (ne compte pas dans les chargements)</span>
          <NetworkFrame ad={{ frame_url: `${slot.frame_url}?preview=1`, width: slot.width, height: slot.height }} />
        </div>
      )}

      {editing && draft && (
        <div style={{ marginTop: 16 }}>
          <label className="muted">Nom</label>
          <input value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
          <label className="muted">Régie</label>
          <input value={draft.provider} onChange={(e) => setDraft({ ...draft, provider: e.target.value })} />
          <label className="muted">Emplacement</label>
          <select value={draft.position} onChange={(e) => setDraft({ ...draft, position: e.target.value })}>
            {Object.entries(POSITIONS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
          </select>
          <label className="muted">Largeur (px)</label>
          <input type="number" min="50" max="1200" value={draft.width} onChange={(e) => setDraft({ ...draft, width: e.target.value })} />
          <label className="muted">Hauteur (px)</label>
          <input type="number" min="20" max="900" value={draft.height} onChange={(e) => setDraft({ ...draft, height: e.target.value })} />
          <label className="muted">Code de la régie</label>
          <textarea style={textareaStyle} value={draft.html_code} onChange={(e) => setDraft({ ...draft, html_code: e.target.value })} />
          <button onClick={save}>Enregistrer</button>
        </div>
      )}
    </div>
  );
}

export default function AdminNetworkAds() {
  const [slots, setSlots] = useState([]);
  const [form, setForm] = useState(NEW_SLOT);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState(null);

  const load = () => api.get('/admin/network-ads').then((r) => setSlots(r.data));
  useEffect(() => { load(); }, []);

  const changed = (text) => { setMsg({ ok: true, text }); load(); };
  const failed = (text) => setMsg({ ok: false, text });
  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const create = async (e) => {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      await api.post('/admin/network-ads', {
        name: form.name,
        provider: form.provider || null,
        position: form.position,
        width: Number(form.width),
        height: Number(form.height),
        html_code: form.html_code,
      });
      setForm(NEW_SLOT);
      changed('Emplacement ajouté.');
    } catch (err) {
      failed(errorMessage(err, 'Ajout impossible.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <h2>Régie publicitaire (Monetag…)</h2>
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}

      <div className="card">
        <p className="muted" style={{ marginTop: 0 }}>
          Colle ici le code (« tag ») fourni par la régie après approbation de ton site. Il s'affiche dans un cadre isolé :
          il ne peut pas accéder aux comptes de tes joueurs. Les formats qui demandent le contrôle de toute la page
          (notifications push, pop-under) ne fonctionneront pas dans ce cadre. Quand sponsor et régie existent
          pour un même emplacement, ils s'affichent en alternance.
        </p>
      </div>

      <form className="card" onSubmit={create}>
        <h3>Ajouter un emplacement</h3>
        <label className="muted">Nom *</label>
        <input value={form.name} onChange={set('name')} placeholder="ex : Monetag - bannière liste" required />
        <label className="muted">Régie</label>
        <input value={form.provider} onChange={set('provider')} />
        <label className="muted">Emplacement *</label>
        <select value={form.position} onChange={set('position')}>
          {Object.entries(POSITIONS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
        </select>
        <label className="muted">Largeur du cadre (px) *</label>
        <input type="number" min="50" max="1200" value={form.width} onChange={set('width')} required />
        <label className="muted">Hauteur du cadre (px) *</label>
        <input type="number" min="20" max="900" value={form.height} onChange={set('height')} required />
        <label className="muted">Code de la régie *</label>
        <textarea style={textareaStyle} value={form.html_code} onChange={set('html_code')} placeholder="<script ...></script>" required />
        <button disabled={busy}>{busy ? 'Envoi…' : "Ajouter l'emplacement"}</button>
      </form>

      {slots.length === 0 && <p className="muted">Aucun emplacement de régie.</p>}
      {slots.map((s) => <SlotCard key={s.id} slot={s} onChanged={changed} onError={failed} />)}
    </>
  );
}
