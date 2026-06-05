/* MedForge Agendamiento — Configuración base */

function ConfigModule({ sedeId, notify }) {
  const [tab, setTab] = React.useState("horarios");
  const tabs = [
    { id: "horarios", icon: "mdi-clock-outline", label: "Horarios médicos" },
    { id: "salas", icon: "mdi-door", label: "Salas y consultorios" },
    { id: "tipos", icon: "mdi-tag-multiple-outline", label: "Tipos de cita" },
    { id: "areas", icon: "mdi-shape-outline", label: "Áreas clínicas" },
    { id: "bloqueos", icon: "mdi-cancel", label: "Bloqueos" },
  ];
  return (
    <div>
      <div className="cfg-tabs">
        {tabs.map((t) => (
          <button key={t.id} className={tab === t.id ? "on" : ""} onClick={() => setTab(t.id)}><i className={`mdi ${t.icon}`}></i>{t.label}</button>
        ))}
      </div>
      {tab === "horarios" && <CfgHorarios sedeId={sedeId} notify={notify} />}
      {tab === "salas" && <CfgSalas notify={notify} />}
      {tab === "tipos" && <CfgTipos notify={notify} />}
      {tab === "areas" && <CfgAreas />}
      {tab === "bloqueos" && <CfgBloqueos sedeId={sedeId} notify={notify} />}
    </div>
  );
}

