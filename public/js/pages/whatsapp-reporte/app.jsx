/* ============================================================================
   MedForge · Reporte ejecutivo de WhatsApp — shell de la aplicación
   Filtros de período + sede recalculan TODO el reporte. Export a PDF imprime
   el documento completo con los filtros aplicados (la portada los refleja).
   ========================================================================== */
const { useState, useMemo, useEffect } = React;

const PERIOD_KEYS = ["hoy", "7d", "30d", "90d"];
const SEDE_OPTS = [{ id: "todas", label: "Todas" }, ...WAR.SEDES.map((s) => ({ id: s.id, label: s.label }))];

/* celda de síntesis en la portada (con delta blanco sobre fondo oscuro) */
function SynthCell({ label, value, unit, delta, deltaSuffix = "%", invert = false }) {
  let cls = "flat", icon = "mdi-minus", shown = 0;
  if (delta != null && delta !== 0) {
    const positive = delta > 0;
    const good = invert ? !positive : positive;
    cls = good ? "up" : "down";
    icon = positive ? "mdi-arrow-up-thin" : "mdi-arrow-down-thin";
    shown = Math.abs(delta);
  }
  return (
    <div className="rep-synth-cell">
      <div className="rep-synth-l">{label}</div>
      <div className="rep-synth-v">{value}{unit ? <small>{unit}</small> : null}</div>
      <div className={`rep-synth-d ${cls}`}><i className={`mdi ${icon}`}></i>{shown}{deltaSuffix} vs. anterior</div>
    </div>
  );
}

function Toolbar({ period, setPeriod, sede, setSede, onExport }) {
  return (
    <div className="rep-toolbar">
      <div className="rep-toolbar-inner">
        <div className="rep-tb-brand">
          <img src="/images/logo-light-text.png" alt="MedForge" />
          <span className="rep-tb-div"></span>
          <span className="rep-tb-tag">Reporte ejecutivo<small>Canal WhatsApp</small></span>
        </div>
        <div className="rep-filters">
          <span className="rep-flabel">Período</span>
          <div className="rep-seg">
            {PERIOD_KEYS.map((k) => (
              <button key={k} className={period === k ? "is-active" : ""} onClick={() => setPeriod(k)}>{WAR.PERIODS[k].label.replace("Últimos ", "")}</button>
            ))}
          </div>
          <span className="rep-flabel">Sede</span>
          <div className="rep-seg rep-seg--solid">
            {SEDE_OPTS.map((o) => (
              <button key={o.id} className={sede === o.id ? "is-active" : ""} onClick={() => setSede(o.id)}>{o.label}</button>
            ))}
          </div>
        </div>
        <button className="rep-btn rep-btn--primary" onClick={onExport}><i className="mdi mdi-file-pdf-box"></i>Exportar PDF</button>
      </div>
    </div>
  );
}

function Cover({ r }) {
  const s = r.summary;
  return (
    <header className="rep-cover">
      <div className="rep-cover-eyebrow"><i className="mdi mdi-whatsapp"></i>Reporte ejecutivo · Canal WhatsApp</div>
      <h1>Cómo se comportó el canal de WhatsApp</h1>
      <p className="rep-cover-lede">Una lectura ejecutiva del canal en el período seleccionado: demanda, origen, cobertura, conversión a cita, automatización y equipo. Los filtros de período y sede recalculan todas las secciones.</p>

      <div className="rep-cover-meta">
        <div className="m"><div className="ml">Período</div><div className="mv"><i className="mdi mdi-calendar-range"></i>{r.period.fromLabel} → {r.period.toLabel}</div></div>
        <div className="m"><div className="ml">Sede</div><div className="mv"><i className="mdi mdi-map-marker"></i>{r.sede.label}</div></div>
        <div className="m"><div className="ml">Meta de SLA</div><div className="mv"><i className="mdi mdi-timer-outline"></i>{r.slaTarget} min</div></div>
        <div className="m"><div className="ml">Generado</div><div className="mv"><i className="mdi mdi-clock-outline"></i>{r.generatedAt}</div></div>
      </div>

      <div className="rep-synth">
        <SynthCell label="Conversaciones nuevas" value={fmt(s.conversationsNew)} delta={s.deltas.conversations} />
        <SynthCell label="Cobertura humana" value={s.attentionRate} unit="%" delta={s.deltas.attentionRate} deltaSuffix=" pts" />
        <SynthCell label="1ª respuesta (mediana)" value={s.medianFirstResp} unit=" min" delta={s.deltas.medianResp} deltaSuffix=" min" invert />
        <SynthCell label="Citas agendadas" value={fmt(s.bookings)} delta={s.deltas.bookings} />
      </div>
    </header>
  );
}

function App() {
  const [period, setPeriod] = useState("30d");
  const [sede, setSede] = useState("todas");
  const r = useMemo(() => WAR.report(period, sede), [period, sede]);

  useEffect(() => { document.title = `Reporte WhatsApp · ${WAR.PERIODS[period].label} · ${r.sede.label}`; }, [period, sede, r]);

  const onExport = () => window.print();

  return (
    <div className="rep-app">
      <Toolbar period={period} setPeriod={setPeriod} sede={sede} setSede={setSede} onExport={onExport} />

      {/* cabecera solo-impresión con filtros aplicados */}
      <div className="rep-print-head" style={{ alignItems: "center", justifyContent: "space-between", padding: "0 0 10px", marginBottom: 8, borderBottom: "1px solid #e4e6ef" }}>
        <img src="/images/logo-light-text.png" alt="MedForge" style={{ height: 22 }} />
        <span style={{ font: '600 11px "IBM Plex Sans", sans-serif', color: "#5e6278" }}>
          Reporte ejecutivo WhatsApp · {r.period.fromLabel} → {r.period.toLabel} · {r.sede.label}
        </span>
      </div>

      <main className="rep-doc">
        <Cover r={r} />
        <SecDemanda r={r} />
        <SecOrigen r={r} />
        <SecCobertura r={r} />
        <SecConversion r={r} />
        <SecBot r={r} />
        <SecEquipo r={r} />
        <SecHallazgos r={r} />

        <footer className="rep-footnote">
          <span className="fn-legend"><span className="fn-chip">Nuevo</span>KPIs propuestos, pendientes de implementación en base de datos — enviados a investigación.</span>
          <span className="fn-brand">MedForge by Consulmed · Reporte generado automáticamente</span>
        </footer>
      </main>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
