import { useEffect, useRef, useState } from 'react';
import { num } from '../lib/format';

/** Compteur animé (de l'ancienne valeur vers la nouvelle). */
export default function AnimatedNumber({ value, prefix = '', duration = 900 }) {
  const [shown, setShown] = useState(0);
  const fromRef = useRef(0);

  useEffect(() => {
    const target = Number(value) || 0;
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
      fromRef.current = target;
      setShown(target);
      return undefined;
    }

    const from = fromRef.current;
    const start = performance.now();
    let raf;

    const tick = (now) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - (1 - t) ** 3;
      setShown(Math.round(from + (target - from) * eased));
      if (t < 1) raf = requestAnimationFrame(tick);
      else fromRef.current = target;
    };
    raf = requestAnimationFrame(tick);

    return () => cancelAnimationFrame(raf);
  }, [value, duration]);

  return <>{prefix}{num(shown)}</>;
}
