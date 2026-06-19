import React from 'react';
import { createRoot } from 'react-dom/client';
import { Cover, Footnote } from '../shared/lib';
import { Toolbar } from './toolbar';
import { WhatsappContent } from './sections';
import type { WhatsappReport } from './types';

const TITLE = 'Cómo rindió la atención por WhatsApp';
const LEDE = 'Lectura ejecutiva de la operación de WhatsApp en el período: del primer contacto a la cita agendada. Empieza por el volumen y la atención humana, y baja al detalle de origen, intención, ciclo de vida y friction.';

function App() {
  const r: WhatsappReport = window.MF_WA_REPORT;
  const filters = window.MF_WA_FILTERS ?? { period: '30d', agentId: null };
  const s = r.summary;

  React.useEffect(() => {
    document.title = `Reporte WhatsApp · ${r.period?.fromLabel ?? ''} → ${r.period?.toLabel ?? ''}`;
  }, []);

  const humanAppointments = s.humanAttributedAppointments ?? 0;
  const humanDelta = s.deltas.humanAppointments ?? 0;

  const synth = [
    { label: 'Conversaciones nuevas', value: s.conversationsNew.toLocaleString('es-EC'), delta: s.deltas.conversations },
    { label: 'Tasa de atención', value: `${Math.round(s.attentionRate)}%`, delta: s.deltas.attentionRate },
    { label: 'Mediana 1ra respuesta', value: s.medianFirstResp !== null ? `${Math.round(s.medianFirstResp)} min` : '—', delta: s.deltas.medianResp, invert: true },
    {
      label: 'Citas humanas atrib.',
      value: humanAppointments.toLocaleString('es-EC'),
      delta: humanDelta,
    },
  ];

  return (
    <div className="rep-app" data-unit="whatsapp">
      <Toolbar period={filters.period} />
      <main className="rep-doc">
        <Cover
          unit="whatsapp"
          unitLabel="WhatsApp"
          unitIcon="mdi-whatsapp"
          title={TITLE}
          lede={LEDE}
          period={r.period}
          sede={r.sede}
          generatedAt={r.generatedAt}
          synth={synth}
        />
        <WhatsappContent r={r} />
        <Footnote />
      </main>
    </div>
  );
}

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
