/* MedForge Agendamiento — primitives + lookups + reglas de negocio
   Expuesto a window para los demás scripts Babel. */

const AG = window.AG;
const { toMin, toHHMM } = AG.util;

/* ---------- lookups ---------- */
const byId = (arr, id) => (arr || []).find((x) => x && x.id === id) || null;
const fallbackArea = { id: "consulta", label: "Consulta", icon: "mdi-stethoscope", color: "#1f9d7a", bg: "#dff5ee", fg: "#17654f" };
const fallbackMedico = { id: "", nombre: "Sin médico asignado", esp: "SigCenter", areas: ["consulta"], sede: "", color: "#7e8299", iniciales: "SC" };
const fallbackSala = { id: "", sede: "", label: "Sin sala asignada", tipo: "consultorio", area: "consulta", cap: 1 };
const fallbackTipo = { id: "", label: "Atención SigCenter", area: "consulta", dur: 20, requiereTipoSala: ["consultorio"] };
const fallbackSede = { id: "", label: "Sede", abrev: "", apertura: "08:00", cierre: "18:00" };
const fallbackEstado = { id: "agendado", label: "Agendado", icon: "mdi-calendar-blank-outline", tone: "info", desc: "" };

const medico = (id) => byId(AG.MEDICOS, id) || { ...fallbackMedico, id: id || "" };
const sala   = (id) => byId(AG.SALAS, id) || { ...fallbackSala, id: id || "" };
const tipo   = (id) => byId(AG.TIPOS, id) || { ...fallbackTipo, id: id || "" };
const area   = (id) => byId(AG.AREAS, id) || { ...fallbackArea, id: id || "consulta" };
const sede   = (id) => byId(AG.SEDES, id) || { ...fallbackSede, id: id || "" };
const estado = (id) => byId(AG.ESTADOS, id) || { ...fallbackEstado, id: id || "agendado" };

/* ---------- reglas de negocio: detección de conflictos ---------- */
// Devuelve overlap (bool) entre [aIni,aFin) y [bIni,bFin) en minutos.
function overlaps(aIni, aFin, bIni, bFin) {
  return aIni < bFin && bIni < aFin;
}

// Valida una cita (nueva o editada) contra el resto de citas + bloqueos.
// Devuelve { ok, errores:[], avisos:[] }.
function validarCita(borrador, citas, bloqueos, opts = {}) {
  const errores = [];
  const avisos = [];
  const ini = toMin(borrador.ini);
  const t = tipo(borrador.tipo);
  const fin = ini + (t ? t.dur : 20);
  const sameDay = (c) => c.fecha === borrador.fecha && c.id !== borrador.id &&
                         c.estado !== "cancelado" && c.estado !== "reagendado";

  // 1. Médico no puede estar en dos citas a la vez
  if (borrador.medico) {
    const choque = citas.find((c) => sameDay(c) && c.medico === borrador.medico &&
                                     overlaps(ini, fin, toMin(c.ini), toMin(c.fin)));
    if (choque && !borrador.sobreturno) {
      errores.push(`El médico ya tiene una cita ${choque.ini}–${choque.fin} (${choque.paciente}).`);
    } else if (choque && borrador.sobreturno) {
      avisos.push(`Sobreturno: el médico se solapa con ${choque.paciente} (${choque.ini}).`);
    }
  }

  // 2. Sala no puede tener dos citas a la vez
  if (borrador.sala) {
    const choque = citas.find((c) => sameDay(c) && c.sala === borrador.sala &&
                                     overlaps(ini, fin, toMin(c.ini), toMin(c.fin)));
    if (choque) errores.push(`${sala(borrador.sala).label} está ocupada ${choque.ini}–${choque.fin}.`);
  }

  // 3. Tipo de cita exige tipo de sala compatible
  if (borrador.sala && t && t.requiereTipoSala && !t.requiereTipoSala.includes(sala(borrador.sala).tipo)) {
    errores.push(`«${t.label}» requiere ${t.requiereTipoSala.join(" o ")}, no ${sala(borrador.sala).label}.`);
  }

  // 4. Bloqueos manuales (médico o sala)
  (bloqueos || []).forEach((b) => {
    if (b.fecha !== borrador.fecha) return;
    if (!overlaps(ini, fin, toMin(b.ini), toMin(b.fin))) return;
    if (b.scope === "medico" && b.ref === borrador.medico)
      errores.push(`Médico bloqueado ${b.ini}–${b.fin}: ${b.motivo}.`);
    if (b.scope === "sala" && b.ref === borrador.sala)
      errores.push(`Sala bloqueada ${b.ini}–${b.fin}: ${b.motivo}.`);
  });

  // 5. Horario del médico (aviso, no error)
  if (borrador.medico && opts.horarios) {
    const dow = new Date(borrador.fecha + "T00:00:00").getDay() || 7; // 1..7
    const turnos = opts.horarios.filter((h) => h.medico === borrador.medico && h.dia === dow);
    if (turnos.length) {
      const dentro = turnos.some((h) => ini >= toMin(h.ini) && fin <= toMin(h.fin));
      if (!dentro) avisos.push("La hora está fuera del horario configurado del médico.");
    }
  }

  return { ok: errores.length === 0, errores, avisos };
}

