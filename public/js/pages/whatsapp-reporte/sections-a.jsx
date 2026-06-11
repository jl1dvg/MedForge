/* ============================================================================
   MedForge · Reporte ejecutivo WhatsApp — secciones narrativas (1–4)
   ========================================================================== */

/* ---- Sección 1 · Demanda del canal ---- */
function SecDemanda({ r }) {
  const s = r.summary;
  const sparkConv = r.trend.map((t) => t.conversaciones);
  return (
    <section className="rep-section">
      <SectionHead num="01" kicker="El pulso del canal" title="Demanda del canal"
        lede={`En <b>${r.period.label.toLowerCase()}</b> el canal recibió <b>${fmt(s.conversationsNew)} conversaciones nuevas</b> de <b>${fmt(s.peopleInbound)} personas</b>. Aquí empieza la historia: cuánta gente buscó a la clínica por WhatsApp y cómo se distribuyó esa demanda en el tiempo.`} />

      <Read icon="mdi-trending-up" html={r.insights[0].body} />

      <div className="rep-grid rep-grid--4" style={{ marginTop: 18 }}>
        <KpiCard icon="mdi-message-text-outline" accent="primary" label="Conversaciones nuevas"
          value={fmt(s.conversationsNew)} delta={s.deltas.conversations} spark={sparkConv}
          sub={`<b>${fmt(s.peopleInbound)}</b> personas únicas escribieron`} />
        <KpiCard icon="mdi-account-multiple-outline" accent="info" label="Personas inbound"
          value={fmt(s.peopleInbound)} delta={s.deltas.people}
          sub={`Números únicos en el período`} />
        <KpiCard icon="mdi-forum-outline" accent="success" label="Mensajes intercambiados"
          value={fmt(s.messagesTotal)}
          sub={`<b>${fmt(s.messagesIn)}</b> entrantes · <b>${fmt(s.messagesOut)}</b> salientes`} />
        <NuevoCard icon="mdi-account-reactivate-outline" label="Pacientes reactivados" ghost={`${s.reactivationRate}%`}
          desc="Pacientes inactivos +6 meses que volvieron a contactar por WhatsApp. Requiere cruce con historia clínica." />
      </div>

      <div className="rep-grid rep-grid--3" style={{ marginTop: 16 }}>
        <Card title="Volumen del canal en el tiempo" icon="mdi-chart-areaspline" className="rep-span2"
          headRight={<div className="rep-chart-legend">
            <span className="rep-leg"><b style={{ background: "#5156be" }}></b>Conversaciones</span>
            <span className="rep-leg"><b style={{ background: "#3596f7" }}></b>Atendidas</span>
            <span className="rep-leg line"><b style={{ background: "#05825f" }}></b>Citas</span>
          </div>}>
          <TrendChart data={r.trend} />
        </Card>

        <Card title="Reparto por sede" icon="mdi-map-marker-outline" note={`${r.sede.label}`}>
          {r.bySede.length > 1 ? (
            <BarList items={r.bySede} accessor={(x) => x.conversations} palette={["#5156be", "#3596f7"]}
              metaFmt={(x) => <><strong>{fmt(x.conversations)}</strong> · {x.share}%</>} />
          ) : (
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              <div className="rep-kpi-value" style={{ fontSize: 38 }}>{fmt(r.bySede[0].conversations)}</div>
              <p className="rep-kpi-sub">Conversaciones atribuidas a <b>{r.bySede[0].label}</b> en el período. Cambia el filtro de sede a <b>Todas</b> para comparar.</p>
            </div>
          )}
          <div style={{ marginTop: "auto", paddingTop: 14 }}>
            <Heatmap r={r} />
          </div>
        </Card>
      </div>
    </section>
  );
}

