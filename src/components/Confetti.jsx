import { useMemo } from 'react';

const COLORS = ['#00f0ff', '#ff2bd6', '#b6ff3c', '#ffd34d', '#8a5cff'];

/** Pluie de confettis CSS (à placer dans un parent en position: relative). */
export default function Confetti({ count = 36 }) {
  const pieces = useMemo(
    () => Array.from({ length: count }, (_, i) => ({
      id: i,
      left: Math.random() * 100,
      delay: Math.random() * 0.5,
      color: COLORS[i % COLORS.length],
      rotate: Math.random() * 360,
    })),
    [count]
  );

  return (
    <div className="confetti" aria-hidden="true">
      {pieces.map((p) => (
        <span
          key={p.id}
          style={{ left: `${p.left}%`, background: p.color, animationDelay: `${p.delay}s`, transform: `rotate(${p.rotate}deg)` }}
        />
      ))}
    </div>
  );
}
