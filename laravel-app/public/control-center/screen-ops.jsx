/* MedForge Control Center — Feature Flags + Services panels & screens */

const ENV_CLS = { "Producción": "prod", "Beta": "beta", "Experimental": "maint" };

/* ---- Feature flags panel (reused in detail + standalone) ---- */
function FeatureFlagsPanel({ flags, setFlags, scope }) {
  const onCount = Object.values(flags).filter(Boolean).length;
  return (
    <div className="fade-in">
      <div className="flex jb ac wrap gap10" style={{ marginBottom: 14 }}>
        <p className="muted" style={{ margin: 0, fontSize: 13 }}>
          <b style={{ color: "var(--cc-fg)" }}>{onCount}</b> de {CC_FEATURES.length} módulos activos{scope ? ` para ${scope}` : ""}.
          Cambiar un flag de riesgo alto requiere revisión del equipo de plataforma.
        </p>
        <div className="cc-seg">
          <button className="on">Todos</button>
          <button>Activos</button>
          <button>Riesgo alto</button>
        </div>
      </div>
      <Card flush>
        {CC_FEATURES.map(f => (
          <div key={f.id} className="cc-flag">
            <div>
              <div className="nm">
                <i className={`mdi ${f.icon}`} style={{ fontSize: 19, color: flags[f.id] ? "var(--cc-accent)" : "var(--cc-fg-3)" }}></i>
                {f.nombre}
              </div>
              <p className="desc">{f.desc}</p>
              <div className="meta">
                <span className={`cc-badge ${ENV_CLS[f.env]}`}>{f.env}</span>
                <span className="cc-tag">Riesgo: <RiskInline r={f.riesgo} /></span>
                <span className="cc-tag"><i className="mdi mdi-account-outline" style={{ fontSize: 13 }}></i>{f.resp}</span>
                <span className="muted" style={{ fontSize: 11.5 }}><i className="mdi mdi-clock-outline" style={{ fontSize: 12, verticalAlign: -2 }}></i> {f.mod}</span>
              </div>
            </div>
            <div className="ctrl">
              <Switch on={flags[f.id]} onClick={() => setFlags(s => ({ ...s, [f.id]: !s[f.id] }))} />
              <span style={{ font: "600 11px var(--font-mono)", color: flags[f.id] ? "var(--st-prod)" : "var(--cc-fg-3)" }}>{flags[f.id] ? "ON" : "OFF"}</span>
            </div>
          </div>
        ))}
      </Card>
    </div>
  );
}
function RiskInline({ r }) {
  const col = { bajo: "var(--st-prod)", medio: "var(--st-maint)", alto: "var(--st-susp)", crítico: "var(--st-susp)" }[r];
  return <b style={{ color: col, textTransform: "capitalize" }}>{r}</b>;
}

/* ---- Services panel (per client) ---- */
function ServicesPanel({ clientId }) {
  const svc = CC_SERVICE_STATE[clientId];
  return (
    <div className="fade-in cc-grid g2">
      {CC_SERVICE_DEFS.map(d => {
        const state = SVC_KEYMAP[svc[d.id]];
        const m = CC_SVC_META[state];
        return (
          <div key={d.id} className="cc-card" style={{ padding: "15px 18px", display: "flex", alignItems: "center", gap: 14 }}>
            <div style={{ width: 42, height: 42, borderRadius: 11, background: `${m.color}1f`, color: m.color, display: "grid", placeItems: "center", fontSize: 21, flexShrink: 0 }}>
              <i className={`mdi ${d.icon}`}></i>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ font: "600 14px var(--font-body)", color: "var(--cc-fg)" }}>{d.nombre}</div>
              <div className="muted" style={{ fontSize: 11.5, fontFamily: "var(--font-mono)" }}>
                {state === "operativo" ? "Latencia 42ms · uptime 99.9%" : state === "degradado" ? "Latencia elevada · 1.2s" : state === "error" ? "Sin respuesta · 503" : state === "pausado" ? "Detenido por suspensión" : "No aprovisionado"}
              </div>
            </div>
            <ServicePill state={state} />
          </div>
        );
      })}
    </div>
  );
}

/* ---- Standalone: Feature Flags screen (with client selector) ---- */
function ScreenFeatures({ selectedClient, onPickClient }) {
  const c = CC_CLIENTS.find(x => x.id === selectedClient) || CC_CLIENTS[0];
  const [flags, setFlags] = useState(() => { const o = {}; CC_FEATURES.forEach(f => o[f.id] = f.on); return o; });
  useEffect(() => { const o = {}; CC_FEATURES.forEach(f => o[f.id] = f.on); if (c.id === "hospitalquito") Object.keys(o).forEach(k => o[k] = false); setFlags(o); }, [c.id]);

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Feature Flags"
        sub="Activa o desactiva módulos por cliente. Los cambios se propagan al ambiente seleccionado y quedan auditados."
        actions={<ClientPicker c={c} onPick={onPickClient} />}
      />
      <FeatureFlagsPanel flags={flags} setFlags={setFlags} scope={c.nombre} />
    </div>
  );
}

