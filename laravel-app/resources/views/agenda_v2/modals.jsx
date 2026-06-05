/* MedForge Agendamiento — modal crear/editar + detalle de cita */

function ApptForm({ prefill, state, sedeId, onSave, onClose }) {
  const editing = !!prefill.id;
  const [f, setF] = React.useState(() => ({
    id: prefill.id || null,
    paciente: prefill.paciente || "",
    hc: prefill.hc || "",
    tel: prefill.tel || "",
    edad: prefill.edad || "",
    afil: prefill.afil || "Particular",
    tipo: prefill.tipo || "t_cons",
    medico: prefill.medico || "",
    sala: prefill.sala || "",
    fecha: prefill.fecha || AG.HOY,
    ini: prefill.ini || "08:00",
    sobreturno: prefill.sobreturno || false,
    justifST: prefill.justifST || "",
    notas: prefill.notas || "",
    sede: sedeId,
    estado: prefill.estado || "agendado",
    whatsapp: prefill.whatsapp || "na",
  }));
  const set = (k, v) => setF((p) => ({ ...p, [k]: v }));

  const t = tipo(f.tipo);
  // al cambiar tipo, sugiere sala compatible
  React.useEffect(() => {
    const sug = sugerirSala(f, state.citas, state.bloqueos);
    if (sug && (!f.sala || (sala(f.sala) && !t.requiereTipoSala.includes(sala(f.sala).tipo)))) set("sala", sug);
  }, [f.tipo, f.ini, f.medico]); // eslint-disable-line

  const val = validarCita(f, state.citas, state.bloqueos, { horarios: AG.HORARIOS });
  const salaSugerida = sugerirSala(f, state.citas, state.bloqueos);
  const medicosSede = AG.MEDICOS.filter((m) => m.sede === sedeId && m.areas.includes(t.area));
  const salasOk = AG.SALAS.filter((s) => s.sede === sedeId && t.requiereTipoSala.includes(s.tipo));
  const puedeGuardar = f.paciente.trim() && f.medico && f.sala && (val.ok || f.sobreturno) && (!f.sobreturno || f.justifST.trim());

  return (
    <div className="scrim" onClick={onClose}>
      <div className="modal wide" onClick={(e) => e.stopPropagation()}>
        <div className="modal-h">
          <div>
            <h3>{editing ? "Editar cita" : "Nueva cita"}</h3>
            <div className="sub">{sede(sedeId).label} · {t.dur} min · <AreaChip id={t.area} /></div>
          </div>
          <button className="x" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>

        <div className="modal-b">
          {/* paciente */}
          <div className="form-grid">
            <div className="field full">
              <label>Paciente</label>
              <input value={f.paciente} onChange={(e) => set("paciente", e.target.value)} placeholder="Nombres y apellidos" />
            </div>
            <div className="field"><label>N.° historia clínica</label><input value={f.hc} onChange={(e) => set("hc", e.target.value)} placeholder="HC-00000" /></div>
            <div className="field"><label>Teléfono</label><input value={f.tel} onChange={(e) => set("tel", e.target.value)} placeholder="09x xxx xxxx" /></div>
            <div className="field"><label>Edad</label><input value={f.edad} onChange={(e) => set("edad", e.target.value)} placeholder="años" /></div>
            <div className="field">
              <label>Afiliación</label>
              <select value={f.afil} onChange={(e) => set("afil", e.target.value)}>
                {AG.AFILIACIONES.map((a) => <option key={a}>{a}</option>)}
              </select>
            </div>
          </div>

          <hr style={{ border: 0, borderTop: "1px solid var(--border-soft)", margin: "2px 0" }} />

          {/* cita */}
          <div className="form-grid">
            <div className="field">
              <label>Tipo de cita</label>
              <select value={f.tipo} onChange={(e) => set("tipo", e.target.value)}>
                {AG.AREAS.map((ar) => (
                  <optgroup key={ar.id} label={ar.label}>
                    {AG.TIPOS.filter((tp) => tp.area === ar.id).map((tp) => (
                      <option key={tp.id} value={tp.id}>{tp.label} ({tp.dur}′)</option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </div>
            <div className={`field${!f.medico ? " error" : ""}`}>
              <label>Médico / profesional</label>
              <select value={f.medico} onChange={(e) => set("medico", e.target.value)}>
                <option value="">Seleccionar…</option>
                {medicosSede.map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
              </select>
            </div>
            <div className="field"><label>Fecha</label><input type="date" value={f.fecha} onChange={(e) => set("fecha", e.target.value)} /></div>
            <div className="field"><label>Hora de inicio</label><input type="time" step="300" value={f.ini} onChange={(e) => set("ini", e.target.value)} /></div>
            <div className="field full">
              <label>Sala / consultorio</label>
              <select value={f.sala} onChange={(e) => set("sala", e.target.value)}>
                <option value="">Seleccionar…</option>
                {salasOk.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
              </select>
            </div>
          </div>

          {/* sugerencia de sala */}
          {salaSugerida && (
            <div className="room-sugg">
              <i className="mdi mdi-auto-fix"></i>
              <span>Sala libre sugerida: <b>{sala(salaSugerida).label}</b> ({f.ini}–{toHHMM(toMin(f.ini) + t.dur)})</span>
              {f.sala !== salaSugerida
                ? <button className="btn sm btn-outline-primary" onClick={() => set("sala", salaSugerida)}>Asignar</button>
                : <span className="auto">Asignada</span>}
            </div>
          )}

          {/* validación */}
          {val.errores.map((e, i) => (
            <div key={i} className="validate bad"><i className="mdi mdi-alert-circle-outline"></i><span>{e}</span></div>
          ))}
          {val.avisos.map((a, i) => (
            <div key={i} className="validate warn"><i className="mdi mdi-alert-outline"></i><span>{a}</span></div>
          ))}
          {val.ok && !val.avisos.length && f.medico && f.sala && (
            <div className="validate good"><i className="mdi mdi-check-circle-outline"></i><span>Sin conflictos: médico y sala disponibles en este horario.</span></div>
          )}

          {/* sobreturno */}
          <div className="form-grid" style={{ alignItems: "start" }}>
            <label style={{ display: "flex", gap: 9, alignItems: "center", font: "600 12.5px var(--font-body)", color: "var(--fg-1)", cursor: "pointer" }}>
              <input type="checkbox" checked={f.sobreturno} onChange={(e) => set("sobreturno", e.target.checked)} style={{ width: 16, height: 16 }} />
              <span><i className="mdi mdi-flash" style={{ color: "var(--warning)" }}></i> Sobreturno (excepción)</span>
            </label>
            {f.sobreturno && (
              <div className={`field${!f.justifST.trim() ? " error" : ""}`}>
                <label>Justificación del sobreturno *</label>
                <input value={f.justifST} onChange={(e) => set("justifST", e.target.value)} placeholder="Motivo / autoriza…" />
              </div>
            )}
          </div>

          <div className="field full">
            <label>Notas (opcional)</label>
            <textarea rows="2" value={f.notas} onChange={(e) => set("notas", e.target.value)} placeholder="Indicaciones, preparación, observaciones…"></textarea>
          </div>
        </div>

        <div className="modal-f">
          <span className="muted" style={{ font: "500 11.5px var(--font-body)", marginRight: "auto" }}>
            <i className="mdi mdi-whatsapp" style={{ color: "#0b8043" }}></i> Se enviará recordatorio por WhatsApp al confirmar
          </span>
          <button className="btn btn-light" onClick={onClose}>Cancelar</button>
          <button className="btn btn-primary" disabled={!puedeGuardar}
            onClick={() => onSave({ ...f, fin: toHHMM(toMin(f.ini) + t.dur), area: t.area, dur: t.dur })}>
            <i className="mdi mdi-content-save-outline"></i>{editing ? "Guardar cambios" : "Crear cita"}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- DETALLE / VISTA de una cita ---------- */
function ApptDetail({ cita: c, onClose, onEdit, onAdvance, onCancel, onResend, onConsulta }) {
  const m = medico(c.medico), s = sala(c.sala), t = tipo(c.tipo);
  const pasos = [
    { id: "agendado",   label: "Agendado",   tm: null },
    { id: "confirmado", label: "Confirmado", tm: c.whatsapp === "confirmado" ? "WhatsApp" : null },
    { id: "en_sala",    label: "En sala",    tm: c.horaSala },
    { id: "en_consulta",label: "En consulta",tm: c.horaConsulta },
    { id: "completado", label: "Completado", tm: c.horaFin },
  ];
  const order = ["agendado", "confirmado", "en_sala", "en_consulta", "completado"];
  const idx = order.indexOf(c.estado);
  const finalState = c.estado === "completado" || c.estado === "ausente" || c.estado === "cancelado";

  const waThread = {
    confirmado: [
      { d: "out", x: `Hola ${c.paciente.split(" ")[0]}, te recordamos tu cita en MedForge ${sede(c.sede).label} el 5 jun a las ${c.ini}. Responde *SÍ* para confirmar o *NO* para cancelar.`, t: "Ayer 18:02" },
      { d: "in",  x: "Sí, confirmo. Gracias.", t: "Ayer 18:40" },
      { d: "out", x: "¡Listo! Tu cita quedó confirmada. Llega 15 min antes con tu cédula.", t: "Ayer 18:40" },
    ],
    enviado: [
      { d: "out", x: `Hola ${c.paciente.split(" ")[0]}, te recordamos tu cita el 5 jun a las ${c.ini}. Responde *SÍ* para confirmar.`, t: "Hoy 07:30" },
    ],
    sin_respuesta: [
      { d: "out", x: `Hola ${c.paciente.split(" ")[0]}, te recordamos tu cita el 5 jun a las ${c.ini}. Responde *SÍ* para confirmar.`, t: "Ayer 17:00" },
      { d: "out", x: "Segundo recordatorio: por favor confirma tu asistencia.", t: "Hoy 08:15" },
    ],
    na: [],
    cancelado_wa: [
      { d: "out", x: `Recordatorio de cita 5 jun ${c.ini}.`, t: "Ayer 17:00" },
      { d: "in",  x: "NO", t: "Ayer 19:22" },
      { d: "out", x: "Entendido, cancelamos tu cita. Escríbenos para reagendar.", t: "Ayer 19:22" },
    ],
  }[c.whatsapp] || [];

  const waLabel = { confirmado: "Confirmado", enviado: "Enviado", sin_respuesta: "Sin respuesta", na: "Sin envío", cancelado_wa: "Canceló" }[c.whatsapp];

  return (
    <div className="scrim" onClick={onClose}>
      <div className="modal wide" onClick={(e) => e.stopPropagation()}>
        <div className="modal-h">
          <div>
            <h3>{c.paciente}</h3>
            <div className="sub">{c.hc} · {c.edad} años · {c.afil}{c.sobreturno && <span> · <Badge tone="warning" icon="mdi-flash">Sobreturno</Badge></span>}</div>
          </div>
          <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
            <EstadoBadge id={c.estado} />
            <button className="x" onClick={onClose}><i className="mdi mdi-close"></i></button>
          </div>
        </div>

        <div className="modal-b">
          <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr", gap: 18 }}>
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div className="det-grid">
                <div className="det"><div className="l">Tipo de cita</div><div className="v">{t.label}</div></div>
                <div className="det"><div className="l">Área</div><div className="v"><AreaChip id={c.area} /></div></div>
                <div className="det"><div className="l">Médico</div><div className="v">{m.nombre}</div></div>
                <div className="det"><div className="l">Sala</div><div className="v">{s.label}</div></div>
                <div className="det"><div className="l">Fecha</div><div className="v">5 jun 2026</div></div>
                <div className="det"><div className="l">Horario</div><div className="v">{c.ini} – {c.fin} <span className="muted" style={{ fontWeight: 400 }}>({c.dur}′)</span></div></div>
              </div>
              {c.notas && <div className="det"><div className="l">Notas</div><div className="v" style={{ fontWeight: 400, fontSize: 12.5 }}>{c.notas}</div></div>}
              {c._readonly && (
                <div className="validate warn">
                  <i className="mdi mdi-sync"></i>
                  <div><b>SigCenter</b>: esta cita es de solo lectura en Agenda V3.</div>
                </div>
              )}

              <div>
                <div className="l" style={{ font: "700 9.5px var(--font-body)", textTransform: "uppercase", letterSpacing: ".04em", color: "var(--fg-mute)", marginBottom: 9 }}>Línea de tiempo del flujo</div>
                <div className="timeline">
                  {pasos.map((p, i) => {
                    const pIdx = order.indexOf(p.id);
                    const reached = pIdx <= idx && idx >= 0;
                    const e = estado(p.id);
                    return (
                      <div key={p.id} className={`tl-step${reached ? "" : " pending"}`}>
                        <div className="dot" style={reached ? { background: `var(--${e.tone === "light" ? "fg-mute" : e.tone})` } : {}}>
                          <i className={`mdi ${reached ? "mdi-check" : p.id === "agendado" ? "mdi-circle-small" : "mdi-circle-small"}`}></i>
                        </div>
                        <div className="tl-b">
                          <div className="tt">{p.label}</div>
                          {p.tm && <div className="tm">{p.tm}</div>}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>

            {/* whatsapp panel */}
            <div className="wa-panel" style={{ alignSelf: "start" }}>
              <div className="wa-h"><i className="mdi mdi-whatsapp"></i>Bot de confirmación<span className="st">{waLabel}</span></div>
              <div className="wa-thread">
                {waThread.length === 0
                  ? <div style={{ textAlign: "center", color: "var(--fg-mute)", font: "500 11.5px var(--font-body)", padding: 14 }}>Aún no se ha enviado recordatorio.</div>
                  : waThread.map((msg, i) => (
                    <div key={i} className={`wa-msg ${msg.d}`}>{msg.x}<span className="t">{msg.t}</span></div>
                  ))}
              </div>
              {!c._readonly && (
                <div style={{ padding: 10, borderTop: "1px solid var(--border-soft)", display: "flex", gap: 8 }}>
                  <button className="btn sm btn-outline-success block" onClick={onResend}>
                    <i className="mdi mdi-send-outline"></i>{c.whatsapp === "na" ? "Enviar confirmación" : "Reenviar confirmación"}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="modal-f">
          {!c._readonly && (
            <React.Fragment>
              <button className="btn btn-outline-danger" onClick={onCancel} disabled={finalState}><i className="mdi mdi-close-circle-outline"></i>Cancelar cita</button>
              <button className="btn btn-outline-secondary" onClick={() => onEdit(c)}><i className="mdi mdi-pencil-outline"></i>Editar / reagendar</button>
              {onConsulta && c.estado !== "cancelado" && c.estado !== "ausente" && (
                <button className="btn btn-outline-success" onClick={() => onConsulta(c.id)}><i className="mdi mdi-file-document-edit-outline"></i>{c.estado === "completado" || c.hcLlena ? "Ver historia clínica" : "Abrir consulta"}</button>
              )}
              <div className="spacer"></div>
              {!finalState && idx < 4 && (
                <button className="btn btn-primary" onClick={() => onAdvance(c.id)}>
                  <i className="mdi mdi-arrow-right-circle-outline"></i>Avanzar a «{estado(order[idx + 1]).label}»
                </button>
              )}
            </React.Fragment>
          )}
          {c._readonly && (
            <React.Fragment>
              <span className="muted" style={{ font: "600 12px var(--font-body)", marginRight: "auto" }}>
                <i className="mdi mdi-sync"></i> Registro SigCenter de solo lectura
              </span>
              <button className="btn btn-light" onClick={onClose}>Cerrar</button>
            </React.Fragment>
          )}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { ApptForm, ApptDetail });