// Sugiere automáticamente una sala libre del tipo correcto.
function sugerirSala(borrador, citas, bloqueos) {
  const t = tipo(borrador.tipo);
  if (!t) return null;
  const ini = toMin(borrador.ini);
  const fin = ini + t.dur;
  const candidatas = AG.SALAS.filter((s) =>
    s.sede === borrador.sede && t.requiereTipoSala.includes(s.tipo));
  for (const s of candidatas) {
    const ocupada = citas.some((c) => c.fecha === borrador.fecha && c.sala === s.id &&
      c.estado !== "cancelado" && c.estado !== "reagendado" &&
      overlaps(ini, fin, toMin(c.ini), toMin(c.fin)));
    const bloqueada = (bloqueos || []).some((b) => b.fecha === borrador.fecha &&
      b.scope === "sala" && b.ref === s.id && overlaps(ini, fin, toMin(b.ini), toMin(b.fin)));
    if (!ocupada && !bloqueada) return s.id;
  }
  return null;
}

/* ---------- primitives ---------- */
function Box({ title, icon, action, children, noPad }) {
  return (
    <div className="box">
      {title && (
        <div className="box-header">
          <h4 className="box-title">{icon && <i className={`mdi ${icon}`}></i>}{title}</h4>
          {action}
        </div>
      )}
      <div className={`box-body${noPad ? " no-pad" : ""}`}>{children}</div>
    </div>
  );
}

function Badge({ tone = "primary", icon, children }) {
  return (
    <span className={`badge badge--${tone}`}>
      {icon && <i className={`mdi ${icon}`}></i>}{children}
    </span>
  );
}

function EstadoBadge({ id }) {
  const e = estado(id);
  if (!e) return null;
  return <Badge tone={e.tone} icon={e.icon}>{e.label}</Badge>;
}

// WhatsApp confirmation icon
function WaIcon({ status, size = 14 }) {
  const map = {
    confirmado:    { i: "mdi-check-all",          c: "#0b8043", t: "Confirmado por WhatsApp" },
    enviado:       { i: "mdi-check",              c: "#7e8299", t: "Recordatorio enviado, sin respuesta" },
    sin_respuesta: { i: "mdi-clock-alert-outline",c: "#ffa800", t: "Sin respuesta del paciente" },
    cancelado_wa:  { i: "mdi-close-circle",       c: "#ee3158", t: "Canceló por WhatsApp" },
    na:            { i: "mdi-whatsapp",           c: "#c5cbd6", t: "Sin recordatorio" },
  };
  const m = map[status] || map.na;
  return <i className={`mdi ${m.i}`} style={{ color: m.c, fontSize: size }} title={m.t}></i>;
}

function KpiChip({ icon, tone, label, value, hint }) {
  const tones = {
    primary: { bg: "var(--primary-fade)", fg: "var(--primary)" },
    success: { bg: "#dff5ee", fg: "var(--success)" },
    warning: { bg: "#fff0d1", fg: "#8a5d0a" },
    danger:  { bg: "#fde2e7", fg: "var(--danger)" },
    info:    { bg: "#cfe5fd", fg: "#0863be" },
  };
  const c = tones[tone] || tones.primary;
  return (
    <div className="kpi">
      <div className="tile" style={{ background: c.bg, color: c.fg }}><i className={`mdi ${icon}`}></i></div>
      <div style={{ minWidth: 0 }}>
        <div className="label">{label}</div>
        <div className="value">{value}</div>
        {hint && <div className="hint">{hint}</div>}
      </div>
    </div>
  );
}

function AreaChip({ id, withDot = true }) {
  const a = area(id);
  if (!a) return null;
  return (
    <span className="chip-tag" style={{ background: a.bg, color: a.fg }}>
      {withDot && <span className="sw" style={{ background: a.color }}></span>}{a.label}
    </span>
  );
}

// "hace 12 min" relativo a 'now' (minutos del día)
function minutosDesde(hhmm, nowMin) {
  if (!hhmm) return null;
  return nowMin - toMin(hhmm);
}

Object.assign(window, {
  byId, medico, sala, tipo, area, sede, estado,
  overlaps, validarCita, sugerirSala, minutosDesde,
  Box, Badge, EstadoBadge, WaIcon, KpiChip, AreaChip,
});