/* ---------- Horarios médicos (grilla semanal) ---------- */
function CfgHorarios({ sedeId, notify }) {
  const dias = [1, 2, 3, 4, 5, 6];
  const meds = AG.MEDICOS.filter((m) => m.sede === sedeId);
  return (
    <Box title="Horarios por médico" icon="mdi-clock-outline"
      action={<button className="btn sm btn-outline-primary" onClick={() => notify("Editor de turnos (demo)")}><i className="mdi mdi-pencil-outline"></i>Editar turnos</button>}>
      <p className="muted" style={{ font: "400 12.5px var(--font-body)", margin: "0 0 14px" }}>
        Define los turnos de atención por día de la semana. Las excepciones puntuales (vacaciones, congresos) se gestionan en <b>Bloqueos</b>.
      </p>
      <div className="sched">
        <div className="hd"></div>
        {dias.map((d) => <div key={d} className="hd">{["", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"][d]}</div>)}
        {meds.map((m) => (
          <React.Fragment key={m.id}>
            <div className="who">
              <div className="av" style={{ background: m.color }}>{m.iniciales}</div>
              <div><div className="nm">{m.nombre}</div><div className="es">{m.esp}</div></div>
            </div>
            {dias.map((d) => {
              const turnos = AG.HORARIOS.filter((h) => h.medico === m.id && h.dia === d);
              return (
                <div key={d} className="cell">
                  {turnos.length ? turnos.map((t, i) => <div key={i} className="turno">{t.ini}–{t.fin}</div>)
                    : <div className="turno off">—</div>}
                </div>
              );
            })}
          </React.Fragment>
        ))}
      </div>
    </Box>
  );
}

/* ---------- Salas ---------- */
function CfgSalas({ notify }) {
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
      {AG.SEDES.map((sd) => (
        <Box key={sd.id} title={`Sede ${sd.label}`} icon="mdi-office-building-outline"
          action={<span className="muted" style={{ font: "500 12px var(--font-body)" }}>{sd.apertura}–{sd.cierre} · {AG.SALAS.filter((s) => s.sede === sd.id).length} espacios</span>} noPad>
          <table className="tbl">
            <thead><tr><th>Espacio</th><th>Tipo</th><th>Área</th><th>Capacidad</th><th>Estado</th></tr></thead>
            <tbody>
              {AG.SALAS.filter((s) => s.sede === sd.id).map((s) => (
                <tr key={s.id}>
                  <td className="name">{s.label}</td>
                  <td style={{ textTransform: "capitalize" }}>{s.tipo}</td>
                  <td><AreaChip id={s.area} /></td>
                  <td>{s.cap} pac.</td>
                  <td><Badge tone="success" icon="mdi-check-circle-outline">Disponible</Badge></td>
                </tr>
              ))}
            </tbody>
          </table>
        </Box>
      ))}
      <button className="btn btn-outline-primary" style={{ alignSelf: "start" }} onClick={() => notify("Alta de sala (demo)")}><i className="mdi mdi-plus"></i>Añadir espacio</button>
    </div>
  );
}

/* ---------- Tipos de cita ---------- */
function CfgTipos({ notify }) {
  return (
    <Box title="Tipos de cita" icon="mdi-tag-multiple-outline"
      action={<button className="btn sm btn-outline-primary" onClick={() => notify("Nuevo tipo de cita (demo)")}><i className="mdi mdi-plus"></i>Nuevo tipo</button>} noPad>
      <table className="tbl">
        <thead><tr><th>Tipo de cita</th><th>Área clínica</th><th>Duración</th><th>Requiere sala</th><th></th></tr></thead>
        <tbody>
          {AG.TIPOS.map((t) => (
            <tr key={t.id}>
              <td className="name"><span style={{ display: "inline-block", width: 8, height: 8, borderRadius: 2, background: area(t.area).color, marginRight: 8 }}></span>{t.label}</td>
              <td><AreaChip id={t.area} /></td>
              <td><span className="badge badge--light">{t.dur} min</span></td>
              <td className="muted" style={{ textTransform: "capitalize" }}>{t.requiereTipoSala.join(" · ")}</td>
              <td style={{ textAlign: "right" }}><button className="btn sm btn-ghost" onClick={() => notify("Editar " + t.label)}><i className="mdi mdi-pencil-outline"></i></button></td>
            </tr>
          ))}
        </tbody>
      </table>
    </Box>
  );
}

/* ---------- Áreas clínicas ---------- */
function CfgAreas() {
  return (
    <div className="cfg-grid">
      {AG.AREAS.map((a) => {
        const tipos = AG.TIPOS.filter((t) => t.area === a.id);
        const salas = AG.SALAS.filter((s) => s.area === a.id);
        const meds = AG.MEDICOS.filter((m) => m.areas.includes(a.id));
        return (
          <div key={a.id} className="cfg-card">
            <div className="ch">
              <div className="ic" style={{ background: a.bg, color: a.color }}><i className={`mdi ${a.icon}`}></i></div>
              <div><div className="nm">{a.label}</div><div className="sb">{tipos.length} tipos de cita</div></div>
            </div>
            <div className="cfg-row"><span className="k">Profesionales</span><span className="v">{meds.length}</span></div>
            <div className="cfg-row"><span className="k">Salas / espacios</span><span className="v">{salas.length}</span></div>
            <div className="cfg-row"><span className="k">Color de acento</span><span className="v"><span style={{ display: "inline-block", width: 16, height: 16, borderRadius: 4, background: a.color, verticalAlign: "middle" }}></span></span></div>
          </div>
        );
      })}
    </div>
  );
}

/* ---------- Bloqueos ---------- */
function CfgBloqueos({ sedeId, notify }) {
  const tipoMap = {
    reunion: { t: "Reunión", tone: "info", i: "mdi-account-group-outline" },
    mantenimiento: { t: "Mantenimiento", tone: "warning", i: "mdi-wrench-outline" },
    ausencia: { t: "Ausencia médica", tone: "danger", i: "mdi-account-off-outline" },
    almuerzo: { t: "Almuerzo", tone: "light", i: "mdi-food-outline" },
    vacaciones: { t: "Vacaciones", tone: "primary", i: "mdi-palm-tree" },
  };
  return (
    <Box title="Bloqueos manuales de agenda" icon="mdi-cancel"
      action={<button className="btn sm btn-outline-primary" onClick={() => notify("Nuevo bloqueo (demo)")}><i className="mdi mdi-plus"></i>Nuevo bloqueo</button>} noPad>
      <table className="tbl">
        <thead><tr><th>Tipo</th><th>Aplica a</th><th>Fecha</th><th>Horario</th><th>Motivo</th></tr></thead>
        <tbody>
          {AG.BLOQUEOS.map((b) => {
            const tm = tipoMap[b.tipo] || tipoMap.reunion;
            const quien = b.scope === "medico" ? medico(b.ref).nombre : sala(b.ref).label;
            return (
              <tr key={b.id}>
                <td><Badge tone={tm.tone} icon={tm.i}>{tm.t}</Badge></td>
                <td className="name"><i className={`mdi ${b.scope === "medico" ? "mdi-doctor" : "mdi-door"}`} style={{ color: "var(--fg-mute)", marginRight: 6 }}></i>{quien}</td>
                <td>5 jun 2026</td>
                <td><span className="badge badge--light">{b.ini}–{b.fin}</span></td>
                <td className="muted">{b.motivo}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </Box>
  );
}

Object.assign(window, { ConfigModule });
