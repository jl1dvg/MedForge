/* ============================================================================
   MedForge · Reporte ejecutivo WhatsApp — secciones narrativas (5–7)
   ========================================================================== */

/* ---- Sección 5 · Automatización (bot vs humano) ---- */
function SecBot({ r }) {
  const s = r.summary;
  const botData = [
    { label: "Contenido por bot", total: s.containmentRate },
    { label: "Escalado a humano", total: Math.round((100 - s.containmentRate) * 10) / 10 },
  ];
  const sevCont = s.containmentRate >= 62 ? "success" : s.containmentRate >= 50 ? "warning" : "danger";
  return (
    <section className="rep-section">
      <SectionHead num="05" kicker="¿Cuánto resolvió solo?" title="Automatización y handoff"
        lede={`El bot de Flowmaker contuvo el <b>${s.containmentRate}%</b> de las conversaciones e identificó al paciente en el <b>${s.identificationRate}%</b> de los casos. El resto se derivó a un agente — entender por qué es la palanca para escalar el canal.`} />

      <div className="rep-grid rep-grid--4" style={{ marginTop: 6 }}>
        <KpiCard icon="mdi-robot-outline" accent={sevCont} label="Tasa de contención"
          value={s.containmentRate} unit="%" delta={s.deltas.containment} deltaSuffix=" pts"
          sub={`<b>${fmt(s.resolvedBot)}</b> conversaciones resueltas sin humano`} />
        <KpiCard icon="mdi-card-account-details-outline" accent="info" label="Identificación de paciente"
          value={s.identificationRate} unit="%" sub={`Vinculadas automáticamente a una HC`} />
        <KpiCard icon="mdi-account-arrow-right-outline" accent="warning" label="Handoffs a agente"
          value={fmt(s.handoffs)} sub={`<b>${s.handoffRate}%</b> de las conversaciones`} />
        <NuevoCard icon="mdi-heart-pulse" label="Sentimiento del paciente" ghost="—" chart={false}
          desc="Clasificación NLP del tono (positivo / neutro / frustrado) por conversación para anticipar quejas. Requiere modelo de análisis de sentimiento." />
      </div>

      <div className="rep-grid rep-grid--3" style={{ marginTop: 16 }}>
        <Card title="Contención vs. escalamiento" icon="mdi-robot-happy-outline">
          <DonutChart data={botData} unit="%" colors={["#05825f", "#ee3158"]} />
          <div className="rep-chart-legend" style={{ marginTop: 12, justifyContent: "center" }}>
            <span className="rep-leg"><b style={{ background: "#05825f" }}></b>Contenido por bot</span>
            <span className="rep-leg"><b style={{ background: "#ee3158" }}></b>Escalado a humano</span>
          </div>
        </Card>

        <Card title="Principales fricciones que fuerzan el handoff" icon="mdi-alert-octagon-outline" className="rep-span2"
          note={`${fmt(s.handoffs)} handoffs en el período`}>
          <BarList items={r.frictions} palette={["#ee3158", "#ffa800", "#5156be", "#3596f7", "#05825f"]} />
          <p className="rep-kpi-sub" style={{ marginTop: 16, paddingTop: 14, borderTop: "1px dashed var(--border-soft)" }}>
            Cada fricción resuelta en Flowmaker libera capacidad del equipo y acelera la primera respuesta. Las dos primeras causas explican la mayor parte de los handoffs.
          </p>
        </Card>
      </div>
    </section>
  );
}

