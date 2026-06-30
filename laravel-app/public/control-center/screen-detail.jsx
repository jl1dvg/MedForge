/* MedForge Control Center — Client detail (ficha SaaS) with tabs.
   Includes Estado Operativo block + serious state-change drawer. */

function StateChangeDrawer({ client, current, onClose, onApply }) {
  const [sel, setSel] = useState(current);
  const [inicio, setInicio] = useState("");
  const [fin, setFin] = useState("");
  const [motivo, setMotivo] = useState("");
  const [confirmTxt, setConfirmTxt] = useState("");
  const s = CC_STATES[sel];
  const isSerious = sel === "lectura" || sel === "suspendido";
  const changed = sel !== current;
  const confirmOk = !isSerious || confirmTxt.trim().toUpperCase() === s.label.toUpperCase();

  return (
    <Drawer
      title="Cambiar estado operativo"
      subtitle={`${client.nombre} · ${client.dominio}`}
      onClose={onClose}
      footer={<React.Fragment>
        <button className="cc-btn line" onClick={onClose}>Cancelar</button>
        <button className={`cc-btn ${isSerious ? "danger" : "primary"}`} disabled={!changed || !confirmOk}
                onClick={() => onApply(sel, motivo)}>
          <i className={`mdi ${s.icon}`}></i>Aplicar «{s.label}»
        </button>
      </React.Fragment>}
    >
      <label className="cc-formlbl">Selecciona el nuevo estado</label>
      <div style={{ display: "grid", gap: 9, marginBottom: 22 }}>
        {Object.values(CC_STATES).map(o => (
          <div key={o.key} className={`cc-stopt ${sel === o.key ? "sel" : ""}`}
               style={{ color: `var(--st-${o.cls})` }} onClick={() => setSel(o.key)}>
            <div className="ic"><i className={`mdi ${o.icon}`}></i></div>
            <div><div className="nm">{o.label}</div><div className="dc">{o.impact.split(".")[0]}.</div></div>
            <div className="radio"></div>
          </div>
        ))}
      </div>

      {isSerious && (
        <div className="cc-alert warn" style={{ marginBottom: 20 }}>
          <i className="mdi mdi-shield-alert-outline"></i>
          <div><p className="t">Acción de licenciamiento sensible</p>
            <p className="d">Cambiar a «{s.label}» afecta directamente la operación de {client.usuarios} usuarios. Esta acción queda registrada en auditoría con tu identidad y marca de tiempo.</p></div>
        </div>
      )}

      <div className="cc-grid g2" style={{ gridTemplateColumns: "1fr 1fr", marginBottom: 18 }}>
        <div><label className="cc-formlbl">Fecha de inicio (opcional)</label>
          <input className="cc-input" type="datetime-local" value={inicio} onChange={e => setInicio(e.target.value)} /></div>
        <div><label className="cc-formlbl">Fecha de fin (opcional)</label>
          <input className="cc-input" type="datetime-local" value={fin} onChange={e => setFin(e.target.value)} /></div>
      </div>

      <div style={{ marginBottom: 18 }}>
        <label className="cc-formlbl">Motivo interno</label>
        <textarea className="cc-input" placeholder="Visible solo para el equipo de MedForge. Ej: «Mora superior a 30 días», «Mantenimiento programado»…"
                  value={motivo} onChange={e => setMotivo(e.target.value)}></textarea>
      </div>

      {s.msg && (
        <div style={{ marginBottom: 18 }}>
          <label className="cc-formlbl">Vista previa — mensaje que verá el cliente</label>
          <div className="cc-preview">
            <div className="winbar"><i></i><i></i><i></i></div>
            <div className="flex ac gap10" style={{ marginBottom: 10 }}>
              <div style={{ width: 34, height: 34, borderRadius: 9, background: `var(--st-${s.cls}-bg)`, color: `var(--st-${s.cls})`, display: "grid", placeItems: "center", fontSize: 19 }}><i className={`mdi ${s.icon}`}></i></div>
              <b style={{ font: "600 14px var(--font-display)", color: `var(--st-${s.cls})` }}>{s.label}</b>
            </div>
            <p style={{ margin: 0, fontSize: 13, lineHeight: 1.6, color: "var(--cc-fg-2)" }}>{s.msg}</p>
          </div>
        </div>
      )}

      {isSerious && (
        <div>
          <label className="cc-formlbl">Para confirmar, escribe «{s.label}»</label>
          <input className="cc-input" value={confirmTxt} onChange={e => setConfirmTxt(e.target.value)} placeholder={s.label} />
        </div>
      )}
    </Drawer>
  );
}

