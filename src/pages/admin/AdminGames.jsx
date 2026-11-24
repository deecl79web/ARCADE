import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { errorMessage } from '../../api/client';

const NEW_GAME = {
  title: '',
  slug: '',
  description: '',
  max_score: 1000,
  points_per_100_score: 10,
  min_duration_seconds: 10,
};

const NUMBER_FIELDS = [
  ['max_score', 'Score maximum accepté'],
  ['points_per_100_score', 'Points gagnés pour 100 de score'],
  ['min_duration_seconds', "Durée minimale d'une partie (secondes)"],
];

function uploadError(err, fallback) {
  if (err.response?.status === 413) {
    return 'Fichier trop gros pour le serveur : augmente upload_max_filesize et post_max_size dans php.ini.';
  }
  const msg = errorMessage(err, fallback);
  return /failed to upload/i.test(msg)
    ? `${msg} (augmente upload_max_filesize et post_max_size dans php.ini)`
    : msg;
}

function GameCard({ game, onChanged, onError }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(null);
  const [busy, setBusy] = useState(false);

  const startEdit = () => {
    setDraft({
      title: game.title,
      description: game.description || '',
      max_score: game.max_score,
      points_per_100_score: game.points_per_100_score,
      min_duration_seconds: game.min_duration_seconds,
    });
    setEditing(true);
  };

  const toggle = async () => {
    try {
      await api.put(`/admin/games/${game.id}`, { active: !game.active });
      onChanged(game.active ? 'Jeu désactivé.' : 'Jeu activé.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const save = async () => {
    try {
      const payload = { title: draft.title, description: draft.description || null };
      NUMBER_FIELDS.forEach(([key]) => { payload[key] = Number(draft[key]); });
      await api.put(`/admin/games/${game.id}`, payload);
      setEditing(false);
      onChanged('Paramètres enregistrés.');
    } catch (err) {
      onError(errorMessage(err));
    }
  };

  const replaceZip = async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    if (!window.confirm(`Remplacer les fichiers de « ${game.title} » par ${file.name} ?`)) return;

    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('zip', file);
      await api.post(`/admin/games/${game.id}/files`, fd);
      onChanged('ZIP remplacé (nouvelle version).');
    } catch (err) {
      onError(uploadError(err, 'Remplacement impossible.'));
    } finally {
      setBusy(false);
    }
  };

  const replaceThumb = async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    try {
      const fd = new FormData();
      fd.append('thumbnail', file);
      await api.post(`/admin/games/${game.id}/files`, fd);
      onChanged('Miniature mise à jour.');
    } catch (err) {
      onError(uploadError(err, 'Miniature refusée.'));
    }
  };

  const remove = async () => {
    if (!window.confirm(`Supprimer « ${game.title} » et tous ses fichiers ?`)) return;
    try {
      await api.delete(`/admin/games/${game.id}`);
      onChanged('Jeu supprimé.');
    } catch (err) {
      if (err.response?.status === 409) {
        if (!window.confirm(`${err.response.data.message}\n\nSupprimer quand même ?`)) return;
        try {
          await api.delete(`/admin/games/${game.id}`, { params: { force: 1 } });
          onChanged('Jeu supprimé.');
        } catch (err2) {
          onError(errorMessage(err2));
        }
      } else {
        onError(errorMessage(err));
      }
    }
  };

  return (
    <div className="card">
      <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
        {game.thumbnail_url && (
          <img src={game.thumbnail_url} alt="" style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 8 }} />
        )}
        <div style={{ flex: 1, minWidth: 200 }}>
          <strong>{game.title}</strong>{' '}
          <span className={`badge ${game.active ? 'purchased' : 'used'}`}>{game.active ? 'Actif' : 'Désactivé'}</span>
          <div className="muted">/{game.slug} · v{game.version} · {game.sessions_count} partie(s)</div>
          <div className="muted">
            Max {game.max_score} · {game.points_per_100_score} pts / 100 · min {game.min_duration_seconds}s
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <Link className="btn secondary" to={`/arcade/${game.slug}`}>Ouvrir</Link>
          <button className="secondary" onClick={toggle}>{game.active ? 'Désactiver' : 'Activer'}</button>
          <button className="secondary" onClick={editing ? () => setEditing(false) : startEdit}>
            {editing ? 'Annuler' : 'Modifier'}
          </button>
          <label className="btn secondary" style={{ opacity: busy ? 0.5 : 1 }}>
            {busy ? 'Envoi…' : 'Remplacer le ZIP'}
            <input type="file" accept=".zip" hidden disabled={busy} onChange={replaceZip} />
          </label>
          <label className="btn secondary">
            Miniature
            <input type="file" accept="image/png,image/jpeg,image/webp" hidden onChange={replaceThumb} />
          </label>
          <button style={{ background: '#8a3b2a' }} onClick={remove}>Supprimer</button>
        </div>
      </div>

      {editing && draft && (
        <div style={{ marginTop: 16 }}>
          <label className="muted">Titre</label>
          <input value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} />
          <label className="muted">Description</label>
          <input value={draft.description} onChange={(e) => setDraft({ ...draft, description: e.target.value })} />
          {NUMBER_FIELDS.map(([key, label]) => (
            <div key={key}><label className="muted">{label}</label><input type="number" min="0" value={draft[key]} onChange={(e) => setDraft({ ...draft, [key]: e.target.value })} /></div>
          ))}
          <button onClick={save}>Enregistrer</button>
        </div>
      )}
    </div>
  );
}

