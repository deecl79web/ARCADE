import { useEffect, useRef, useState } from 'react';
import { safeHref, trackClick, trackImpression, useAd } from './AdSlot';

/**
 * Annonce plein écran avec compte à rebours (début de partie, seconde chance).
 * - S'il n'y a aucune annonce pour cette position, appelle onUnavailable() tout de suite.
 * - Le bouton principal n'est actif qu'à la fin du compte à rebours.
 */
export default function AdInterstitial({
  position,
  seconds = 5,
  title,
  subtitle,
  primaryLabel,
  onPrimary,
  secondaryLabel,
  onSecondary,
  onUnavailable,
}) {
  const ad = useAd(position);
  const [left, setLeft] = useState(seconds);
  const unavailableCalled = useRef(false);
  const impressionSent = useRef(false);

  useEffect(() => {
    if (ad === null && !unavailableCalled.current) {
      unavailableCalled.current = true;
      onUnavailable?.();
    }
  }, [ad, onUnavailable]);

  useEffect(() => {
    if (!ad) return undefined;
    if (!impressionSent.current) {
      impressionSent.current = true;
      trackImpression(ad);
    }
    const id = setInterval(() => setLeft((s) => (s > 0 ? s - 1 : 0)), 1000);
    return () => clearInterval(id);
  }, [ad]);

  if (!ad) {
    return <div className="overlay"><p className="muted">Chargement…</p></div>;
  }

  return (
    <div className="overlay">
      <h3 style={{ margin: 0 }}>{title}</h3>
      {subtitle && <p className="muted" style={{ margin: 0 }}>{subtitle}</p>}
      <span className="ad-label">Publicité</span>
      <a href={safeHref(ad.link_url)} target="_blank" rel="noopener noreferrer sponsored" onClick={() => trackClick(ad)}>
        <img src={ad.image_url} alt={ad.title} />
      </a>
      <div className="actions">
        <button disabled={left > 0} onClick={onPrimary}>
          {left > 0 ? `${primaryLabel} (${left})` : primaryLabel}
        </button>
        {secondaryLabel && (
          <button className="secondary" onClick={onSecondary}>{secondaryLabel}</button>
        )}
      </div>
    </div>
  );
}