/* ---- Estado Operativo tab content ---- */
function TabEstado({ client, estado, history, onChangeClick }) {
  const s = CC_STATES[estado];
  return (
    <div className="fade-in">
      <div className={`cc-statebanner ${s.cls}`} style={{ marginBottom: "var(--gap)" }}>
        <div className="glyph"><i className={`mdi ${s.icon}`}></i></div>
        <div style={{ flex: 1 }}>
          <div className="flex ac gap10" style={{ marginBottom: 2 }}>
            <p className="big">{s.label}</p>
            <span style={{ fontSize: 11, color: "var(--cc-fg-3)", fontFamily: "var(--font-mono)" }}>estado actual</span>
          </div>
          <p className="imp">{s.impact}</p>
        </div>
        <button className="cc-btn line" onClick={onChangeClick} style={{ flexShrink: 0 }}><i className="mdi mdi-swap-horizontal"></i>Cambiar estado</button>
      </div>

      {estado === "lectura" && (
        <div className="cc-alert info" style={{ marginBottom: "var(--gap)" }}>
          <i className="mdi mdi-information-outline"></i>
          <div><p className="t">Mensaje activo para el cliente</p>
            <p className="d">«{CC_STATES.lectura.msg}»</p></div>
        </div>
      )}

      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Cambiar estado operativo" icon="mdi-tune-variant">
          <p className="muted" style={{ fontSize: 13, marginTop: 0, lineHeight: 1.55 }}>Define cómo opera este cliente. Los cambios sensibles (Solo lectura y Suspendido) requieren confirmación y quedan auditados.</p>
          <div style={{ display: "grid", gap: 9, marginTop: 14 }}>
            {Object.values(CC_STATES).map(o => (
              <div key={o.key} className={`cc-stopt ${estado === o.key ? "sel" : ""}`} style={{ color: `var(--st-${o.cls})`, cursor: "default" }}>
                <div className="ic"><i className={`mdi ${o.icon}`}></i></div>
                <div><div className="nm">{o.label}</div><div className="dc">{o.impact.split(".")[0]}.</div></div>
                {estado === o.key ? <span className="cc-badge acc" style={{ color: `var(--st-${o.cls})`, background: `var(--st-${o.cls}-bg)` }}>Activo</span>
                  : <button className="cc-btn line sm" onClick={onChangeClick}>Activar</button>}
              </div>
            ))}
          </div>
        </Card>

        <Card title="Historial de cambios de estado" icon="mdi-history" flush>
          <div style={{ padding: "16px 18px 4px" }}>
            <div className="cc-timeline">
              {(history || []).map((h, i) => {
                const hs = CC_STATES[h.estado];
                return (
                  <div key={i} className="cc-tl-item">
                    <div className="cc-tl-dot" style={{ background: `var(--st-${hs.cls}-bg)`, color: `var(--st-${hs.cls})` }}><i className={`mdi ${hs.icon}`}></i></div>
                    <div className="cc-tl-body">
                      <p className="t">{hs.label}</p>
                      <p className="d">{h.motivo}</p>
                      <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{h.actor}</span>· {h.when}</div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}

/* ---- Resumen tab ---- */
function TabResumen({ client }) {
  const c = client;
  const Def = ({ l, children, mono }) => (
    <div className="row"><dt>{l}</dt><dd className={mono ? "mono" : ""}>{children}</dd></div>
  );
  return (
    <div className="fade-in">
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-account-group-outline" tone="acc" label="Usuarios activos" value={c.usuarios} foot={<span className="muted">de {c.usuariosMax} permitidos</span>} />
        <Kpi icon="mdi-lifebuoy" tone={c.tickets > 5 ? "susp" : "maint"} label="Tickets abiertos" value={c.tickets} foot={<span className="muted">soporte técnico</span>} />
        <Kpi icon="mdi-brain" tone="read" label="Consumo de IA" value={c.iaPct} unit="%" foot={<span className="muted">{fmtNum(c.iaTokens)} tokens</span>} />
        <Kpi icon="mdi-database" tone="beta" label="Almacenamiento" value={c.storage} unit="GB" foot={<span className="muted">de {c.storageMax} GB</span>} />
      </div>

      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Información comercial" icon="mdi-card-account-details-outline">
          <dl className="cc-defs">
            <Def l="Nombre comercial">{c.nombre}</Def>
            <Def l="Razón social">{c.razon}</Def>
            <Def l="RUC" mono>{c.ruc}</Def>
            <Def l="Dominio principal" mono>{c.dominio}</Def>
            <Def l="Ciudad">{c.ciudad}</Def>
            <Def l="Plan contratado"><PlanBadge plan={c.plan} /></Def>
            <Def l="Estado de pago"><PayBadge pago={c.pago} label={c.pagoLabel} /></Def>
          </dl>
        </Card>
        <Card title="Contrato y contactos" icon="mdi-file-sign">
          <dl className="cc-defs">
            <Def l="Fecha de inicio" mono>{c.inicio}</Def>
            <Def l="Fecha de vencimiento" mono>{c.vence}</Def>
            <Def l="Contacto administrativo">{c.contactoAdmin.n}<br /><span className="mono muted" style={{ fontSize: 11, fontWeight: 400 }}>{c.contactoAdmin.c}</span></Def>
            <Def l="Contacto técnico">{c.contactoTec.n}<br /><span className="mono muted" style={{ fontSize: 11, fontWeight: 400 }}>{c.contactoTec.c}</span></Def>
          </dl>
        </Card>
      </div>

      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Despliegue" icon="mdi-rocket-launch-outline">
          <dl className="cc-defs">
            <Def l="Versión instalada"><span className="cc-tag">{c.version}</span></Def>
            <Def l="Canal">{c.canal}</Def>
            <Def l="Último deploy" mono>{c.ultimoDeploy}</Def>
            <Def l="Último backup" mono>{c.ultimoBackup}</Def>
          </dl>
        </Card>
        <Card title="Consumo de IA" icon="mdi-brain">
          <div className="flex jb ac"><div style={{ font: "700 26px var(--font-display)", color: "var(--cc-fg)" }}>{fmtNum(c.iaTokens)}</div><span className="cc-tag">{fmtMoney(c.iaCosto)} / mes</span></div>
          <div className="muted" style={{ fontSize: 12, margin: "2px 0 12px" }}>tokens este mes</div>
          <Progress value={c.iaPct} />
          <Sparkline data={CC_CONSUMO.iaTokens} color="var(--cc-accent)" />
        </Card>
        <Card title="WhatsApp & almacenamiento" icon="mdi-whatsapp">
          <dl className="cc-defs">
            <Def l="Mensajes enviados" mono>{fmtNum(c.waMsgs)}</Def>
            <Def l="Conversaciones" mono>{fmtNum(c.waConv)}</Def>
            <Def l="PDFs generados" mono>{fmtNum(c.pdfs)}</Def>
          </dl>
          <div style={{ marginTop: 10 }}><Progress value={c.storage} max={c.storageMax} /></div>
          <div className="muted" style={{ fontSize: 11.5, marginTop: 6 }}>{c.storage} GB de {c.storageMax} GB de almacenamiento</div>
        </Card>
      </div>
    </div>
  );
}

function ClientDetail({ clientId, onBack, onNav }) {
  const base = CC_CLIENTS.find(c => c.id === clientId);
  const [estado, setEstado] = useState(base.estado);
  const [history, setHistory] = useState(CC_STATE_HISTORY[clientId] || []);
  const [tab, setTab] = useState("resumen");
  const [drawer, setDrawer] = useState(false);
  const [flags, setFlags] = useState(() => {
    const o = {}; CC_FEATURES.forEach(f => o[f.id] = f.on); 
    if (clientId === "demo") { o.protoreact = true; o.movil = true; }
    if (clientId === "hospitalquito") { Object.keys(o).forEach(k => o[k] = false); }
    return o;
  });
  const c = { ...base, estado };

  const applyState = (newState, motivo) => {
    setEstado(newState);
    setHistory(h => [{ estado: newState, actor: "Carlos Andrade · Operaciones", motivo: motivo || "Sin motivo especificado.", when: "30 jun 2026, ahora" }, ...h]);
    setDrawer(false);
  };

  const tabs = [
    { id: "resumen", label: "Resumen", icon: "mdi-information-outline" },
    { id: "estado", label: "Estado operativo", icon: "mdi-toggle-switch-outline" },
    { id: "features", label: "Feature flags", icon: "mdi-flag-variant-outline" },
    { id: "servicios", label: "Servicios", icon: "mdi-server-network" },
    { id: "deploys", label: "Deploys", icon: "mdi-rocket-launch-outline" },
    { id: "consumo", label: "Consumo", icon: "mdi-chart-areaspline" },
  ];

  return (
    <div className="cc-page fade-in">
      <PageHead
        crumbs={<React.Fragment><a onClick={onBack}><i className="mdi mdi-home-outline"></i></a><i className="mdi mdi-chevron-right"></i><a onClick={onBack}>Clientes</a><i className="mdi mdi-chevron-right"></i>{c.nombre}</React.Fragment>}
        title={<span className="flex ac gap14"><ClientAva c={c} size={40} radius={11} />{c.nombre}</span>}
        actions={<React.Fragment>
          <button className="cc-btn line sm" onClick={onBack}><i className="mdi mdi-arrow-left"></i>Volver</button>
          <button className="cc-btn ghost sm"><i className="mdi mdi-open-in-new"></i>Abrir instancia</button>
          <button className="cc-btn primary sm" onClick={() => { setTab("estado"); setDrawer(true); }}><i className="mdi mdi-swap-horizontal"></i>Cambiar estado</button>
        </React.Fragment>}
      />

      {/* identity strip */}
      <div className="flex ac wrap gap10" style={{ marginBottom: 20 }}>
        <span className="cc-tag"><i className="mdi mdi-web" style={{ fontSize: 13 }}></i>{c.dominio}</span>
        <PlanBadge plan={c.plan} />
        <StateBadge estado={estado} />
        <PayBadge pago={c.pago} label={c.pagoLabel} />
        <span className="cc-tag">{c.version}</span>
        <span className="muted" style={{ fontSize: 12, marginLeft: 4 }}><i className="mdi mdi-clock-outline" style={{ fontSize: 13, verticalAlign: -2 }}></i> Últ. actividad {c.ultimaActividad}</span>
      </div>

      <div className="cc-tabs">
        {tabs.map(t => (
          <button key={t.id} className={tab === t.id ? "on" : ""} onClick={() => setTab(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}
            {t.id === "estado" && (estado === "lectura" || estado === "suspendido") && <span className="svc-dot" style={{ background: `var(--st-${CC_STATES[estado].cls})` }}></span>}
          </button>
        ))}
      </div>

      {tab === "resumen" && <TabResumen client={c} />}
      {tab === "estado" && <TabEstado client={c} estado={estado} history={history} onChangeClick={() => setDrawer(true)} />}
      {tab === "features" && <FeatureFlagsPanel flags={flags} setFlags={setFlags} scope={c.nombre} />}
      {tab === "servicios" && <ServicesPanel clientId={clientId} />}
      {tab === "deploys" && <DeploysPanel client={c} />}
      {tab === "consumo" && <ConsumoPanel client={c} />}

      {drawer && <StateChangeDrawer client={c} current={estado} onClose={() => setDrawer(false)} onApply={applyState} />}
    </div>
  );
}

Object.assign(window, { ClientDetail, StateChangeDrawer, TabEstado, TabResumen });