/* ---- Sección 6 · Equipo y sedes ---- */
function SecEquipo({ r }) {
  const teamMax = Math.max(...r.teams.map((t) => t.total), 1);
  const initials = (n) => n.replace(/^(Dra?|Lcda?|Lic)\.?\s*/i, "").split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  return (
    <section className="rep-section">
      <SectionHead num="06" kicker="¿Quién lo sostuvo?" title="Equipo y sedes"
        lede={`El trabajo humano detrás de las cifras: cómo se repartió la carga entre agentes y equipos, y cómo se comparan <b>Ceibos</b> y <b>Villa Club</b> en el período.`} />

      <div className="rep-grid rep-grid--3" style={{ marginTop: 6 }}>
        <Card title="Desempeño por agente" icon="mdi-account-group-outline" className="rep-span2"
          note={`${r.agents.length} agentes activos`}>
          <table className="rep-table">
            <thead>
              <tr>
                <th>Agente</th>
                <th>Equipo</th>
                <th className="num">Atendidas</th>
                <th className="num">Resueltas</th>
                <th className="num">Resp. prom.</th>
                <th className="num">Conv.</th>
              </tr>
            </thead>
            <tbody>
              {r.agents.map((a, i) => (
                <tr key={a.name}>
                  <td className="name">
                    <span style={{ display: "inline-flex", alignItems: "center", gap: 10 }}>
                      <span className="rep-av">{initials(a.name)}</span>{a.name}
                    </span>
                  </td>
                  <td>{a.role}</td>
                  <td className="num">{fmt(a.attended)}</td>
                  <td className="num">{fmt(a.resolved)}</td>
                  <td className="num">{a.avgRespMin} min</td>
                  <td className="num"><span className={`rep-tag rep-tag--${a.convRate >= 18 ? "success" : "muted"}`}>{a.convRate}%</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>

        <Card title="Handoffs por equipo" icon="mdi-account-switch-outline">
          <div style={{ display: "flex", flexDirection: "column", gap: 13 }}>
            {r.teams.map((t) => (
              <div key={t.name}>
                <div className="rep-bar-top">
                  <span className="rep-bar-name">{t.name}</span>
                  <span className="rep-bar-meta"><strong>{fmt(t.total)}</strong></span>
                </div>
                <div className="rep-bar-track" style={{ display: "flex", background: "var(--bg-soft)" }}>
                  <div style={{ width: `${(t.resolved / teamMax) * 100}%`, background: "#05825f", height: "100%" }}></div>
                  <div style={{ width: `${(t.assigned / teamMax) * 100}%`, background: "#3596f7", height: "100%" }}></div>
                  <div style={{ width: `${(t.queued / teamMax) * 100}%`, background: "#ffa800", height: "100%" }}></div>
                </div>
              </div>
            ))}
          </div>
          <div className="rep-chart-legend" style={{ marginTop: 14, paddingTop: 13, borderTop: "1px dashed var(--border-soft)" }}>
            <span className="rep-leg"><b style={{ background: "#05825f" }}></b>Resueltas</span>
            <span className="rep-leg"><b style={{ background: "#3596f7" }}></b>Asignadas</span>
            <span className="rep-leg"><b style={{ background: "#ffa800" }}></b>En cola</span>
          </div>
        </Card>
      </div>

      {r.bySede.length > 1 && (
        <div className="rep-grid rep-grid--2" style={{ marginTop: 16 }}>
          {r.bySede.map((sd) => (
            <Card key={sd.id} title={sd.label} icon="mdi-map-marker-outline" note={sd.zone}>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 14, marginTop: 2 }}>
                <SedeStat value={fmt(sd.conversations)} label="Conversaciones" />
                <SedeStat value={`${sd.bookings}`} label="Citas" />
                <SedeStat value={`${sd.share}%`} label="Del total" />
                <SedeStat value={`${sd.attentionRate}%`} label="Cobertura" tone={sd.attentionRate >= 85 ? "success" : "warning"} />
                <SedeStat value={`${sd.bookingRate}%`} label="Conversión" tone="primary" />
                <SedeStat value={`${sd.medianResp} min`} label="1ª respuesta" tone={sd.medianResp <= r.slaTarget ? "success" : "warning"} />
              </div>
            </Card>
          ))}
        </div>
      )}
    </section>
  );
}

function SedeStat({ value, label, tone }) {
  const color = tone === "success" ? "var(--success)" : tone === "warning" ? "#8a5d0a" : tone === "primary" ? "var(--primary)" : "var(--fg-1)";
  return (
    <div style={{ background: "var(--bg-soft)", borderRadius: 10, padding: "13px 14px" }}>
      <div style={{ font: '600 22px/1 "Rubik", sans-serif', color, fontVariantNumeric: "tabular-nums" }}>{value}</div>
      <div style={{ font: '600 10px "IBM Plex Sans", sans-serif', textTransform: "uppercase", letterSpacing: ".05em", color: "var(--fg-mute)", marginTop: 6 }}>{label}</div>
    </div>
  );
}

/* ---- Sección 7 · Hallazgos y recomendaciones ---- */
function SecHallazgos({ r }) {
  const accent = { success: "var(--success)", warning: "var(--warning)", danger: "var(--danger)", primary: "var(--primary)" };
  return (
    <section className="rep-section">
      <SectionHead num="07" kicker="La síntesis" title="Hallazgos y acciones sugeridas"
        lede={`Lo que el período nos dice y qué hacer al respecto. Cuatro lecturas del canal y un plan corto, priorizado por impacto sobre la conversión a cita.`} />

      <div className="rep-grid rep-grid--2" style={{ marginTop: 6 }}>
        {r.insights.map((ins, i) => (
          <div className="rep-insight" key={i} style={{ "--ins-accent": accent[ins.tone] || accent.primary }}>
            <div className="rep-insight-h"><span className="dot"></span><h4>{ins.title}</h4></div>
            <p>{ins.body}</p>
          </div>
        ))}
      </div>

      <Card title="Acciones sugeridas" icon="mdi-clipboard-check-outline" className="rep-span3" style={{ marginTop: 16 }}>
        <div className="rep-recs">
          {r.recommendations.map((rec, i) => (
            <div className="rep-rec" key={i}>
              <span className="rep-rec-num">{i + 1}</span>
              <p>{rec}</p>
            </div>
          ))}
        </div>
      </Card>
    </section>
  );
}

Object.assign(window, { SecBot, SecEquipo, SecHallazgos, SedeStat });
