/** Fond animé en CSS pur (grille néon, halos, étoiles). Désactivé si l'utilisateur préfère moins d'animations. */
export default function BackgroundFX() {
  return (
    <div className="bg-fx" aria-hidden="true">
      <div className="bg-stars" />
      <div className="bg-orb o1" />
      <div className="bg-orb o2" />
      <div className="bg-orb o3" />
      <div className="bg-grid" />
    </div>
  );
}