/* ---- Standalone: Servicios screen ---- */
function ScreenServicios({ selectedClient, onPickClient }) {
  const [scope, setScope] = useState("matriz");
  const c = CC_CLIENTS.find(x => x.id === selectedClient) || CC_CLIENTS[0];
  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Servicios"
        sub="Salud de la infraestructura por cliente. Monitorea aplicación, base de datos, integraciones y procesos."
        actions={<div className="cc-seg">
          <button className={scope === "matriz" ? "on" : ""} onClick={() => setScope("matriz")}><i className="mdi mdi-grid"></i>Matriz global</button>
          <button className={scope === "cliente" ? "on" : ""} onClick={() => setScope("cliente")}><i className="mdi mdi-domain"></i>Por cliente</button>
        </div>}
      />
      {scope === "cliente" && <div style={{ marginBottom: "var(--gap)" }}><ClientPicker c={c} onPick={onPickClient} /></div>}
      {scope === "cliente" ? <ServicesPanel clientId={c.id} /> : <ServiceMatrix />}
    </div>
  );
}

/* ---- Global service matrix (clients × services) ---- */
function ServiceMatrix() {
  return (
    <Card flush>
      <div className="cc-tblwrap">
        <table className="cc-tbl">
          <thead><tr>
            <th>Servicio</th>
            {CC_CLIENTS.map(c => <th key={c.id} style={{ textAlign: "center" }}>{c.inicial}</th>)}
            <th style={{ textAlign: "center" }}>Salud</th>
          </tr></thead>
          <tbody>
            {CC_SERVICE_DEFS.map(d => {
              const states = CC_CLIENTS.map(c => SVC_KEYMAP[CC_SERVICE_STATE[c.id][d.id]]);
              const okCount = states.filter(s => s === "operativo").length;
              return (
                <tr key={d.id}>
                  <td><div className="flex ac gap10"><i className={`mdi ${d.icon}`} style={{ fontSize: 18, color: "var(--cc-fg-3)" }}></i><span style={{ fontWeight: 600, color: "var(--cc-fg)" }}>{d.nombre}</span></div></td>
                  {CC_CLIENTS.map((c, i) => {
                    const m = CC_SVC_META[states[i]];
                    return <td key={c.id} style={{ textAlign: "center" }} title={`${c.nombre}: ${m.label}`}>
                      <span className="svc-dot" style={{ background: m.color, width: 11, height: 11, animation: states[i] === "operativo" ? "ccPulse 2.4s infinite" : "none" }}></span>
                    </td>;
                  })}
                  <td style={{ textAlign: "center" }}><span className="cc-mono" style={{ fontSize: 12 }}>{okCount}/{CC_CLIENTS.length}</span></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="flex ac gap14 wrap" style={{ padding: "13px 18px", borderTop: "1px solid var(--cc-border)" }}>
        {Object.entries(CC_SVC_META).map(([k, m]) => (
          <span key={k} className="flex ac gap6" style={{ fontSize: 12, color: "var(--cc-fg-3)" }}><span className="svc-dot" style={{ background: m.color }}></span>{m.label}</span>
        ))}
      </div>
    </Card>
  );
}

/* ---- Client picker (compact) ---- */
function ClientPicker({ c, onPick }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="cc-clientsel">
      <button onClick={() => setOpen(o => !o)}>
        <span className="ava" style={{ background: c.color }}>{c.inicial}</span>{c.nombre}<i className="mdi mdi-chevron-down chev"></i>
      </button>
      {open && (
        <div className="cc-menu" style={{ right: 0, left: "auto" }} onMouseLeave={() => setOpen(false)}>
          <div className="head">Seleccionar cliente</div>
          {CC_CLIENTS.map(x => (
            <div key={x.id} className={`item ${x.id === c.id ? "on" : ""}`} onClick={() => { onPick(x.id); setOpen(false); }}>
              <span className="ava" style={{ background: x.color }}>{x.inicial}</span>
              <div><div className="nm">{x.nombre}</div><div className="dm">{x.dominio}</div></div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

Object.assign(window, { FeatureFlagsPanel, ServicesPanel, ScreenFeatures, ScreenServicios, ServiceMatrix, ClientPicker });
