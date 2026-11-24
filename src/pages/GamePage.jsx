import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Coins, Loader2, Maximize, RotateCcw } from 'lucide-react';
import api, { errorMessage } from '../api/client';
import { useAuth } from '../context/AuthContext';
import AdSlot from '../components/AdSlot';
import AdInterstitial from '../components/AdInterstitial';
import AnimatedNumber from '../components/AnimatedNumber';
import Confetti from '../components/Confetti';
import NextVoucherBar from '../components/NextVoucherBar';

/** Déroulé d'une manche : intro (selon le réglage) -> jeu -> seconde chance -> score. */
export default function GamePage() {
  const { slug } = useParams();
  const { refreshUser } = useAuth();

  const iframeRef = useRef(null);
  const boxRef = useRef(null);
  const sessionRef = useRef(null);
  const continueUsedRef = useRef(false);
  const pendingScoreRef = useRef(0);
  const phaseRef = useRef('loading');
  const startedRoundRef = useRef(-1);

  const [game, setGame] = useState(null);
  const [round, setRound] = useState(0);
  const [phase, setPhaseState] = useState('loading');
  const [pendingScore, setPendingScore] = useState(0);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [interstitialEvery, setInterstitialEvery] = useState(1);

  const setPhase = useCallback((p) => { phaseRef.current = p; setPhaseState(p); }, []);

  useEffect(() => {
    api.get('/settings/public').then((r) => {
      const value = Number(r.data?.interstitial_every_n_games);
      setInterstitialEvery(Number.isFinite(value) && value >= 1 ? value : 1);
    }).catch(() => setInterstitialEvery(1));
  }, []);

  useEffect(() => {
    api.get(`/games/${slug}`).then((r) => setGame(r.data)).catch(() => setError('Jeu introuvable.'));
  }, [slug]);

  useEffect(() => {
    if (!game) return;
    sessionRef.current = null;
    continueUsedRef.current = false;
    startedRoundRef.current = -1;
    setResult(null);
    setError('');
    setPhase('intro');
  }, [game, round, setPhase]);

  const startSession = useCallback(async () => {
    if (!game || startedRoundRef.current === round) return;
    startedRoundRef.current = round;
    try {
      const { data } = await api.post('/game-sessions', { game_id: game.id });
      sessionRef.current = { id: data.session_id, token: data.token };
      setPhase('playing');
    } catch (err) { setError(errorMessage(err, 'Impossible de démarrer la partie.')); setPhase('finished'); }
  }, [game, round, setPhase]);

  const showStartInterstitial = round === 0 || (round % interstitialEvery === 0);
  useEffect(() => { if (phase === 'intro' && game && !showStartInterstitial) startSession(); }, [phase, game, showStartInterstitial, startSession]);

  const submitFinal = useCallback(async (score) => {
    const session = sessionRef.current;
    if (!session) return;
    sessionRef.current = null;
    setPhase('submitting');
    try {
      const { data } = await api.post(`/game-sessions/${session.id}/score`, { token: session.token, score });
      setResult(data); refreshUser();
    } catch (err) { setError(errorMessage(err, 'Score non enregistré.')); }
    setPhase('finished');
  }, [refreshUser, setPhase]);

  const resumeGame = useCallback(() => { continueUsedRef.current = true; iframeRef.current?.contentWindow?.postMessage({ type: 'arcade:continue' }, '*'); setPhase('playing'); }, [setPhase]);
  const finishFromContinue = useCallback(() => submitFinal(pendingScoreRef.current), [submitFinal]);

  useEffect(() => {
    const onMessage = (event) => {
      if (event.source !== iframeRef.current?.contentWindow || phaseRef.current !== 'playing') return;
      const msg = event.data;
      if (!msg || msg.type !== 'arcade:gameover') return;
      const score = Number.parseInt(msg.score, 10);
      if (!Number.isFinite(score) || score < 0 || !sessionRef.current) return;
      if (msg.canContinue === true && !continueUsedRef.current) { pendingScoreRef.current = score; setPendingScore(score); setPhase('continue'); }
      else submitFinal(score);
    };
    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [submitFinal, setPhase]);

  const goFullscreen = () => boxRef.current?.requestFullscreen?.().catch(() => {});
  if (error && !game) return <p className="error">{error}</p>;
  if (!game) return <p>Chargement…</p>;
  const showFrame = !['loading', 'intro'].includes(phase);

  return (
    <>
      <div className="game-toolbar">
        <h2 style={{ margin: 0 }}>{game.title}</h2>
        <button className="secondary" onClick={goFullscreen} aria-label="Afficher le jeu en plein écran"><Maximize className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> Plein écran</button>
      </div>
      <div className="game-box" ref={boxRef}>
        {showFrame && <iframe key={round} ref={iframeRef} src={game.entry_url} title={game.title} sandbox="allow-scripts allow-fullscreen" />}
        {phase === 'intro' && showStartInterstitial && (
          <AdInterstitial position="game_start" seconds={4} title="Ta partie va commencer" primaryLabel="Jouer" onPrimary={startSession} onUnavailable={startSession} />
        )}
        {phase === 'intro' && !showStartInterstitial && (
          <div className="overlay"><Loader2 className="spin" size={28} aria-hidden="true" /><p className="muted">Démarrage de la partie…</p></div>
        )}
        {phase === 'continue' && (
          <AdInterstitial position="second_chance" seconds={5} title="Seconde chance" subtitle={`Score actuel : ${pendingScore}`} primaryLabel="Continuer la partie" onPrimary={resumeGame} secondaryLabel="Terminer et valider mon score" onSecondary={finishFromContinue} onUnavailable={finishFromContinue} />
        )}
      </div>
      {error && <p className="error">{error}</p>}
      {phase === 'finished' && (
        <div className="card reward">
          {result ? (
            <>
              {!result.flagged && result.points_awarded > 0 && <Confetti />}
              <p className="muted" style={{ marginBottom: 2 }}>Score</p>
              <h3 style={{ fontSize: '1.8rem', margin: '0 0 6px' }}><AnimatedNumber value={result.score} /></h3>
              {result.flagged && <p className="error">Score refusé (partie jugée invalide).</p>}
              {!result.flagged && result.points_awarded > 0 && (
                <><div className="points-pop"><Coins className="icon-glow" size={24} strokeWidth={2} aria-hidden="true" /> <AnimatedNumber value={result.points_awarded} prefix="+" /> pts</div><p className="muted">Solde : {result.balance} pts</p><NextVoucherBar big /></>
              )}
            </>
          ) : null}
          <AdSlot key={`over-${round}`} position="game_over" />
          <button className="big" onClick={() => setRound((r) => r + 1)}><RotateCcw className="icon-glow" size={20} strokeWidth={2} aria-hidden="true" /> Rejouer</button>
        </div>
      )}
      <AdSlot key={`below-${round}`} position="game_below" />
    </>
  );
}
