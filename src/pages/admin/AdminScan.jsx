import { useEffect, useRef, useState } from 'react';
import { Html5Qrcode } from 'html5-qrcode';
import api from '../../api/client';

export default function AdminScan() {
  const [scanning, setScanning] = useState(false);
  const [manual, setManual] = useState('');
  const [outcome, setOutcome] = useState(null); // { ok, message, voucher }
  const [camError, setCamError] = useState('');
  const lockRef = useRef(false);

  const verify = async (payload) => {
    if (lockRef.current) return;
    lockRef.current = true;
    try {
      const { data } = await api.post('/admin/vouchers/scan', { payload });
      setOutcome({ ok: true, message: data.message, voucher: data.voucher });
    } catch (err) {
      const data = err.response?.data;
      setOutcome({ ok: false, message: data?.message || 'Erreur lors de la vérification.', voucher: data?.voucher });
    } finally {
      lockRef.current = false;
    }
  };

  useEffect(() => {
    if (!scanning) return;
    const scanner = new Html5Qrcode('qr-reader');
    setCamError('');

    scanner
      .start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: 250 },
        (text) => {
          setScanning(false); // arrête la caméra dès qu'un QR est lu
          verify(text);
        },
        () => {}
      )
      .catch(() => setCamError("Caméra inaccessible (HTTPS requis hors localhost, et autorisation nécessaire)."));

    return () => {
      if (scanner.isScanning) {
        scanner.stop().then(() => scanner.clear()).catch(() => {});
      }
    };
  }, [scanning]);

  const submitManual = (e) => {
    e.preventDefault();
    if (manual.trim()) verify(manual.trim());
  };

  return (
    <>
      <h2>Scanner un bon</h2>
      <div className="card">
        <div id="qr-reader" />
        {camError && <p className="error">{camError}</p>}
        <button onClick={() => { setOutcome(null); setScanning((s) => !s); }}>
          {scanning ? 'Arrêter la caméra' : 'Scanner avec la caméra'}
        </button>
      </div>

      <div className="card">
        <p className="muted">Ou collez le contenu du QR (lecteur externe) :</p>
        <form onSubmit={submitManual}>
          <input value={manual} onChange={(e) => setManual(e.target.value)} placeholder="uuid.signature" />
          <button className="secondary">Vérifier</button>
        </form>
      </div>

      {outcome && (
        <div className="card" style={{ borderLeft: `6px solid ${outcome.ok ? '#1f6f3f' : '#c0392b'}` }}>
          <h3 className={outcome.ok ? 'success' : 'error'}>{outcome.message}</h3>
          {outcome.voucher && (
            <ul>
              <li>Client : <strong>{outcome.voucher.client}</strong></li>
              <li>Bon : {outcome.voucher.label} — <strong>{outcome.voucher.value_fcfa} FCFA</strong></li>
              {outcome.voucher.min_purchase_fcfa > 0 && (
                <li>Achat minimum à vérifier : <strong>{outcome.voucher.min_purchase_fcfa} FCFA</strong></li>
              )}
              <li>ID : {outcome.voucher.uuid}</li>
              {outcome.voucher.used_at && <li>Utilisé le : {new Date(outcome.voucher.used_at).toLocaleString('fr-FR')}</li>}
            </ul>
          )}
        </div>
      )}
    </>
  );
}
