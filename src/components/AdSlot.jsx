import { useEffect, useRef, useState } from 'react';
import api from '../api/client';

export const safeHref = (url) => (/^https?:\/\//i.test(url) ? url : '#');

/**
 * Récupère une annonce pour une position.
 * undefined = chargement, null = aucune annonce.
 * L'annonce est soit { type: 'sponsor', ... } soit { type: 'network', frame_url, width, height }.
 */
export function useAd(position) {
  const [ad, setAd] = useState(undefined);

  useEffect(() => {
    let cancelled = false;
    api.get('/ads', { params: { position } })
      .then((r) => { if (!cancelled) setAd(r.data?.id ? r.data : null); })
      .catch(() => { if (!cancelled) setAd(null); });
    return () => { cancelled = true; };
  }, [position]);

  return ad;
}

export const trackImpression = (ad) => { api.post(`/ads/${ad.id}/impression`).catch(() => {}); };
export const trackClick = (ad) => { api.post(`/ads/${ad.id}/click`).catch(() => {}); };

/**
 * Cadre isolé pour le code d'une régie (Monetag...).
 * SÉCURITÉ : le sandbox NE contient PAS "allow-same-origin". Le script de la régie ne peut donc ni
 * lire le localStorage (token des joueurs) ni appeler ton API en leur nom.
 */
export function NetworkFrame({ ad }) {
  return (
    <iframe
      src={ad.frame_url}
      title="Publicité"
      width={ad.width}
      height={ad.height}
      loading="lazy"
      sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"
      style={{ border: 0, maxWidth: '100%' }}
    />
  );
}

/** Bannière (liste des jeux, sous le jeu, mon espace, fin de partie) : sponsor ou régie. */
export default function AdSlot({ position }) {
  const ad = useAd(position);
  const tracked = useRef(null);

  useEffect(() => {
    if (ad && ad.type !== 'network' && tracked.current !== ad.id) {
      tracked.current = ad.id;
      trackImpression(ad);
    }
  }, [ad]);

  if (!ad) return null;

  return (
    <div className="ad-slot">
      <span className="ad-label">Publicité</span>
      {ad.type === 'network' ? (
        <NetworkFrame ad={ad} />
      ) : (
        <a href={safeHref(ad.link_url)} target="_blank" rel="noopener noreferrer sponsored" onClick={() => trackClick(ad)}>
          <img src={ad.image_url} alt={ad.title} />
        </a>
      )}
    </div>
  );
}
