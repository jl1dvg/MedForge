/* MedForge Control Center — Clientes (listado + filtros) */

function ScreenClientes({ onOpenClient }) {
  const [fEstado, setFEstado] = useState("todos");
  const [fPlan, setFPlan] = useState("todos");
  const [fCiudad, setFCiudad] = useState("todas");
  const [fPago, setFPago] = useState("todos");
  const [q, setQ] = useState("");

  const rows = CC_CLIENTS.filter(c =>
    (fEstado === "todos" || c.estado === fEstado) &&
    (fPlan === "todos" || c.plan === fPlan) &&
    (fCiudad === "todas" || c.ciudad === fCiudad) &&
    (fPago === "todos" || c.pago === fPago) &&
    (q === "" || c.nombre.toLowerCase().includes(q.toLowerCase()) || c.dominio.includes(q.toLowerCase()))
  );

  const ciudades = [...new Set(CC_CLIENTS.map(c => c.ciudad))];

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Clientes"
        sub="Todas las organizaciones que operan sobre MedForge. Filtra por estado, plan, ciudad o vencimiento."
        actions={<React.Fragment>
          <button className="cc-btn ghost sm"><i className="mdi mdi-file-excel-box"></i>Exportar</button>
          <button className="cc-btn primary sm"><i className="mdi mdi-plus"></i>Nuevo cliente</button>
        </React.Fragment>}
      />

      <Card style={{ marginBottom: "var(--gap)" }}>
        <div className="cc-filters">
          <div className="cc-search" style={{ maxWidth: 260, flex: "0 0 260px" }}>
            <i className="mdi mdi-magnify"></i>
            <input placeholder="Buscar empresa o dominio…" value={q} onChange={e => setQ(e.target.value)} />
          </div>
          <div className="cc-field"><label>Estado operativo</label>
            <select value={fEstado} onChange={e => setFEstado(e.target.value)}>
              <option value="todos">Todos</option>
              <option value="produccion">Producción</option>
              <option value="mantenimiento">Mantenimiento</option>
              <option value="lectura">Solo lectura</option>
              <option value="suspendido">Suspendido</option>
            </select></div>
          <div className="cc-field"><label>Plan</label>
            <select value={fPlan} onChange={e => setFPlan(e.target.value)}>
              <option value="todos">Todos</option>
              <option>Enterprise</option><option>Professional</option><option>Starter</option><option>Trial</option><option>Custom</option>
            </select></div>
          <div className="cc-field"><label>Pago</label>
            <select value={fPago} onChange={e => setFPago(e.target.value)}>
              <option value="todos">Todos</option>
              <option value="ok">Al día</option><option value="vencido">Vencido</option><option value="trial">Trial</option>
            </select></div>
          <div className="cc-field"><label>Ciudad</label>
            <select value={fCiudad} onChange={e => setFCiudad(e.target.value)}>
              <option value="todas">Todas</option>
              {ciudades.map(c => <option key={c}>{c}</option>)}
            </select></div>
          <div style={{ flex: 1 }}></div>
          <button className="cc-btn line sm" onClick={() => { setFEstado("todos"); setFPlan("todos"); setFCiudad("todas"); setFPago("todos"); setQ(""); }}>
            <i className="mdi mdi-filter-remove-outline"></i>Limpiar
          </button>
        </div>
      </Card>

      <Card flush>
        <div className="flex jb ac" style={{ padding: "13px 18px", borderBottom: "1px solid var(--cc-border)" }}>
          <span style={{ fontSize: 12.5, color: "var(--cc-fg-3)" }}>Mostrando <b style={{ color: "var(--cc-fg)" }}>{rows.length}</b> de {CC_CLIENTS.length} clientes</span>
          <span className="cc-tag"><i className="mdi mdi-update" style={{ fontSize: 13 }}></i> Actualizado hace 1 min</span>
        </div>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr>
              <th>Empresa</th><th>Plan</th><th>Estado</th><th>Usuarios</th><th>Últ. actividad</th><th>Versión</th><th>Consumo IA</th><th>Pago</th><th></th>
            </tr></thead>
            <tbody>
              {rows.map(c => (
                <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                  <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                  <td><PlanBadge plan={c.plan} /></td>
                  <td><StateBadge estado={c.estado} /></td>
                  <td><span className="cc-mono">{c.usuarios}</span><span className="muted" style={{ fontSize: 11 }}> / {c.usuariosMax}</span></td>
                  <td className="muted" style={{ fontSize: 12.5 }}>{c.ultimaActividad}</td>
                  <td><span className="cc-tag">{c.version}</span></td>
                  <td style={{ minWidth: 130 }}>
                    <div className="flex ac gap10">
                      <div style={{ flex: 1 }}><Progress value={c.iaPct} /></div>
                      <span className="cc-mono" style={{ fontSize: 11.5 }}>{c.iaPct}%</span>
                    </div>
                  </td>
                  <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                  <td style={{ textAlign: "right" }}>
                    <button className="cc-btn line sm" onClick={(e) => { e.stopPropagation(); onOpenClient(c.id); }}>Ver detalle</button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr><td colSpan="9" style={{ textAlign: "center", padding: 40, color: "var(--cc-fg-3)" }}>No hay clientes que coincidan con los filtros aplicados.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { ScreenClientes });
