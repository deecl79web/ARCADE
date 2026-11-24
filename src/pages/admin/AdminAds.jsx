import { useEffect, useState } from 'react';
import api, { errorMessage } from '../../api/client';

const POSITIONS = {
  arcade_list: { label: 'Liste des jeux (bannière)', hint: 'Bannière ~ 728×90 ou 970×250' },
  game_below: { label: 'Sous le jeu (bannière)', hint: 'Bannière ~ 728×90' },
  account: { label: 'Mon espace + page des bons (bannière)', hint: 'Bannière ~ 728×90' },
  game_over: { label: 'Fin de partie (sous le score)', hint: 'Bannière ~ 728×90 ou 300×250' },
  game_start: { label: 'Début de chaque partie (plein écran, 4 s)', hint: 'Image ~ 600×400' },
  second_chance: { label: 'Seconde chance (plein écran, 5 s)', hint: 'Image ~ 600×400' },
};

const NEW_AD = { title: '', position: 'arcade_list', link_url: 'https://', starts_at: '', ends_at: '' };

// ISO (UTC) du serveur -> valeur d'un <input type="datetime-local"> en heure locale
function toLocalInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// valeur datetime-local (heure locale) -> ISO UTC, ou null
const toIso = (local) => (local ? new Date(local).toISOString() : null);

const fmt = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR') : '—');

function ctr(ad) {
  return ad.impressions > 0 ? `${((ad.clicks / ad.impressions) * 100).toFixed(1)} %` : '—';
}

