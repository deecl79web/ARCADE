import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { errorMessage } from '../api/client';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await register(name, email, password);
      navigate('/arcade');
    } catch (err) {
      setError(errorMessage(err, 'Inscription impossible.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="card auth-card">
      <h2>Inscription</h2>
      <form onSubmit={submit}>
        <input placeholder="Nom complet (il apparaîtra sur tes bons)" value={name} onChange={(e) => setName(e.target.value)} required autoComplete="name" />
        <input type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
        <input type="password" placeholder="Mot de passe (8 caractères min.)" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" />
        {error && <p className="error">{error}</p>}
        <button disabled={busy}>{busy ? 'Création…' : 'Créer mon compte'}</button>
      </form>
      <p className="muted" style={{ marginTop: 14 }}>
        En t'inscrivant, tu acceptes les <Link to="/legal/conditions">conditions d'utilisation</Link> et la{' '}
        <Link to="/legal/confidentialite">politique de confidentialité</Link>.
      </p>
      <p className="muted">Déjà un compte ? <Link to="/login">Connexion</Link></p>
    </div>
  );
}
