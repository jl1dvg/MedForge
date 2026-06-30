/* MedForge Control Center — Overview screen */

function ScreenOverview({ onOpenClient, onNav, env }) {
  const activos = CC_CLIENTS.filter(c => c.estado === "produccion").length;
  const enRiesgo = CC_CLIENTS.filter(c => c.riesgo === "alto" || c.riesgo === "crítico").length;
  const suspendidos = CC_CLIENTS.filter(c => c.estado === "suspendido").length;
  const porVencer = CC_CLIENTS.filter(c => ["09 jul 2026", "04 ago 2026", "19 ene 2026", "08 feb 2026"].includes(c.vence)).length;

  // global service health counts
  let counts = { operativo: 0, degradado: 0, error: 0, pausado: 0, no_config: 0 };
  Object.values(CC_SERVICE_STATE).forEach(svc => Object.values(svc).forEach(v => counts[SVC_KEYMAP[v]]++));
  const totalSvc = Object.values(counts).reduce((a, b) => a + b, 0);

  const iaMonthly = CC_CONSUMO.iaTokens;
  const waMonthly = CC_CONSUMO.waMsgs;

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Overview"
        sub="Estado global de la plataforma MedForge — clientes, operación, consumo y eventos críticos en un solo lugar."
        actions={<React.Fragment>
          <button className="cc-btn line sm"><i className="mdi mdi-calendar-range"></i>Junio 2026</button>
          <button className="cc-btn ghost sm"><i className="mdi mdi-file-pdf-box"></i>Exportar</button>
        </React.Fragment>}
      />

      {/* KPI strip */}
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-domain" tone="prod" label="Clientes activos" value={activos} delta="100% SLA" deltaDir="up"
             foot={<span className="muted">de {CC_CLIENTS.length} cuentas totales</span>} />
        <Kpi icon="mdi-alert-rhombus-outline" tone="susp" label="Clientes en riesgo" value={enRiesgo} delta="+1" deltaDir="dn"
             foot={<span className="muted">pago vencido o incidencias</span>} />
        <Kpi icon="mdi-server-off" tone="maint" label="Servicios con incidencia" value={counts.error + counts.degradado} delta={counts.error + " en error"} deltaDir="flat"
             foot={<span className="muted">{counts.pausado} pausados por suspensión</span>} />
        <Kpi icon="mdi-license" tone="read" label="Licencias por vencer" value={porVencer} delta="≤ 30 días" deltaDir="flat"
             foot={<span className="muted">requieren renovación</span>} />
      </div>

      {/* critical alerts */}
      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <div className="cc-alert danger">
          <i className="mdi mdi-cancel"></i>
          <div><p className="t">Hospital Quito está suspendido</p>
            <p className="d">Acceso bloqueado desde el 10 mar 2026 por contrato vencido y mora. 12 tickets abiertos pendientes de gestión administrativa.</p></div>
        </div>
        <div className="cc-alert warn">
          <i className="mdi mdi-eye-lock-outline"></i>
          <div><p className="t">Salud Visual en Solo lectura</p>
            <p className="d">Suspensión parcial automática por factura vencida. Los usuarios solo pueden consultar información hasta regularizar el pago.</p></div>
        </div>
      </div>

      {/* consumption row */}
      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Consumo mensual de IA" icon="mdi-brain"
              action={<div className="flex ac gap10"><span className="cc-tag">{fmtMoney(883)} jun</span><span className="cc-delta up"><i className="mdi mdi-arrow-up-thin"></i>32%</span></div>}>
          <div className="flex jb ac" style={{ marginBottom: 10 }}>
            <div><div className="cc-kpi-inline" style={{ font: "700 28px var(--font-display)", color: "var(--cc-fg)" }}>6.9M</div>
              <div className="muted" style={{ fontSize: 12 }}>tokens consumidos este mes</div></div>
          </div>
          <AreaChart data={iaMonthly} labels={CC_MONTHS} color="var(--cc-accent)" h={170} />
        </Card>
        <Card title="Consumo de WhatsApp" icon="mdi-whatsapp"
              action={<div className="flex ac gap10"><span className="cc-tag">28.0K msj</span><span className="cc-delta up"><i className="mdi mdi-arrow-up-thin"></i>4.5%</span></div>}>
          <div className="flex jb ac" style={{ marginBottom: 10 }}>
            <div><div style={{ font: "700 28px var(--font-display)", color: "var(--cc-fg)" }}>4.700</div>
              <div className="muted" style={{ fontSize: 12 }}>conversaciones activas este mes</div></div>
          </div>
          <AreaChart data={waMonthly} labels={CC_MONTHS} color="var(--cc-accent-2)" h={170} fmt={(v)=>v+"K"} />
        </Card>
      </div>

      {/* servers health + critical events */}
      <div className="cc-grid g21">
        <Card title="Estado general de servidores" icon="mdi-server-network"
              action={<button className="cc-btn line sm" onClick={() => onNav("servicios")}>Ver servicios<i className="mdi mdi-arrow-right"></i></button>}>
          <div className="flex ac gap14" style={{ marginBottom: 18 }}>
            <Donut centerValue={Math.round(counts.operativo / totalSvc * 100) + "%"} centerLabel="operativo"
              segments={[
                { pct: Math.round(counts.operativo / totalSvc * 100), color: "var(--st-prod)", label: "Operativo", val: counts.operativo },
                { pct: Math.round(counts.degradado / totalSvc * 100), color: "var(--st-maint)", label: "Degradado", val: counts.degradado },
                { pct: Math.round(counts.error / totalSvc * 100), color: "var(--st-susp)", label: "Error", val: counts.error },
                { pct: Math.round(counts.pausado / totalSvc * 100), color: "var(--st-mute)", label: "Pausado / no config.", val: counts.pausado + counts.no_config },
              ]} />
            <div style={{ flex: 1, minWidth: 0 }}>
              {CC_CLIENTS.map(c => {
                const svc = CC_SERVICE_STATE[c.id];
                const states = Object.values(svc).map(v => SVC_KEYMAP[v]);
                const err = states.filter(s => s === "error").length;
                const deg = states.filter(s => s === "degradado").length;
                const pau = states.filter(s => s === "pausado").length;
                const dotColor = err ? "var(--st-susp)" : deg ? "var(--st-maint)" : pau ? "var(--st-mute)" : "var(--st-prod)";
                const txt = err ? `${err} en error` : deg ? `${deg} degradado` : pau ? "pausado" : "todo operativo";
                return (
                  <div key={c.id} className="flex ac jb" style={{ padding: "8px 0", borderBottom: "1px solid var(--cc-border)" }}>
                    <div className="flex ac gap10"><ClientAva c={c} size={26} /><span style={{ fontWeight: 600, fontSize: 13 }}>{c.nombre}</span></div>
                    <span className="flex ac gap6" style={{ fontSize: 12, color: "var(--cc-fg-3)" }}><span className="svc-dot" style={{ background: dotColor }}></span>{txt}</span>
                  </div>
                );
              })}
            </div>
          </div>
        </Card>

        <Card title="Últimos eventos críticos" icon="mdi-timeline-alert-outline" flush
              action={<button className="cc-btn line sm" onClick={() => onNav("auditoria")}>Auditoría</button>}>
          <div style={{ padding: "16px 18px 4px" }}>
            <div className="cc-timeline">
              {CC_AUDIT.slice(0, 5).map((e, i) => (
                <div key={i} className="cc-tl-item">
                  <div className="cc-tl-dot" style={{ background: `var(--st-${e.cls === "acc" ? "read" : e.cls}-bg)`, color: e.cls === "acc" ? "var(--cc-accent)" : `var(--st-${e.cls})` }}>
                    <i className={`mdi ${e.icon}`}></i>
                  </div>
                  <div className="cc-tl-body">
                    <p className="t">{e.titulo}</p>
                    <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{e.actor}</span>· {e.when}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </Card>
      </div>

      {/* clients quick strip */}
      <Card title="Clientes" icon="mdi-domain" flush style={{ marginTop: "var(--gap)" }}
            action={<button className="cc-btn line sm" onClick={() => onNav("clientes")}>Ver todos<i className="mdi mdi-arrow-right"></i></button>}>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Plan</th><th>Estado</th><th>Usuarios</th><th>Versión</th><th>Pago</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => (
                <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                  <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                  <td><PlanBadge plan={c.plan} /></td>
                  <td><StateBadge estado={c.estado} /></td>
                  <td className="cc-mono">{c.usuarios}</td>
                  <td><span className="cc-tag">{c.version}</span></td>
                  <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                  <td style={{ textAlign: "right" }}><i className="mdi mdi-chevron-right" style={{ color: "var(--cc-fg-3)", fontSize: 20 }}></i></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { ScreenOverview });