export default function AdminGames() {
  const [games, setGames] = useState([]);
  const [form, setForm] = useState(NEW_GAME);
  const [zip, setZip] = useState(null);
  const [thumb, setThumb] = useState(null);
  const [fileKey, setFileKey] = useState(0);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState(null); // { ok, text }

  const load = () => api.get('/admin/games').then((r) => setGames(r.data));
  useEffect(() => { load(); }, []);

  const changed = (text) => { setMsg({ ok: true, text }); load(); };
  const failed = (text) => setMsg({ ok: false, text });

  const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

  const create = async (e) => {
    e.preventDefault();
    if (!zip) return failed('Choisis le ZIP du jeu.');
    setBusy(true);
    setMsg(null);
    try {
      const fd = new FormData();
      Object.entries(form).forEach(([k, v]) => { if (v !== '') fd.append(k, v); });
      fd.append('active', '1');
      fd.append('zip', zip);
      if (thumb) fd.append('thumbnail', thumb);

      await api.post('/admin/games', fd);
      setForm(NEW_GAME);
      setZip(null);
      setThumb(null);
      setFileKey((k) => k + 1);
      changed('Jeu ajouté.');
    } catch (err) {
      failed(uploadError(err, "Ajout impossible."));
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <h2>Jeux</h2>
      {msg && <p className={msg.ok ? 'success' : 'error'}>{msg.text}</p>}

      <form className="card" onSubmit={create}>
        <h3>Ajouter un jeu</h3>
        <label className="muted">Titre *</label>
        <input value={form.title} onChange={set('title')} required />
        <label className="muted">Adresse (slug) — laisser vide pour la générer depuis le titre</label>
        <input value={form.slug} onChange={set('slug')} placeholder="ex : tape-taupe" pattern="[a-z0-9]+(-[a-z0-9]+)*" />
        <label className="muted">Description</label>
        <input value={form.description} onChange={set('description')} />
        {NUMBER_FIELDS.map(([key, label]) => (
          <div key={key}><label className="muted">{label}</label><input type="number" min="0" value={form[key]} onChange={set(key)} required /></div>
        ))}
        <label className="muted">ZIP du jeu * (index.html à la racine)</label>
        <input key={`z${fileKey}`} type="file" accept=".zip" onChange={(e) => setZip(e.target.files[0] || null)} />
        <label className="muted">Miniature (optionnelle, PNG/JPG/WebP, 1 Mo max)</label>
        <input key={`t${fileKey}`} type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => setThumb(e.target.files[0] || null)} />
        <button disabled={busy}>{busy ? 'Envoi en cours…' : 'Ajouter le jeu'}</button>
      </form>

      {games.length === 0 && <p className="muted">Aucun jeu.</p>}
      {games.map((g) => (
        <GameCard key={g.id} game={g} onChanged={changed} onError={failed} />
      ))}
    </>
  );
}
