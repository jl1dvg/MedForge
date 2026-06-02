/* ============================================================
   MedForge · Solicitudes v2 — Conciliación de cirugías
   Cruza solicitudes con su protocolo quirúrgico posterior.
   ============================================================ */

function concStatus(sol) {
  const confirmed = sol.protocolo_confirmado || sol.estado === "completado";
  if (confirmed) return "confirmada";
  if (sol.protocolo_posterior_compatible) return "pendiente";
  return "sin-match";
}

const CONC_FILTERS = [
  { key: "all", label: "Todas", icon: "mdi-format-list-bulleted" },
  { key: "matched", label: "Con match", icon: "mdi-link-variant" },
  { key: "confirmed", label: "Confirmadas", icon: "mdi-check-decagram-outline" },
  { key: "unmatched", label: "Sin match", icon: "mdi-link-variant-off" },
];

const CONC_STATUS_META = {
  "confirmada": { cls: "ok", label: "Confirmada", icon: "mdi-check-decagram" },
  "pendiente": { cls: "warn", label: "Pendiente confirmación", icon: "mdi-progress-clock" },
  "sin-match": { cls: "none", label: "Sin match", icon: "mdi-help-circle-outline" },
};

function ProtocolCell({ sol }) {
  const p = sol.protocolo_confirmado || sol.protocolo_posterior_compatible;
  const confirmed = !!sol.protocolo_confirmado;
  if (!p) return <span style={{ color: "var(--fg-fade)", fontSize: 12.5 }}>Sin coincidencia</span>;
  return (
    <div className="proto-cell">
      <div className="proto-id"><i className="mdi mdi-file-document-check-outline"></i>#{p.form_id} <span className="proto-eye">{p.lateralidad}</span></div>
      <div className="proto-meta">{p.membrete}</div>
      <div className="proto-date">
        <i className="mdi mdi-calendar-blank-outline"></i>{fmtDate(p.fecha_inicio)}
        {confirmed && <span className="proto-conf"> · confirmado por {sol.protocolo_confirmado.confirmado_by}</span>}
      </div>
    </div>
  );
}

function ConciliacionView({ rows, onConfirm }) {
  const [mode, setMode] = React.useState("all");

  const stats = React.useMemo(() => {
    let conMatch = 0, confirmadas = 0;
    rows.forEach((s) => {
      const st = concStatus(s);
      if (st !== "sin-match") conMatch++;
      if (st === "confirmada") confirmadas++;
    });
    return { total: rows.length, conMatch, confirmadas };
  }, [rows]);

  const visible = React.useMemo(() => rows.filter((s) => {
    const st = concStatus(s);
    if (mode === "matched") return st === "pendiente";
    if (mode === "confirmed") return st === "confirmada";
    if (mode === "unmatched") return st === "sin-match";
    return true;
  }), [rows, mode]);

  const counts = React.useMemo(() => {
    const c = { all: rows.length, matched: 0, confirmed: 0, unmatched: 0 };
    rows.forEach((s) => {
      const st = concStatus(s);
      if (st === "pendiente") c.matched++;
      else if (st === "confirmada") c.confirmed++;
      else c.unmatched++;
    });
    return c;
  }, [rows]);

  return (
    <div className="board-scroll">
      <div className="conc-head">
        <div className="conc-intro">
          <h2 className="conc-title"><i className="mdi mdi-sync-circle"></i>Conciliación de cirugías</h2>
          <p className="conc-sub">
            <span className="conc-period"><i className="mdi mdi-calendar-range"></i>02 may – 02 jun 2026</span>
            <b>{stats.total}</b> solicitudes · <b>{stats.conMatch}</b> con protocolo compatible · <b style={{ color: "var(--success)" }}>{stats.confirmadas}</b> confirmadas
          </p>
        </div>
        <div className="conc-filters">
          {CONC_FILTERS.map((f) => (
            <button key={f.key} className={`conc-pill ${mode === f.key ? "is-active" : ""}`} onClick={() => setMode(f.key)}>
              <i className={`mdi ${f.icon}`}></i>{f.label}
              <span className="conc-pill-n">{counts[f.key]}</span>
            </button>
          ))}
        </div>
      </div>

      {visible.length === 0 ? (
        <div className="empty-state">
          <i className="mdi mdi-sync-off"></i>
          <h3>Nada que conciliar</h3>
          <p>No hay solicitudes en este filtro para el periodo seleccionado.</p>
        </div>
      ) : (
        <div className="table-wrap">
          <table className="sol-table conc-table">
            <thead>
              <tr>
                <th>Fecha</th><th>Paciente</th><th>Procedimiento solicitado</th><th>Ojo</th>
                <th>Protocolo posterior compatible</th><th>Estado</th><th style={{ textAlign: "right" }}>Acción</th>
              </tr>
            </thead>
            <tbody>
              {visible.map((sol) => {
                const st = concStatus(sol);
                const meta = CONC_STATUS_META[st];
                const canConfirm = st === "pendiente";
                return (
                  <tr key={sol.id} className={st === "confirmada" ? "row-confirmed" : ""}>
                    <td style={{ whiteSpace: "nowrap", fontSize: 12.5, color: "var(--fg-3)" }}>{fmtDate(sol.fecha)}</td>
                    <td>
                      <div className="t-name">{sol.full_name}</div>
                      <div className="t-sub">HC {sol.hc_number} · {sol.form_id}</div>
                    </td>
                    <td style={{ fontSize: 12.5, maxWidth: 230 }}>{sol.procedimiento}</td>
                    <td><span className="card-sla sla-ok" style={{ padding: "1px 7px", fontSize: 10.5 }}>{sol.ojo}</span></td>
                    <td><ProtocolCell sol={sol} /></td>
                    <td><span className={`conc-status conc-${meta.cls}`}><i className={`mdi ${meta.icon}`}></i>{meta.label}</span></td>
                    <td style={{ textAlign: "right" }}>
                      {canConfirm ? (
                        <button className="conc-confirm-btn" onClick={() => onConfirm(sol.id)}>
                          <i className="mdi mdi-check-circle-outline"></i>Confirmar y completar
                        </button>
                      ) : <span style={{ color: "var(--fg-fade)" }}>—</span>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

Object.assign(window, { ConciliacionView, concStatus });
