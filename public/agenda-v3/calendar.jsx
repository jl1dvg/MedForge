/* MedForge Agendamiento — Calendario operativo (día / semana) */

const DAY_START = 8 * 60;   // 08:00
const DAY_END   = 18 * 60;  // 18:00
const DIAS_SEM = ["", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];

function Calendario({ state, tweaks, sedeId, nowMin, onNew, onOpen }) {
  const [vista, setVista] = React.useState("dia");     // dia | semana
  const [grupo, setGrupo] = React.useState("medico");  // medico | sala
  const [fArea, setFArea] = React.useState("");
  const [fMed, setFMed]   = React.useState("");

  const slotMin = tweaks.slotMin;
  const pxPerMin = tweaks.density === "compacta" ? 0.82 : tweaks.density === "comoda" ? 1.32 : 1.05;
  const totalPx = (DAY_END - DAY_START) * pxPerMin;
  const slotPx = slotMin * pxPerMin;
  const hourPx = 60 * pxPerMin;

  const citasHoy = state.citas.filter((c) => c.sede === sedeId &&
    (!fArea || c.area === fArea) && (!fMed || c.medico === fMed) &&
    (tweaks.showSobre || !c.sobreturno));
  const bloqueosHoy = tweaks.showBloqueos ? state.bloqueos.filter((b) => b.fecha === AG.HOY) : [];

  /* ---------- recursos (columnas) ---------- */
  let recursos;
  if (grupo === "medico") {
    recursos = AG.MEDICOS.filter((m) => m.sede === sedeId && (!fArea || m.areas.includes(fArea)) && (!fMed || m.id === fMed))
      .map((m) => ({ id: m.id, nm: m.nombre, sub: m.esp, color: m.color, ini: m.iniciales, key: "medico" }));
  } else {
    recursos = AG.SALAS.filter((s) => s.sede === sedeId && (!fArea || s.area === fArea))
      .map((s) => ({ id: s.id, nm: s.label, sub: area(s.area).label, color: area(s.area).color, ini: s.label.slice(0, 2).toUpperCase(), key: "sala" }));
  }

  const colsCss = `56px repeat(${recursos.length}, minmax(168px, 1fr))`;
  const fechaLarga = new Date(AG.HOY + "T00:00:00").toLocaleDateString("es-EC", { weekday: "long", day: "numeric", month: "long", year: "numeric" });

  /* ---------- toolbar ---------- */
  const toolbar = (
    <div className="cal-toolbar">
      <div className="seg">
        <button className={vista === "dia" ? "on" : ""} onClick={() => setVista("dia")}><i className="mdi mdi-calendar-today"></i>Día</button>
        <button className={vista === "semana" ? "on" : ""} onClick={() => setVista("semana")}><i className="mdi mdi-calendar-week"></i>Semana</button>
      </div>
      <div className="daynav">
        <button className="btn icon-btn"><i className="mdi mdi-chevron-left"></i></button>
        <span className="today-label">{vista === "dia" ? fechaLarga : "2 – 7 jun 2026"}</span>
        <button className="btn icon-btn"><i className="mdi mdi-chevron-right"></i></button>
      </div>
      <div className="spacer"></div>
      <div className="cal-filters">
        {vista === "dia" && (
          <div className="seg" style={{ marginRight: 4 }}>
            <button className={grupo === "medico" ? "on" : ""} onClick={() => setGrupo("medico")}>Por médico</button>
            <button className={grupo === "sala" ? "on" : ""} onClick={() => setGrupo("sala")}>Por sala</button>
          </div>
        )}
        <select value={fArea} onChange={(e) => setFArea(e.target.value)}>
          <option value="">Todas las áreas</option>
          {AG.AREAS.map((a) => <option key={a.id} value={a.id}>{a.label}</option>)}
        </select>
        <select value={fMed} onChange={(e) => setFMed(e.target.value)}>
          <option value="">Todos los médicos</option>
          {AG.MEDICOS.filter((m) => m.sede === sedeId).map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
        </select>
        <button className="btn btn-primary" onClick={() => onNew({})}><i className="mdi mdi-plus"></i>Nueva cita</button>
      </div>
    </div>
  );

  const legend = (
    <div className="area-legend">
      {AG.AREAS.map((a) => (
        <span key={a.id} className="lg"><span className="sw" style={{ background: a.color }}></span>{a.label}</span>
      ))}
      <span className="div"></span>
      <span className="note"><i className="mdi mdi-flash" style={{ color: "var(--warning)" }}></i>Sobreturno</span>
      <span className="note"><i className="mdi mdi-cancel" style={{ color: "var(--fg-fade)" }}></i>Bloqueo</span>
      <span className="div"></span>
      <span className="note"><i className="mdi mdi-circle" style={{ color: "var(--danger)", fontSize: 9 }}></i>Hora actual {toHHMM(nowMin)}</span>
    </div>
  );

  if (vista === "semana") return (<React.Fragment>{toolbar}{legend}<SemanaView state={state} sedeId={sedeId} fMed={fMed} pxPerMin={pxPerMin * 0.9} onOpen={onOpen} /></React.Fragment>);

  /* ---------- DÍA ---------- */
  // contar citas por recurso
  const citasDe = (r) => citasHoy.filter((c) => c[r.key] === r.id);

  return (
    <React.Fragment>
      {toolbar}
      {legend}
      <div className="cal-grid-wrap">
        <div className="cal-head" style={{ gridTemplateColumns: colsCss }}>
          <div className="gutter-h"></div>
          {recursos.map((r) => (
            <div key={r.id} className="cal-res-h">
              <div className="av" style={{ background: r.color }}>{r.ini}</div>
              <div style={{ minWidth: 0 }}>
                <div className="nm">{r.nm}</div>
                <div className="sub">{r.sub}</div>
              </div>
              <span className="cnt">{citasDe(r).length}</span>
            </div>
          ))}
        </div>
        <div className="cal-body" style={{ gridTemplateColumns: colsCss }}>
          {/* gutter */}
          <div className="cal-gutter" style={{ height: totalPx }}>
            {Array.from({ length: (DAY_END - DAY_START) / 60 + 1 }).map((_, i) => (
              <div key={i} className="cal-hour" style={{ top: i * hourPx, transform: i === 0 ? "none" : "translateY(-7px)" }}>
                {toHHMM(DAY_START + i * 60)}
              </div>
            ))}
          </div>
          {/* columnas */}
          {recursos.map((r) => (
            <ColumnaDia key={r.id} recurso={r} citas={citasDe(r)} bloqueos={bloqueosHoy}
              pxPerMin={pxPerMin} slotPx={slotPx} hourPx={hourPx} totalPx={totalPx} slotMin={slotMin}
              nowMin={nowMin} onNew={onNew} onOpen={onOpen} grupo={grupo} />
          ))}
        </div>
      </div>
    </React.Fragment>
  );
}

function ColumnaDia({ recurso, citas, bloqueos, pxPerMin, slotPx, hourPx, totalPx, slotMin, nowMin, onNew, onOpen, grupo }) {
  const nSlots = (DAY_END - DAY_START) / slotMin;
  const bg = `repeating-linear-gradient(to bottom, var(--border-soft) 0 1px, transparent 1px ${slotPx}px), repeating-linear-gradient(to bottom, var(--border) 0 1px, transparent 1px ${hourPx}px)`;
  // bloqueos que aplican a esta columna
  const colBloqueos = bloqueos.filter((b) =>
    (grupo === "medico" && b.scope === "medico" && b.ref === recurso.id) ||
    (grupo === "sala" && b.scope === "sala" && b.ref === recurso.id));

  return (
    <div className="cal-col" style={{ height: totalPx, background: bg }}>
      {/* slot zones */}
      {Array.from({ length: nSlots }).map((_, i) => {
        const m = DAY_START + i * slotMin;
        return (
          <div key={i} className="slotzone" style={{ top: i * slotPx, height: slotPx }}
            onClick={() => onNew(grupo === "medico" ? { medico: recurso.id, ini: toHHMM(m) } : { sala: recurso.id, ini: toHHMM(m) })}>
            <span className="plus"><i className="mdi mdi-plus"></i></span>
          </div>
        );
      })}
      {/* bloqueos */}
      {colBloqueos.map((b) => {
        const top = (toMin(b.ini) - DAY_START) * pxPerMin;
        const h = (toMin(b.fin) - toMin(b.ini)) * pxPerMin;
        return (
          <div key={b.id} className="appt bloqueo" style={{ top, height: h }}>
            <div className="a-name"><i className="mdi mdi-cancel" style={{ fontSize: 11 }}></i> {b.motivo}</div>
            <div className="a-proc">{b.ini}–{b.fin}</div>
          </div>
        );
      })}
      {/* citas */}
      {citas.map((c) => <ApptBlock key={c.id} c={c} pxPerMin={pxPerMin} onOpen={onOpen} />)}
      {/* now line */}
      {nowMin >= DAY_START && nowMin <= DAY_END && (
        <div className="now-line" style={{ top: (nowMin - DAY_START) * pxPerMin }}></div>
      )}
    </div>
  );
}

function ApptBlock({ c, pxPerMin, onOpen }) {
  const a = area(c.area);
  const top = (toMin(c.ini) - DAY_START) * pxPerMin;
  const h = Math.max(c.dur * pxPerMin, 18);
  const short = h < 42;
  const done = c.estado === "completado";
  const cancel = c.estado === "cancelado" || c.estado === "reagendado";
  const inProg = c.estado === "en_sala" || c.estado === "en_consulta";
  return (
    <div className={`appt${short ? " short" : ""}${done ? " done" : ""}${cancel ? " cancel" : ""}`}
      style={{ top, height: h, background: a.bg, borderLeftColor: a.color, color: a.fg,
               boxShadow: inProg ? "0 0 0 1.5px " + a.color : undefined }}
      onClick={(e) => { e.stopPropagation(); onOpen(c.id); }}>
      {c.sobreturno && <span className="sobre-tag">ST</span>}
      <div className="a-top">
        <span className="a-time" style={{ color: a.fg }}>{c.ini}</span>
        <WaIcon status={c.whatsapp} size={12} />
      </div>
      <div className="a-name">{c.paciente}</div>
      <div className="a-proc" style={{ color: a.fg }}>{tipo(c.tipo)?.label || c.notas || '—'}</div>
      <div className="a-foot">
        <span className="a-room" style={{ color: a.fg }}>{sala(c.sala)?.label || '—'}</span>
      </div>
    </div>
  );
}

/* ---------- VISTA SEMANA: disponibilidad por día + citas reales (jueves) ---------- */
function SemanaView({ state, sedeId, fMed, pxPerMin, onOpen }) {
  const defaultMed = AG.MEDICOS.find((m) => m.sede === sedeId) || AG.MEDICOS[0] || null;
  const medId = fMed || (defaultMed ? defaultMed.id : "");
  const m = medico(medId);
  const totalPx = (DAY_END - DAY_START) * pxPerMin;
  const hourPx = 60 * pxPerMin;
  const dias = [1, 2, 3, 4, 5, 6];
  const fechasSem = { 1: "2 jun", 2: "3 jun", 3: "4 jun", 4: "5 jun", 5: "6 jun", 6: "7 jun" };
  // ojo: HOY (4 jun) es jueves => dia 4
  const citasMed = state.citas.filter((c) => c.medico === medId && c.estado !== "cancelado");

  if (!medId) {
    return (
      <Box title="Semana" icon="mdi-calendar-week">
        <div className="muted" style={{ font: "500 12.5px var(--font-body)" }}>
          No hay médicos sincronizados para mostrar la vista semanal.
        </div>
      </Box>
    );
  }

  return (
    <div>
      <div className="area-legend" style={{ marginTop: -4 }}>
        <span className="lg"><span className="av" style={{ background: m.color, width: 22, height: 22, borderRadius: 6, color: "#fff", display: "grid", placeItems: "center", font: "700 9px var(--font-body)" }}>{m.iniciales}</span>{m.nombre} · {m.esp}</span>
        <span className="div"></span>
        <span className="note"><i className="mdi mdi-information-outline"></i>Bandas = turnos configurados · bloques = citas del día (jue 5 jun)</span>
      </div>
      <div className="cal-grid-wrap">
        <div className="cal-head" style={{ gridTemplateColumns: `56px repeat(6, 1fr)` }}>
          <div className="gutter-h"></div>
          {dias.map((d) => (
            <div key={d} className="cal-res-h" style={{ justifyContent: "center" }}>
              <div style={{ textAlign: "center" }}>
                <div className="nm">{DIAS_SEM[d]}</div>
                <div className="sub">{fechasSem[d]}</div>
              </div>
            </div>
          ))}
        </div>
        <div className="cal-body" style={{ gridTemplateColumns: `56px repeat(6, 1fr)` }}>
          <div className="cal-gutter" style={{ height: totalPx }}>
            {Array.from({ length: (DAY_END - DAY_START) / 60 + 1 }).map((_, i) => (
              <div key={i} className="cal-hour" style={{ top: i * hourPx, transform: i === 0 ? "none" : "translateY(-7px)" }}>{toHHMM(DAY_START + i * 60)}</div>
            ))}
          </div>
          {dias.map((d) => {
            const turnos = AG.HORARIOS.filter((h) => h.medico === medId && h.dia === d);
            const esJueves = d === 4;
            return (
              <div key={d} className="cal-col" style={{ height: totalPx, background: `repeating-linear-gradient(to bottom, var(--border) 0 1px, transparent 1px ${hourPx}px)` }}>
                {turnos.map((t, i) => (
                  <div key={i} style={{ position: "absolute", left: 2, right: 2, top: (toMin(t.ini) - DAY_START) * pxPerMin, height: (toMin(t.fin) - toMin(t.ini)) * pxPerMin, background: m.color + "1f", border: `1px dashed ${m.color}66`, borderRadius: 6 }}></div>
                ))}
                {esJueves && citasMed.map((c) => <ApptBlock key={c.id} c={c} pxPerMin={pxPerMin} onOpen={onOpen} />)}
                {turnos.length === 0 && (
                  <div style={{ position: "absolute", inset: 0, display: "grid", placeItems: "center", color: "var(--fg-fade)", font: "600 11px var(--font-body)" }}>Libre</div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Calendario });