/* mapa de calor demanda hora × día */
function Heatmap({ r }) {
  const color = (v) => {
    const t = v / r.heatMax;
    if (t < 0.12) return "#f3f6f9";
    if (t < 0.3) return "#dfe3f6";
    if (t < 0.5) return "#bcc2ee";
    if (t < 0.72) return "#8f95df";
    return "#5156be";
  };
  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 9 }}>
        <span className="rep-card-note" style={{ fontWeight: 600, color: "var(--fg-2)" }}>Concentración horaria</span>
        <span className="rep-heat-scale">menos<span className="sw">{["#f3f6f9", "#dfe3f6", "#bcc2ee", "#8f95df", "#5156be"].map((c, i) => <i key={i} style={{ background: c }}></i>)}</span>más</span>
      </div>
      <div className="rep-heat">
        {r.heat.map((row) => (
          <div className="rep-heat-row" key={row.day}>
            <span className="rep-heat-axis">{row.day}</span>
            {row.cells.map((c) => (
              <div className="rep-heat-cell" key={c.hour} style={{ background: color(c.value), color: c.value / r.heatMax > 0.55 ? "#fff" : "transparent" }}>{c.value}</div>
            ))}
          </div>
        ))}
      </div>
      <div className="rep-heat-hours">
        <span></span>
        {r.hours.map((h) => <span key={h}>{h}h</span>)}
      </div>
    </div>
  );
}