function AdCard({ ad, onChanged, onError }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);

  const live = ad.active
    && (!ad.starts_at || new Date(ad.starts_at) <= new Date())
    && (!ad.ends_at || new Date(ad.ends_at) >= new Date());

  const startEdit = () => {
    setDraft({
      title: ad.title,
      position: ad.position,
      link_url: ad.link_url,
      starts_at: toLocalInput(ad.starts_at),
      ends_at: toLocalInput(ad.ends_at),
    });
    setEditing(true);
  };

  const toggle = async () => {
    try {
      await api.put(`/admin/ads/${ad.id}`, { active: !ad.active });
      onChanged(ad.active ? 'Annonce désactivée.' : 'Annonce activée.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const save = async () => {
    try {
      await api.put(`/admin/ads/${ad.id}`, {
        title: draft.title,
        position: draft.position,
        link_url: draft.link_url,
        starts_at: toIso(draft.starts_at),
        ends_at: toIso(draft.ends_at),
      });
      setEditing(false);
      onChanged('Annonce enregistrée.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const replaceImage = async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    try {
      const fd = new FormData();
      fd.append('image', file);
      await api.post(`/admin/ads/${ad.id}/image`, fd);
      onChanged('Image remplacée.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const remove = async () => {
    if (!window.confirm(`Supprimer l'annonce « ${ad.title} » ?`)) return;
    try {
      await api.delete(`/admin/ads/${ad.id}`);
      onChanged('Annonce supprimée.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  return (
    <div className="card">
      <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
        <img src={ad.image_url} alt="" style={{ width: 160, maxHeight: 90, objectFit: 'contain', background: '#000', borderRadius: 8 }} />
        <div style={{ flex: 1, minWidth: 220 }}>
          <strong>{ad.title}</strong>{' '}
          <span className={`badge ${live ? 'purchased' : 'used'}`}>{live ? 'En ligne' : ad.active ? 'Programmée / expirée' : 'Désactivée'}</span>
          <div className="muted">{POSITIONS[ad.position]?.label}</div>
          <div className="muted">Du {fmt(ad.starts_at)} au {fmt(ad.ends_at)}</div>
          <div className="muted">
            {ad.impressions} affichage(s) · {ad.clicks} clic(s) · CTR {ctr(ad)}
          </div>
          <div className="muted" style={{ wordBreak: 'break-all' }}>{ad.link_url}</div>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="secondary" onClick={toggle}>{ad.active ? 'Désactiver' : 'Activer'}</button>
          <button className="secondary" onClick={editing ? () => setEditing(false) : startEdit}>{editing ? 'Annuler' : 'Modifier'}</button>
          <label className="btn secondary">
            Changer l'image
            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" hidden onChange={replaceImage} />
          </label>
          <button style={{ background: '#8a3b2a' }} onClick={remove}>Supprimer</button>
        </div>
      </div>

      {editing && draft && (
        <div style={{ marginTop: 16 }}>
          <label className="muted">Sponsor / campagne</label>
          <input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} />
          <label className="muted">Emplacement</label>
          <select value={draft.position} onChange={(e) => setDraft({ ...draft, position: e.target.value })}>
            {Object.entries(POSITIONS).map(([key, p]) => <option key={key} value={key}>{p.label}</option>)}
          </select>
          <label className="muted">Lien (http/https)</label>
          <input type="url" value={draft.link_url} onChange={(e) => setDraft({ ...draft, link_url: e.target.value })} />
          <label className="muted">Début (optionnel)</label>
          <input type="datetime-local" value={draft.starts_at} onChange={(e) => setDraft({ ...draft, starts_at: e.target.value })} />
          <label className="muted">Fin (optionnel)</label>
          <input type="datetime-local" value={draft.ends_at} onChange={(e) => setDraft({ ...draft, ends_at: e.target.value })} />
          <button onClick={save}>Enregistrer</button>
        </div>
      )}
    </div>
  );
}

export default function AdminAds() {
  const [ads, setAds] = useState([]);
  const [form, setForm] = useState(NEW_AD);
  const [image, setImage] = useState(null);
  const [fileKey, setFileKey] = useState(0);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState(null);

  const load = () => api.get('/admin/ads').then((r) => setAds(r.data));
  useEffect(() => { load(); }, []);

  const changed = (text) => { setMsg({ ok: true, text }); load(); };
  const failed = (text) => setMsg({ ok: false, text });
  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const create = async (e) => {
    e.preventDefault();
    if (!image) return failed("Choisis l'image de l'annonce.");
    setBusy(true);
    setMsg(null);
    try {
      const fd = new FormData();
      fd.append('title', form.title);
      fd.append('position', form.position);
      fd.append('link_url', form.link_url);
      if (form.starts_at) fd.append('starts_at', toIso(form.starts_at));
      if (form.ends_at) fd.append('ends_at', toIso(form.ends_at));
      fd.append('active', '1');
      fd.append('image', image);

      await api.post('/admin/ads', fd);
      setForm(NEW_AD);
      setImage(null);
      setFileKey((k) => k + 1);
      changed('Annonce ajoutée.');
    } catch (err) {
      failed(errorMessage(err, "Ajout impossible."));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <h2>Publicités (sponsors directs)</h2>
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}

      <form className="card" onSubmit={create}>
        <h3>Ajouter une annonce</h3>
        <label className="muted">Sponsor / campagne *</label>
        <input value={form.title} onChange={set('title')} required />
        <label className="muted">Emplacement *</label>
        <select value={form.position} onChange={set('position')}>
          {Object.entries(POSITIONS).map(([key, p]) => <option key={key} value={key}>{p.label}</option>)}
        </select>
        <p className="muted" style={{ marginTop: -4 }}>{POSITIONS[form.position].hint} — PNG, JPG, WebP ou GIF, 1 Mo max.</p>
        <label className="muted">Lien de destination * (http ou https)</label>
        <input type="url" value={form.link_url} onChange={set('link_url')} required />
        <label className="muted">Début (optionnel)</label>
        <input type="datetime-local" value={form.starts_at} onChange={set('starts_at')} />
        <label className="muted">Fin (optionnel)</label>
        <input type="datetime-local" value={form.ends_at} onChange={set('ends_at')} />
        <label className="muted">Image *</label>
        <input key={fileKey} type="file" accept="image/png,image/jpeg,image/webp,image/gif" onChange={(e) => setImage(e.target.files[0] || null)} />
        <button disabled={busy}>{busy ? 'Envoi…' : "Ajouter l'annonce"}</button>
      </form>

      {ads.length === 0 && <p className="muted">Aucune annonce.</p>}
      {ads.map((ad) => <AdCard key={ad.id} ad={ad} onChanged={changed} onError={failed} />)}
    </>
  );
}
