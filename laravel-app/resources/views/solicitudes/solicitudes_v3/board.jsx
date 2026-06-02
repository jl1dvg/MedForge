/* ============================================================
   MedForge · Solicitudes v2 — toolbar, board, table
   ============================================================ */

function Toolbar({ filters, setFilters, preset, setPreset, view, setView, doctors, afiliaciones }) {
  return (
    <div className="toolbar">
      <div className="search-box">
        <i className="mdi mdi-magnify"></i>
        <input
          placeholder="Buscar paciente, HC o procedimiento…"
          value={filters.search}
          onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
        />
      </div>

      <select className="filter-select" value={filters.afiliacion} onChange={(e) => setFilters((f) => ({ ...f, afiliacion: e.target.value }))}>
        <option value="">Todas las afiliaciones</option>
        {Object.entries(afiliaciones).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
      </select>

      <select className="filter-select" value={filters.doctor} onChange={(e) => setFilters((f) => ({ ...f, doctor: e.target.value }))}>
        <option value="">Todos los doctores</option>
        {doctors.map((d) => <option key={d} value={d}>{d}</option>)}
      </select>

      <div className="preset-group">
        <button className={`preset ${preset === "mis-casos" ? "is-active" : ""}`} onClick={() => setPreset(preset === "mis-casos" ? "" : "mis-casos")}>
          <i className="mdi mdi-account-check-outline"></i>Mis casos
        </button>
        <button className={`preset is-danger ${preset === "urgentes" ? "is-active" : ""}`} onClick={() => setPreset(preset === "urgentes" ? "" : "urgentes")}>
          <i className="mdi mdi-alert-circle-outline"></i>Urgentes
        </button>
      </div>

      <div className="toolbar-spacer"></div>

      <div className="view-toggle">
        <button className={view === "kanban" ? "is-active" : ""} onClick={() => setView("kanban")}>
          <i className="mdi mdi-view-column-outline"></i>Kanban
        </button>
        <button className={view === "tabla" ? "is-active" : ""} onClick={() => setView("tabla")}>
          <i className="mdi mdi-table"></i>Tabla
        </button>
        <button className={view === "conciliacion" ? "is-active" : ""} onClick={() => setView("conciliacion")}>
          <i className="mdi mdi-sync"></i>Conciliación
        </button>
      </div>
    </div>
  );
}

const PHASE_COL_DOT = {
  ingreso: "#3d7ac7", validacion: "#6f67d8", aptitud: "#1f9d7a", agenda: "#d59623",
};

function Column({ col, items, onOpen, onAdvance, dnd, lastSlug }) {
  const breaches = items.filter((s) => s.sla_status === "vencido").length;
  return (
    <div
      className={`col ${dnd.dropTarget === col.slug ? "is-droptarget" : ""}`}
      onDragOver={(e) => dnd.onDragOver(e, col.slug)}
      onDragLeave={dnd.onDragLeave}
      onDrop={(e) => dnd.onDrop(e, col.slug)}
    >
      <div className="col-head">
        <span className="col-dot" style={{ background: PHASE_COL_DOT[col.phase] }}></span>
        <h3 className="col-title">{col.label}</h3>
        {breaches > 0 && <span className="col-breach"><i className="mdi mdi-alert"></i>{breaches}</span>}
        <span className="col-count">{items.length}</span>
      </div>
      <div className="col-body">
        {items.length === 0 && <div className="col-empty">Sin solicitudes</div>}
        {items.map((sol) => (
          <Card
            key={sol.id}
            sol={sol}
            onOpen={onOpen}
            onAdvance={onAdvance}
            isLast={col.slug === lastSlug}
            dnd={dnd}
          />
        ))}
      </div>
    </div>
  );
}

function Board({ columns, phases, byColumn, onOpen, onAdvance, dnd, groupPhases }) {
  const lastSlug = columns[columns.length - 1].slug;
  if (!groupPhases) {
    return (
      <div className="board-scroll">
        <div className="board flat">
          <div className="pg-cols">
            {columns.map((col) => (
              <Column key={col.slug} col={col} items={byColumn[col.slug] || []}
                onOpen={onOpen} onAdvance={onAdvance} dnd={dnd} lastSlug={lastSlug} />
            ))}
          </div>
        </div>
      </div>
    );
  }
  return (
    <div className="board-scroll">
      <div className="board">
        {phases.map((ph) => {
          const phaseCols = columns.filter((c) => c.phase === ph.key);
          return (
            <div className="phase-group" key={ph.key}>
              <div className="phase-band">
                <i className={`mdi ${ph.icon}`}></i>{ph.label}<span className="ph-line"></span>
              </div>
              <div className="pg-cols">
                {phaseCols.map((col) => (
                  <Column
                    key={col.slug}
                    col={col}
                    items={byColumn[col.slug] || []}
                    onOpen={onOpen}
                    onAdvance={onAdvance}
                    dnd={dnd}
                    lastSlug={lastSlug}
                  />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function TableView({ rows, onOpen }) {
  if (!rows.length) {
    return (
      <div className="board-scroll">
        <div className="empty-state">
          <i className="mdi mdi-clipboard-search-outline"></i>
          <h3>Sin resultados</h3>
          <p>Ajusta los filtros para ver solicitudes.</p>
        </div>
      </div>
    );
  }
  return (
    <div className="board-scroll">
      <div className="table-wrap">
        <table className="sol-table">
          <thead>
            <tr>
              <th>Paciente</th><th>Procedimiento</th><th>Doctor</th><th>Afiliación</th>
              <th>Estado</th><th>Progreso</th><th>SLA</th><th>Turno</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((sol) => (
              <tr key={sol.id} onClick={() => onOpen(sol.id)}>
                <td>
                  <div className="t-name" style={{ display: "flex", alignItems: "center", gap: 7 }}>
                    {sol.prioridad === "urgente" && <span className="urgent-flag"></span>}
                    {sol.full_name}
                  </div>
                  <div className="t-sub">HC {sol.hc_number} · {sol.form_id}</div>
                </td>
                <td><div style={{ fontWeight: 600, fontSize: 12.5 }}>{sol.procedimiento_short} <span className="card-sla sla-ok" style={{ padding: "1px 6px", fontSize: 10 }}>{sol.ojo}</span></div></td>
                <td style={{ fontSize: 12.5 }}>{sol.doctor}</td>
                <td><AfilChip sol={sol} /></td>
                <td><span className="state-pill">{sol.estado_label}</span></td>
                <td>
                  <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                    <span className="t-mini-bar"><i style={{ width: `${sol.checklist_progress.percent}%` }}></i></span>
                    <span style={{ fontSize: 11, color: "var(--fg-mute)", fontWeight: 600 }}>{sol.checklist_progress.percent}%</span>
                  </div>
                </td>
                <td><SlaBadge sol={sol} /></td>
                <td style={{ fontSize: 12, color: "var(--fg-3)" }}>{sol.turno || "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

Object.assign(window, { Toolbar, Board, Column, TableView });
