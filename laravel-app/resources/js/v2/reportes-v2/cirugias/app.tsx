import React from 'react';
import { createRoot } from 'react-dom/client';
import { Cover, Section, ExecutiveMap, Footnote } from '../shared/lib';
import { Toolbar } from './toolbar';
import { CirugiasContent } from './sections';
import type { CirugiasReport } from './types';

const TITLE = 'Cómo rindió la unidad de Cirugías';
const LEDE  = 'Lectura ejecutiva del quirófano en el período: de la solicitud al cobro. Empieza por el mapa financiero —dónde se gana, se bloquea o se pierde— y baja al detalle de producción, procedimientos, equipo y calidad.';

function App() {
  const r: CirugiasReport = window.MF_CIR_REPORT;
  const sedeOptions = window.MF_CIR_SEDE_OPTIONS ?? [{ value: '', label: 'Todas las sedes' }];
  const filters     = window.MF_CIR_FILTERS ?? { startDate: '', endDate: '', sede: '' };

  React.useEffect(() => {
    document.title = `Reporte Cirugías · ${r.period?.fromLabel ?? ''} → ${r.period?.toLabel ?? ''}`;
  }, []);

  return (
    <div className="rep-app" data-unit="cirugias">
      <Toolbar
        startDate={filters.startDate}
        endDate={filters.endDate}
        sede={filters.sede}
        sedeOptions={sedeOptions}
      />
      <main className="rep-doc">
        <Cover
          unit={r.unit}
          unitLabel={r.unitLabel}
          unitIcon={r.unitIcon}
          title={TITLE}
          lede={LEDE}
          period={r.period}
          sede={r.sede}
          generatedAt={r.generatedAt}
          synth={r.synth}
        />
        <Section num="01" kicker="Mapa ejecutivo financiero"
          title="El flujo conectado, de la solicitud al cobro"
          lede="Conecta cada KPI financiero con la etapa del flujo que lo origina, para distinguir facturado real, oportunidad estimada, pendiente de pago y pérdida.">
          <ExecutiveMap exec={r.exec} unit={r.unit} />
        </Section>
        <CirugiasContent r={r} />
        <Footnote />
      </main>
    </div>
  );
}

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