/* ---- Sección 2 · Origen de la demanda ---- */
function SecOrigen({ r }) {
  const top = r.sources[0];
  return (
    <section className="rep-section">
      <SectionHead num="02" kicker="¿De dónde vienen?" title="Origen de la demanda"
        lede={`<b>${top.label}</b> concentra el <b>${top.share}%</b> de las conversaciones del período. Conocer el origen permite atribuir cada cita —y, a futuro, cada dólar— al canal que la generó.`} />

      <div className="rep-grid rep-grid--3" style={{ marginTop: 6 }}>
        <Card title="Mezcla de orígenes" icon="mdi-chart-donut" className="rep-span1"
          headRight={<span className="rep-card-note">{fmt(r.summary.conversationsNew)} convs.</span>}>
          <DonutChart data={r.sources} />
          <div className="rep-chart-legend" style={{ marginTop: 12 }}>
            {r.sources.map((sm, i) => (
              <span className="rep-leg" key={sm.id}><b style={{ background: PALETTE[i % PALETTE.length] }}></b>{sm.label} · {sm.share}%</span>
            ))}
          </div>
        </Card>

        <Card title="Rendimiento por origen" icon="mdi-bullseye-arrow" className="rep-span2"
          note="conversión y carga operativa">
          <table className="rep-table">
            <thead>
              <tr>
                <th>Origen</th>
                <th className="num">Convs.</th>
                <th className="num">Part.</th>
                <th className="num">Identif.</th>
                <th className="num">Citas</th>
                <th className="num">Conv.</th>
              </tr>
            </thead>
            <tbody>
              {r.sources.map((sm) => (
                <tr key={sm.id}>
                  <td className="name"><span style={{ display: "inline-flex", alignItems: "center", gap: 9 }}><i className={`mdi ${sm.icon}`} style={{ color: "var(--fg-mute)", fontSize: 17 }}></i>{sm.label}</span></td>
                  <td className="num">{fmt(sm.total)}</td>
                  <td className="num">{sm.share}%</td>
                  <td className="num">{fmt(sm.identified)}</td>
                  <td className="num">{fmt(sm.bookings)}</td>
                  <td className="num"><span className={`rep-tag rep-tag--${sm.bookingRate >= 22 ? "success" : sm.bookingRate >= 16 ? "warning" : "muted"}`}>{sm.bookingRate}%</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </div>

      <div className="rep-grid rep-grid--3" style={{ marginTop: 16 }}>
        <Card title="Intención inicial declarada" icon="mdi-tag-text-outline" className="rep-span2">
          <BarList items={r.intents} palette={PALETTE} />
        </Card>
        <NuevoCard icon="mdi-cash-multiple" label="Costo por conversación (CAC)" ghost="$ —"
          desc="Inversión publicitaria (Meta / Google Ads) dividida entre las conversaciones generadas por cada origen. Requiere integración con las APIs de campañas." />
      </div>
    </section>
  );
}

/* ---- Sección 3 · Cobertura y respuesta ---- */
function SecCobertura({ r }) {
  const s = r.summary;
  const sevCov = s.attentionRate >= 85 ? "success" : s.attentionRate >= 75 ? "warning" : "danger";
  const sevResp = s.medianFirstResp <= r.slaTarget ? "success" : s.medianFirstResp <= r.slaTarget * 2 ? "warning" : "danger";
  return (
    <section className="rep-section">
      <SectionHead num="03" kicker="¿Atendimos bien?" title="Cobertura y velocidad de respuesta"
        lede={`De cada conversación que necesitó una persona, se atendió al <b>${s.attentionRate}%</b>, con una mediana de <b>${s.medianFirstResp} min</b> a la primera respuesta humana frente a una meta de <b>${r.slaTarget} min</b>.`} />

      <div className="rep-grid rep-grid--4" style={{ marginTop: 6 }}>
        <KpiCard icon="mdi-account-heart-outline" accent={sevCov} label="Cobertura humana"
          value={s.attentionRate} unit="%" delta={s.deltas.attentionRate} deltaSuffix=" pts"
          sub={`<b>${fmt(s.attendedHuman)}</b> atendidas · <b>${fmt(s.lostNeedsHuman)}</b> sin respuesta`} />
        <KpiCard icon="mdi-timer-sand" accent={sevResp} label="1ª respuesta (mediana)"
          value={s.medianFirstResp} unit=" min" delta={s.deltas.medianResp} deltaInvert deltaSuffix=" min"
          sub={`P75 en <b>${s.p75FirstResp} min</b> · meta ${r.slaTarget} min`} />
        <KpiCard icon="mdi-check-decagram-outline" accent={s.slaRate >= 80 ? "success" : "warning"} label="Cumplimiento de SLA"
          value={s.slaRate} unit="%" sub={`Respuestas dentro de meta en horario laboral`} />
        <NuevoCard icon="mdi-emoticon-happy-outline" label="Satisfacción (CSAT)" ghost={`${s.csat}%`} chart={false}
          desc="Encuesta de 1 toque al cerrar la conversación. Requiere disparador post-cierre y tabla de respuestas." />
      </div>

      <div className="rep-grid rep-grid--3" style={{ marginTop: 16 }}>
        <Card title="Distribución del tiempo de primera respuesta" icon="mdi-chart-bar" className="rep-span2"
          headRight={<div className="rep-chart-legend">
            <span className="rep-leg"><b style={{ background: "#05825f" }}></b>Dentro de SLA</span>
            <span className="rep-leg"><b style={{ background: "#c8c9ee" }}></b>Fuera de SLA</span>
          </div>}>
          <HistogramChart data={r.responseDist} slaTarget={r.slaTarget} />
        </Card>

        <Card title="Resolución de lo atendido" icon="mdi-pie-chart-outline">
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            <CoverageBar label="Atendidas por agente" value={s.attendedHuman} total={s.conversationsNew} color="#3596f7" />
            <CoverageBar label="Resueltas por el bot" value={s.resolvedBot} total={s.conversationsNew} color="#05825f" />
            <CoverageBar label="Resueltas tras handoff" value={s.resolved} total={s.conversationsNew} color="#5156be" />
            <CoverageBar label="Pidieron ayuda sin respuesta" value={s.lostNeedsHuman} total={s.conversationsNew} color="#ee3158" />
          </div>
          <p className="rep-kpi-sub" style={{ marginTop: 16, paddingTop: 14, borderTop: "1px dashed var(--border-soft)" }}>
            La cobertura combina automatización y trabajo humano: el bot absorbe la base y el equipo concentra los casos que requieren criterio clínico.
          </p>
        </Card>
      </div>
    </section>
  );
}

function CoverageBar({ label, value, total, color }) {
  const pct = total > 0 ? Math.round((value / total) * 100) : 0;
  return (
    <div>
      <div className="rep-bar-top">
        <span className="rep-bar-name">{label}</span>
        <span className="rep-bar-meta"><strong>{fmt(value)}</strong> · {pct}%</span>
      </div>
      <div className="rep-bar-track"><div className="rep-bar-fill" style={{ width: `${pct}%`, background: color }}></div></div>
    </div>
  );
}

/* ---- Sección 4 · Conversión a cita ---- */
function SecConversion({ r }) {
  const s = r.summary;
  const bestLc = [...r.lifecycle].sort((a, b) => b.bookingRate - a.bookingRate)[0];
  const fnMax = r.funnel[0].value || 1;
  const fnColors = ["#5156be", "#3596f7", "#0863be", "#05825f"];
  return (
    <section className="rep-section">
      <SectionHead num="04" kicker="¿Convirtió?" title="Conversión a cita"
        lede={`El canal generó <b>${fmt(s.bookings)} citas</b> en Sigcenter — un <b>${s.bookingRate}%</b> de quienes escribieron. El segmento <b>${bestLc.label.toLowerCase()}</b> convierte mejor (${bestLc.bookingRate}%) que la captación fría.`} />

      <div className="rep-grid rep-grid--4" style={{ marginTop: 6 }}>
        <KpiCard icon="mdi-calendar-check-outline" accent="success" label="Citas agendadas"
          value={fmt(s.bookings)} delta={s.deltas.bookings}
          sub={`<b>${fmt(s.bookingPatients)}</b> pacientes · <b>${fmt(s.bookingFailures)}</b> intentos fallidos`} />
        <KpiCard icon="mdi-percent-outline" accent="primary" label="Tasa de conversión"
          value={s.bookingRate} unit="%" delta={s.deltas.bookingRate} deltaSuffix=" pts"
          sub={`Citas sobre personas que escribieron`} />
        <KpiCard icon="mdi-account-check-outline" accent="info" label="Pacientes agendados"
          value={fmt(s.bookingPatients)} sub={`Vinculados a una historia clínica`} />
        <NuevoCard icon="mdi-cash-register" label="Ingreso atribuido al canal" ghost="$ —"
          desc="Valor facturado de las cirugías y consultas que nacieron de una cita de WhatsApp. Requiere cruce cita → cirugía → facturación." />
      </div>

      <div className="rep-grid rep-grid--3" style={{ marginTop: 16 }}>
        <Card title="Embudo de servicio" icon="mdi-filter-variant" className="rep-span1"
          headRight={<span className="rep-tag rep-tag--success">{s.bookingRate}% a cita</span>}>
          <div className="rep-funnel">
            {r.funnel.map((f, i) => (
              <div className="rep-fn-row" key={f.label}>
                <span className="rep-fn-label">{f.label}</span>
                <div className="rep-fn-bar-wrap">
                  <div className="rep-fn-bar" style={{ width: `${Math.max(16, (f.value / fnMax) * 100)}%`, background: fnColors[i] }}>
                    <strong>{fmt(f.value)}</strong>
                  </div>
                  {f.rateToNext != null && <span className="rep-fn-drop"><i className="mdi mdi-arrow-down-thin"></i>{f.rateToNext}%</span>}
                </div>
                <span className="rep-fn-pct">{f.rateFromStart}%</span>
              </div>
            ))}
          </div>
        </Card>

        <Card title="Conversión por segmento de paciente" icon="mdi-shape-outline" className="rep-span2"
          note="dónde rinde mejor el canal">
          <table className="rep-table">
            <thead>
              <tr>
                <th>Segmento (ciclo de vida)</th>
                <th className="num">Convs.</th>
                <th className="num">Part.</th>
                <th className="num">Identif.</th>
                <th className="num">Citas</th>
                <th className="num">Conversión</th>
              </tr>
            </thead>
            <tbody>
              {r.lifecycle.map((l) => (
                <tr key={l.label}>
                  <td className="name">{l.label}</td>
                  <td className="num">{fmt(l.total)}</td>
                  <td className="num">{l.share}%</td>
                  <td className="num">{fmt(l.identified)}</td>
                  <td className="num">{fmt(l.bookings)}</td>
                  <td className="num">
                    <span style={{ display: "inline-flex", alignItems: "center", justifyContent: "flex-end", gap: 0 }}>
                      <span className="rep-mini-bar"><i style={{ width: `${Math.min(100, l.bookingRate * 3)}%`, background: l === bestLc ? "#05825f" : "#5156be" }}></i></span>
                      <b style={{ minWidth: 38, textAlign: "right", color: "var(--fg-1)" }}>{l.bookingRate}%</b>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </div>
    </section>
  );
}

Object.assign(window, { SecDemanda, SecOrigen, SecCobertura, SecConversion, Heatmap, CoverageBar });
