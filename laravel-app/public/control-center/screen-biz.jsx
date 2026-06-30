/* MedForge Control Center — Licencias, Deploys, Consumo, Auditoría */

/* ============ LICENCIAS Y PLANES ============ */
function ScreenLicencias() {
  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Licencias y Planes"
        sub="Catálogo de planes comerciales, límites incluidos y estado de los contratos vigentes."
        actions={<button className="cc-btn primary sm"><i className="mdi mdi-plus"></i>Nuevo plan</button>}
      />

      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)", alignItems: "stretch" }}>
        {CC_PLAN_CARDS.map(p => (
          <div key={p.nombre} className="cc-card" style={{ padding: 0, overflow: "hidden", position: "relative", borderColor: p.destacado ? p.color : undefined, borderWidth: p.destacado ? 1.5 : 1 }}>
            <div style={{ height: 4, background: p.color }}></div>
            <div style={{ padding: "18px 20px" }}>
              {p.destacado && <span className="cc-badge acc" style={{ position: "absolute", top: 16, right: 16, color: p.color, background: `${p.color}22` }}>Más usado</span>}
              <div style={{ font: "700 17px var(--font-display)", color: "var(--cc-fg)" }}>{p.nombre}</div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 4, margin: "8px 0 4px" }}>
                {p.precio != null ? <React.Fragment><span style={{ font: "700 30px var(--font-display)", color: "var(--cc-fg)" }}>{fmtMoney(p.precio)}</span><span className="muted" style={{ fontSize: 12 }}>/ mes</span></React.Fragment>
                  : <span style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)" }}>A medida</span>}
              </div>
              <div className="muted" style={{ fontSize: 12, marginBottom: 14 }}>{p.clientes} cliente{p.clientes === 1 ? "" : "s"} activo{p.clientes === 1 ? "" : "s"}</div>
              <dl className="cc-defs" style={{ fontSize: 12.5 }}>
                <LiRow l="Usuarios" v={p.usuarios} />
                <LiRow l="Módulos" v={p.modulos} />
                <LiRow l="IA / mes" v={p.ia} />
                <LiRow l="WhatsApp" v={p.wa} />
                <LiRow l="Storage" v={p.storage} />
                <LiRow l="Soporte" v={p.soporte} />
                <LiRow l="SLA" v={p.sla} mono />
              </dl>
            </div>
          </div>
        ))}
      </div>

      <Card title="Contratos vigentes" icon="mdi-file-sign" flush>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Plan</th><th>Inicio</th><th>Vencimiento</th><th>Usuarios</th><th>Estado pago</th><th>Contrato</th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const vencido = c.pago === "vencido";
                return (
                  <tr key={c.id}>
                    <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                    <td><PlanBadge plan={c.plan} /></td>
                    <td className="cc-mono">{c.inicio}</td>
                    <td className="cc-mono" style={{ color: vencido ? "var(--st-susp)" : undefined }}>{c.vence}</td>
                    <td><span className="cc-mono">{c.usuarios}/{c.usuariosMax}</span></td>
                    <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                    <td><span className={`cc-badge ${vencido ? "susp" : c.pago === "trial" ? "beta" : "prod"}`}>{vencido ? "Renovación pendiente" : c.pago === "trial" ? "En evaluación" : "Vigente"}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
function LiRow({ l, v, mono }) { return <div className="row"><dt>{l}</dt><dd className={mono ? "mono" : ""} style={{ maxWidth: "55%" }}>{v}</dd></div>; }

/* ============ DEPLOYS Y VERSIONES ============ */
function DeploysPanel({ client }) {
  const behind = client.version !== client.versionDisp && client.versionDisp === "2026.6.1";
  return (
    <div className="fade-in">
      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Versión actual" icon="mdi-package-variant-closed"><div style={{ font: "700 26px var(--font-display)", color: "var(--cc-fg)" }}>{client.version}</div><div className="muted" style={{ fontSize: 12, marginTop: 4 }}>Canal {client.canal}</div></Card>
        <Card title="Versión disponible" icon="mdi-cloud-download-outline"><div style={{ font: "700 26px var(--font-display)", color: behind ? "var(--st-maint)" : "var(--st-prod)" }}>{client.versionDisp}</div><div className="muted" style={{ fontSize: 12, marginTop: 4 }}>{behind ? "Actualización disponible" : "Al día"}</div></Card>
        <Card title="Último deploy" icon="mdi-clock-fast"><div style={{ font: "600 16px var(--font-mono)", color: "var(--cc-fg)" }}>{client.ultimoDeploy}</div><div className="muted" style={{ fontSize: 12, marginTop: 6 }}>Responsable: Plataforma</div></Card>
      </div>
      {behind && (
        <div className="cc-alert warn" style={{ marginBottom: "var(--gap)" }}>
          <i className="mdi mdi-update"></i>
          <div className="flex jb ac" style={{ width: "100%", gap: 16 }}>
            <div><p className="t">Actualización disponible: {client.versionDisp}</p><p className="d">Esta instancia está {client.version}. Programa la actualización en una ventana de mantenimiento.</p></div>
            <button className="cc-btn primary sm" style={{ flexShrink: 0 }}><i className="mdi mdi-calendar-clock"></i>Programar actualización</button>
          </div>
        </div>
      )}
      <ReleaseTimeline current={client.version} />
    </div>
  );
}

function ReleaseTimeline({ current }) {
  return (
    <Card title="Timeline de releases" icon="mdi-source-branch" flush>
      <div style={{ padding: "18px 20px 6px" }}>
        <div className="cc-timeline">
          {CC_RELEASES.map((r, i) => (
            <div key={i} className="cc-tl-item">
              <div className="cc-tl-dot" style={{ background: `var(--st-${r.cls === "prod" ? "prod" : "beta"}-bg)`, color: `var(--st-${r.cls === "prod" ? "prod" : "beta"})` }}><i className="mdi mdi-tag-outline"></i></div>
              <div className="cc-tl-body">
                <p className="t flex ac gap10" style={{ display: "flex" }}>
                  <span className="cc-tag" style={{ fontSize: 12 }}>{r.v}</span>
                  <span className={`cc-badge ${r.canal === "Stable" ? "prod" : "beta"}`}>{r.canal}</span>
                  {r.v === current && <span className="cc-badge acc">Instalada aquí</span>}
                </p>
                <p className="d" style={{ marginTop: 4 }}>{r.titulo}</p>
                <div className="when">{r.fecha} · {r.estado}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </Card>
  );
}

function ScreenDeploys() {
  return (
    <div className="cc-page fade-in">
      <PageHead title="Deploys y Versiones" sub="Gestión de releases por cliente. Controla canales, versiones instaladas y programa actualizaciones."
        actions={<button className="cc-btn ghost sm"><i className="mdi mdi-source-branch"></i>Canales</button>} />
      <Card flush style={{ marginBottom: "var(--gap)" }}>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Cliente</th><th>Versión actual</th><th>Disponible</th><th>Canal</th><th>Último deploy</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const behind = c.version !== c.versionDisp;
                return (
                  <tr key={c.id}>
                    <td><div className="ent"><ClientAva c={c} /><div className="nm">{c.nombre}</div></div></td>
                    <td><span className="cc-tag">{c.version}</span></td>
                    <td>{behind ? <span className="cc-tag" style={{ color: "var(--st-maint)", borderColor: "color-mix(in srgb,var(--st-maint) 40%,transparent)" }}>{c.versionDisp}</span> : <span className="muted" style={{ fontSize: 12 }}>—</span>}</td>
                    <td><span className={`cc-badge ${c.canal === "Stable" ? "prod" : "beta"}`}>{c.canal}</span></td>
                    <td className="cc-mono" style={{ fontSize: 12 }}>{c.ultimoDeploy}</td>
                    <td>{behind ? <span className="cc-badge maint">Desactualizado</span> : <span className="cc-badge prod"><span className="led"></span>Al día</span>}</td>
                    <td style={{ textAlign: "right" }}><button className="cc-btn line sm" disabled={c.estado === "suspendido"}><i className="mdi mdi-calendar-clock"></i>Programar</button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
      <ReleaseTimeline current="2026.6.1" />
    </div>
  );
}

/* ============ CONSUMO ============ */
function ConsumoPanel({ client }) {
  return (
    <div className="fade-in">
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-brain" tone="acc" label="Tokens IA" value={fmtNum(client.iaTokens)} foot={<span className="muted">{fmtMoney(client.iaCosto)} estimado</span>} />
        <Kpi icon="mdi-whatsapp" tone="prod" label="Mensajes WhatsApp" value={fmtNum(client.waMsgs)} foot={<span className="muted">{fmtNum(client.waConv)} conversaciones</span>} />
        <Kpi icon="mdi-file-pdf-box" tone="read" label="PDFs generados" value={fmtNum(client.pdfs)} foot={<span className="muted">{fmtNum(client.reportes)} reportes</span>} />
        <Kpi icon="mdi-api" tone="beta" label="Llamadas API" value={fmtNum(client.apiCalls)} foot={<span className="muted">este mes</span>} />
      </div>
      <div className="cc-grid g2">
        <Card title="Consumo de IA — comparativo mensual" icon="mdi-chart-areaspline"><AreaChart data={CC_CONSUMO.iaTokens} labels={CC_MONTHS} color="var(--cc-accent)" h={190} /></Card>
        <Card title="Costo estimado de IA" icon="mdi-cash"><AreaChart data={CC_CONSUMO.iaCosto} labels={CC_MONTHS} color="var(--st-prod)" h={190} /></Card>
      </div>
    </div>
  );
}

function ScreenConsumo() {
  const [vista, setVista] = useState("global");
  return (
    <div className="cc-page fade-in">
      <PageHead title="Consumo" sub="Métricas de uso de la plataforma: IA, WhatsApp, documentos, almacenamiento y API."
        actions={<button className="cc-btn ghost sm"><i className="mdi mdi-file-excel-box"></i>Exportar</button>} />
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-brain" tone="acc" label="Tokens IA usados" value="6.9M" delta="32%" deltaDir="up" foot={<span className="muted">global · junio</span>} />
        <Kpi icon="mdi-cash" tone="prod" label="Costo estimado IA" value={fmtMoney(883)} delta="32%" deltaDir="dn" foot={<span className="muted">vs. {fmtMoney(668)} en may</span>} />
        <Kpi icon="mdi-whatsapp" tone="read" label="Mensajes WhatsApp" value="28.0K" delta="4.5%" deltaDir="up" foot={<span className="muted">4.700 conversaciones</span>} />
        <Kpi icon="mdi-folder-outline" tone="beta" label="Storage usado" value="611" unit="GB" delta="8%" deltaDir="up" foot={<span className="muted">de 1.525 GB</span>} />
      </div>

      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Tokens IA — comparativo mensual" icon="mdi-chart-areaspline" action={<span className="cc-tag">millones</span>}><AreaChart data={CC_CONSUMO.iaTokens} labels={CC_MONTHS} color="var(--cc-accent)" h={200} /></Card>
        <Card title="Mensajes WhatsApp enviados" icon="mdi-message-text-outline" action={<span className="cc-tag">miles</span>}><BarChart data={CC_CONSUMO.waMsgs} labels={CC_MONTHS} alt /></Card>
      </div>

      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="PDFs generados" icon="mdi-file-pdf-box"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>14.600</div><BarChart data={CC_CONSUMO.pdfs} labels={CC_MONTHS} /></Card>
        <Card title="Reportes exportados" icon="mdi-chart-box-outline"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>647</div><BarChart data={CC_CONSUMO.reportes} labels={CC_MONTHS} /></Card>
        <Card title="Llamadas API" icon="mdi-api"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>2.1M</div><BarChart data={CC_CONSUMO.api} labels={CC_MONTHS} alt /></Card>
      </div>

      <Card title="Consumo por cliente — IA y WhatsApp" icon="mdi-chart-bar-stacked" flush>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Cliente</th><th>Tokens IA</th><th>% del plan</th><th>Costo IA</th><th>Mensajes WA</th><th>Storage</th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => (
                <tr key={c.id}>
                  <td><div className="ent"><ClientAva c={c} /><div className="nm">{c.nombre}</div></div></td>
                  <td className="cc-mono">{fmtNum(c.iaTokens)}</td>
                  <td style={{ minWidth: 120 }}><div className="flex ac gap10"><div style={{ flex: 1 }}><Progress value={c.iaPct} /></div><span className="cc-mono" style={{ fontSize: 11.5 }}>{c.iaPct}%</span></div></td>
                  <td className="cc-mono">{fmtMoney(c.iaCosto)}</td>
                  <td className="cc-mono">{fmtNum(c.waMsgs)}</td>
                  <td className="cc-mono">{c.storage} GB</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

/* ============ AUDITORÍA ============ */
function ScreenAuditoria() {
  const [filtro, setFiltro] = useState("todos");
  const tipos = [
    { id: "todos", label: "Todos", icon: "mdi-format-list-bulleted" },
    { id: "estado", label: "Estado operativo", icon: "mdi-toggle-switch-outline" },
    { id: "licencia", label: "Licencias", icon: "mdi-license" },
    { id: "feature", label: "Features", icon: "mdi-flag-variant-outline" },
    { id: "deploy", label: "Deploys", icon: "mdi-rocket-launch-outline" },
    { id: "backup", label: "Backups", icon: "mdi-backup-restore" },
    { id: "error", label: "Errores", icon: "mdi-alert-octagon-outline" },
    { id: "soporte", label: "Soporte", icon: "mdi-lifebuoy" },
  ];
  const rows = CC_AUDIT.filter(e => filtro === "todos" || e.tipo === filtro);
  return (
    <div className="cc-page fade-in">
      <PageHead title="Auditoría" sub="Registro cronológico global de todas las acciones sensibles sobre la plataforma y sus clientes."
        actions={<button className="cc-btn ghost sm"><i className="mdi mdi-download"></i>Exportar registro</button>} />
      <div className="flex ac wrap gap6" style={{ marginBottom: "var(--gap)" }}>
        {tipos.map(t => (
          <button key={t.id} className={`cc-btn ${filtro === t.id ? "primary" : "line"} sm`} onClick={() => setFiltro(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}
          </button>
        ))}
      </div>
      <Card flush>
        <div style={{ padding: "20px 22px 4px" }}>
          <div className="cc-timeline">
            {rows.map((e, i) => (
              <div key={i} className="cc-tl-item">
                <div className="cc-tl-dot" style={{ background: e.cls === "acc" ? "var(--cc-accent-soft)" : `var(--st-${e.cls}-bg)`, color: e.cls === "acc" ? "var(--cc-accent)" : `var(--st-${e.cls})` }}><i className={`mdi ${e.icon}`}></i></div>
                <div className="cc-tl-body">
                  <p className="t flex ac gap10" style={{ display: "flex", flexWrap: "wrap" }}>{e.titulo}<span className="cc-tag" style={{ fontSize: 10.5 }}>{e.cliente}</span></p>
                  <p className="d">{e.desc}</p>
                  <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{e.actor}</span>· {e.when}</div>
                </div>
              </div>
            ))}
            {rows.length === 0 && <p className="muted" style={{ padding: "20px 0" }}>No hay eventos de este tipo en el periodo.</p>}
          </div>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { ScreenLicencias, DeploysPanel, ReleaseTimeline, ScreenDeploys, ConsumoPanel, ScreenConsumo, ScreenAuditoria });
