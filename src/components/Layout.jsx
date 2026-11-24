import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { ArrowUp, BarChart3, Coins, Gamepad2, List, Megaphone, Menu, MonitorPlay, ScanLine, Settings, Tags, Ticket, User, X, Zap } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { CONTACT, FACEBOOK_URL, SITE_NAME, TELEGRAM_URL, WHATSAPP_URL } from '../lib/config';
import { num } from '../lib/format';
import BackgroundFX from './BackgroundFX';
import NextVoucherBar from './NextVoucherBar';
import { FacebookIcon, TelegramIcon, WhatsAppIcon } from './BrandIcons';

const navClass = ({ isActive }) => (isActive ? 'active' : undefined);

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);

  useEffect(() => { setOpen(false); }, [location.pathname]);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="app-shell">
      <BackgroundFX />

      <header className="nav">
        <Link className="brand" to="/"><Zap className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> {SITE_NAME}</Link>

        <button className="nav-toggle secondary" aria-label={open ? 'Fermer le menu' : 'Ouvrir le menu'} aria-expanded={open} onClick={() => setOpen((o) => !o)}>
          {open ? <X className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> : <Menu className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" />}
        </button>

        <nav className={`nav-links ${open ? 'open' : ''}`}>
          <NavLink to="/arcade" className={navClass}><Gamepad2 className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Jeux</NavLink>
          {user && <NavLink to="/vouchers" className={navClass}><Ticket className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Bons</NavLink>}
          {user && <NavLink to="/dashboard" className={navClass}><User className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Mon espace</NavLink>}
          {user?.role === 'admin' && (
            <details className="admin-menu">
              <summary><Settings className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Admin</summary>
              <div className="admin-list">
                <NavLink to="/admin/economy" className={navClass}><BarChart3 className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Économie</NavLink>
                <NavLink to="/admin/games" className={navClass}><Gamepad2 className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Jeux</NavLink>
                <NavLink to="/admin/ads" className={navClass}><Megaphone className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Sponsors</NavLink>
                <NavLink to="/admin/network-ads" className={navClass}><MonitorPlay className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Régie</NavLink>
                <NavLink to="/admin/scan" className={navClass}><ScanLine className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Scanner</NavLink>
                <NavLink to="/admin/vouchers" className={navClass}><List className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Liste des bons</NavLink>
                <NavLink to="/admin/voucher-types" className={navClass}><Tags className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Types de bons</NavLink>
              </div>
            </details>
          )}
        </nav>

        <div className="nav-right">
          {user ? (
            <>
              <span className="points-chip" title="Ton solde de points"><Coins className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> {num(user.points_balance)}</span>
              <span className="user-name">{user.name}</span>
              <button className="secondary" onClick={handleLogout}>Quitter</button>
            </>
          ) : (
            <>
              <Link className="btn secondary" to="/login">Connexion</Link>
              <Link className="btn" to="/register">Jouer gratuitement</Link>
            </>
          )}
        </div>
      </header>

      {user && <NextVoucherBar />}

      <main className="container">
        <Outlet />
      </main>

      <footer className="footer">
        <div className="footer-grid">
          <section>
            <Link className="footer-brand" to="/"><Zap className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> {SITE_NAME}</Link>
            <p>Mini-jeux et récompenses pour la boutique.</p>
          </section>
          <section>
            <h3>Jouer</h3>
            <Link to="/arcade">Jeux</Link>
            {user && <Link to="/vouchers">Bons</Link>}
            {user && <Link to="/dashboard">Mon espace</Link>}
          </section>
          <section>
            <h3>Informations</h3>
            <Link to="/legal/conditions">Conditions</Link>
            <Link to="/legal/confidentialite">Confidentialité</Link>
            <Link to="/legal/bons">Règles des bons</Link>
          </section>
          <section>
            <h3>Contact</h3>
            {WHATSAPP_URL && <a className="footer-whatsapp" href={WHATSAPP_URL} target="_blank" rel="noopener noreferrer"><WhatsAppIcon /> Commander sur WhatsApp</a>}
            <div className="social-links">
              {TELEGRAM_URL && <a href={TELEGRAM_URL} target="_blank" rel="noopener noreferrer" aria-label="Telegram"><TelegramIcon /></a>}
              {FACEBOOK_URL && <a href={FACEBOOK_URL} target="_blank" rel="noopener noreferrer" aria-label="Facebook"><FacebookIcon /></a>}
              {WHATSAPP_URL && <a href={WHATSAPP_URL} target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><WhatsAppIcon /></a>}
            </div>
            {CONTACT && <p className="muted">{CONTACT}</p>}
          </section>
        </div>
        <div className="footer-bottom">
          <span>© {new Date().getFullYear()} {SITE_NAME} · Les points n'ont aucune valeur monétaire</span>
          <button className="footer-top secondary" type="button" onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} aria-label="Retour en haut">
            <ArrowUp className="icon-glow" size={18} strokeWidth={2} aria-hidden="true" /> Retour en haut
          </button>
        </div>
      </footer>
    </div>
  );
}
