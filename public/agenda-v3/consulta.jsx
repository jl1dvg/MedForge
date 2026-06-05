/* MedForge Agendamiento — Mi agenda (médico) + Historia clínica */

/* ============ MI AGENDA (worklist del médico) ============ */
function MiAgenda({ state, docId, setDocId, nowMin, onConsulta, onOpen }) {
  const m = medico(docId) ?? { nombre: '—', esp: '', sede: null, color: '#999', iniciales: '?' };
  const mis = state.citas
    .filter((c) => c.medico === docId && c.estado !== "cancelado" && c.estado !== "reagendado")
    .sort((a, b) => toMin(a.ini) - toMin(b.ini));

  const atendidos = mis.filter((c) => c.estado === "completado").length;
  const enEspera = mis.filter((c) => c.estado === "confirmado" || c.estado === "agendado" || c.estado === "en_sala").length;
  // paciente actual o siguiente
  const actual = mis.find((c) => c.estado === "en_consulta" || c.estado === "en_sala")
    || mis.find((c) => c.estado !== "completado" && c.estado !== "ausente");

  const accion = (c) => {
    const done = c.estado === "completado";
    return done
      ? <button className="btn sm btn-outline-secondary" onClick={() => onConsulta(c.id)}><i className="mdi mdi-file-document-outline"></i>Ver HC</button>
      : <button className="btn sm btn-primary" onClick={() => onConsulta(c.id)}><i className="mdi mdi-file-document-edit-outline"></i>Abrir consulta</button>;
  };

  return (
    <React.Fragment>
      <div className="doc-bar">
        <div className="who">
          <div className="av" style={{ background: m.color }}>{m.iniciales}</div>
          <div><div className="nm">{m.nombre}</div><div className="es">{m.esp} · {sede(m.sede)?.label || '—'}</div></div>
        </div>
        <select value={docId} onChange={(e) => setDocId(e.target.value)}>
          {AG.MEDICOS.map((d) => <option key={d.id} value={d.id}>Ver como: {d.nombre}</option>)}
        </select>
        <div className="mini">
          <div className="m"><div className="n">{mis.length}</div><div className="l">Agenda hoy</div></div>
          <div className="m"><div className="n" style={{ color: "var(--success)" }}>{atendidos}</div><div className="l">Atendidos</div></div>
          <div className="m"><div className="n" style={{ color: "var(--primary)" }}>{enEspera}</div><div className="l">Por atender</div></div>
        </div>
      </div>

      {actual && (
        <div className="next-card">
          <div style={{ flex: 1 }}>
            <div className="lab">{actual.estado === "en_consulta" ? "En consulta ahora" : actual.estado === "en_sala" ? "En sala — listo para atender" : "Siguiente paciente"}</div>
            <div className="nm">{actual.paciente}</div>
            <div className="meta">{actual.hc} · {actual.edad} años · {tipo(actual.tipo)?.label || actual.notas || '—'} · {sala(actual.sala)?.label || '—'}</div>
          </div>
          <div style={{ textAlign: "right" }}>
            <div className="tm">{actual.ini}</div>
          </div>
          <button className="btn lg" style={{ background: "#fff", color: "var(--primary)" }} onClick={() => onConsulta(actual.id)}>
            <i className="mdi mdi-file-document-edit-outline"></i>Abrir consulta
          </button>
        </div>
      )}

      <Box title="Pacientes del día" icon="mdi-clipboard-list-outline" noPad
        action={<span className="muted" style={{ font: "500 12px var(--font-body)" }}>jueves 5 jun 2026</span>}>
        <table className="wl">
          <thead><tr><th>Hora</th><th>Paciente</th><th>Tipo de cita</th><th>Sala</th><th>Estado</th><th>WhatsApp</th><th style={{ textAlign: "right" }}>Consulta</th></tr></thead>
          <tbody>
            {mis.map((c) => {
              const esActual = actual && c.id === actual.id;
              return (
                <tr key={c.id} className={c.estado === "completado" ? "is-done" : esActual ? "is-now" : ""}>
                  <td className="tm">{c.ini}</td>
                  <td onClick={() => onOpen(c.id)} style={{ cursor: "pointer" }}>
                    <div className="pname">{c.paciente}</div>
                    <div className="psub">{c.hc} · {c.edad} años · {c.afil}</div>
                  </td>
                  <td><AreaChip id={c.area} /> <span style={{ marginLeft: 4 }}>{tipo(c.tipo)?.label || c.notas || '—'}</span></td>
                  <td>{sala(c.sala)?.label || '—'}</td>
                  <td><EstadoBadge id={c.estado} /></td>
                  <td><WaIcon status={c.whatsapp} size={16} /></td>
                  <td style={{ textAlign: "right" }}>{accion(c)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Box>
    </React.Fragment>
  );
}

/* ============ EyePair helper ============ */
function EyePair({ label, sub, ph, valOD, valOS, onOD, onOS }) {
  return (
    <React.Fragment>
      <div className="rl">{label}{sub && <small> · {sub}</small>}</div>
      <input placeholder={ph || ""} value={valOD} onChange={(e) => onOD(e.target.value)} />
      <input placeholder={ph || ""} value={valOS} onChange={(e) => onOS(e.target.value)} />
    </React.Fragment>
  );
}

const CIE10 = [
  { code: "H25.9", nm: "Catarata senil" },
  { code: "H40.9", nm: "Glaucoma, no especificado" },
  { code: "H35.3", nm: "Degeneración macular (DMAE)" },
  { code: "E11.3", nm: "Retinopatía diabética" },
  { code: "H52.4", nm: "Presbicia" },
  { code: "H11.0", nm: "Pterigión" },
  { code: "H10.3", nm: "Conjuntivitis aguda" },
  { code: "H33.0", nm: "Desprendimiento de retina" },
];

/* ============ PANTALLA DE CONSULTA / HISTORIA CLÍNICA ============ */
function ConsultaScreen({ cita: c, onClose, onFinish, notify }) {
  const m = medico(c.medico) ?? { nombre: '—', sede: null };
  const s = sala(c.sala)   ?? { label: '—' };
  const t = tipo(c.tipo)   ?? { label: c.notas || '—' };
  const ya = c.estado === "completado" || c.hcLlena;
  const pre = c.hcData || {};
  const [f, setF] = React.useState(() => ({
    motivo: pre.motivo || "", antecedentes: pre.antecedentes || "",
    avscOD: pre.avscOD || "", avscOS: pre.avscOS || "",
    avccOD: pre.avccOD || "", avccOS: pre.avccOS || "",
    pioOD: pre.pioOD || "", pioOS: pre.pioOS || "",
    refOD: pre.refOD || "", refOS: pre.refOS || "",
    bmcOD: pre.bmcOD || "", bmcOS: pre.bmcOS || "",
    fondoOD: pre.fondoOD || "", fondoOS: pre.fondoOS || "",
    plan: pre.plan || "", receta: pre.receta || "",
  }));
  const [dx, setDx] = React.useState(pre.dx || []);
  const [dxText, setDxText] = React.useState("");
  const [recetas, setRecetas] = React.useState(pre.recetas || []);
  const [examenes, setExamenes] = React.useState(pre.examenes || []);
  const [solicitudes, setSolicitudes] = React.useState(pre.solicitudes || []);
  const [antec, setAntec] = React.useState(() => CLIN.getAntec(c.hc));
  const set = (k, v) => setF((p) => ({ ...p, [k]: v }));
  const addDx = (d) => { if (d && !dx.some((x) => x.code === d.code)) setDx([...dx, d]); setDxText(""); };

  // acciones que dispara el auxiliar virtual
  const acts = {
    addExamen: (id) => setExamenes((prev) => prev.some((e) => e.id === id) ? prev : (() => { const ex = CLIN.EXAMENES.find((x) => x.id === id); notify(`Examen añadido: ${ex.nm}`, "success"); return [...prev, { id: ex.id, nm: ex.nm, ojo: CLIN.OJOS[2], obs: "" }]; })()),
    addSolicitud: (procId) => setSolicitudes((prev) => { const p = CLIN.PROCEDIMIENTOS.find((x) => x.id === procId); notify(`Solicitud de ${p.clase.toLowerCase()} generada`, "success"); return [...prev, { proc: procId, nm: p.nm, clase: p.clase, ojo: CLIN.OJOS[0], prioridad: "Normal", dx: dx[0] ? `${dx[0].code} · ${dx[0].nm}` : "", obs: "" }]; }),
  };

  const finalizar = () => {
    if (!f.motivo.trim()) { notify("Indica el motivo de consulta para finalizar", "danger"); return; }
    if (dx.length === 0) { notify("Registra al menos un diagnóstico", "danger"); return; }
    onFinish(c.id, { ...f, dx, recetas, examenes, solicitudes });
  };

  const histPrev = [
    { d: "12 mar 2026", dx: "Catarata senil OD (H25.9)", dr: m.nombre },
    { d: "04 dic 2025", dx: "Control post-operatorio", dr: "Dra. Veintimilla" },
  ];

  return (
    <div className="hc">
      <div className="hc-head">
        <button className="back" onClick={onClose}><i className="mdi mdi-arrow-left"></i></button>
        <div>
          <div className="pt">{c.paciente} <small>{c.hc} · {c.edad} años</small></div>
          <div className="meta">
            <span><i className="mdi mdi-stethoscope"></i> {t.label}</span>
            <span><i className="mdi mdi-doctor"></i> {m.nombre}</span>
            <span><i className="mdi mdi-door"></i> {s.label}</span>
            <span><i className="mdi mdi-clock-outline"></i> {c.ini}</span>
            <EstadoBadge id={c.estado} />
          </div>
        </div>
        <div className="acts">
          <button className="btn btn-light" onClick={() => notify("Borrador guardado")}><i className="mdi mdi-content-save-outline"></i>Guardar borrador</button>
          {!ya
            ? <button className="btn btn-success" onClick={finalizar}><i className="mdi mdi-check-circle-outline"></i>Finalizar consulta</button>
            : <span className="badge badge--success" style={{ padding: "8px 12px" }}><i className="mdi mdi-check-all"></i>Consulta finalizada</span>}
        </div>
      </div>

      <div className="hc-body">
        <div className="hc-main">
          {/* Antecedentes persistentes */}
          <Antecedentes hc={c.hc} antec={antec} onChange={setAntec} />

          {/* Anamnesis */}
          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-comment-text-outline"></i><span className="t">Motivo de consulta y anamnesis</span></div>
            <div className="sb" style={{ display: "grid", gap: 12 }}>
              <div className="field"><label>Motivo de consulta</label>
                <textarea rows="2" value={f.motivo} onChange={(e) => set("motivo", e.target.value)} placeholder="Ej. Disminución progresiva de visión en ojo derecho, 6 meses de evolución…"></textarea>
              </div>
              <div className="field"><label>Enfermedad actual</label>
                <textarea rows="2" value={f.antecedentes} onChange={(e) => set("antecedentes", e.target.value)} placeholder="Evolución del cuadro, síntomas asociados, tratamientos previos…"></textarea>
              </div>
            </div>
          </div>

          {/* Examen */}
          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-eye-outline"></i><span className="t">Examen oftalmológico</span></div>
            <div className="sb">
              <div className="eyes">
                <div className="hdr lbl"></div>
                <div className="hdr"><span className="eye-tag" style={{ color: "#2e5e99" }}>OD</span> (derecho)</div>
                <div className="hdr"><span className="eye-tag" style={{ color: "#9f2d3e" }}>OS</span> (izquierdo)</div>
                <EyePair label="Agudeza visual" sub="sin corrección" ph="20/40" valOD={f.avscOD} valOS={f.avscOS} onOD={(v) => set("avscOD", v)} onOS={(v) => set("avscOS", v)} />
                <EyePair label="Agudeza visual" sub="con corrección" ph="20/20" valOD={f.avccOD} valOS={f.avccOS} onOD={(v) => set("avccOD", v)} onOS={(v) => set("avccOS", v)} />
                <EyePair label="Refracción" sub="esf / cil × eje" ph="-1.50 -0.75 × 90" valOD={f.refOD} valOS={f.refOS} onOD={(v) => set("refOD", v)} onOS={(v) => set("refOS", v)} />
                <EyePair label="PIO" sub="mmHg" ph="14" valOD={f.pioOD} valOS={f.pioOS} onOD={(v) => set("pioOD", v)} onOS={(v) => set("pioOS", v)} />
              </div>
            </div>
          </div>

          {/* Biomicroscopía / fondo */}
          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-microscope"></i><span className="t">Biomicroscopía y fondo de ojo</span></div>
            <div className="sb" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <div className="field"><label><span className="eye-tag" style={{ color: "#2e5e99" }}>OD</span> · Segmento anterior</label><textarea rows="2" value={f.bmcOD} onChange={(e) => set("bmcOD", e.target.value)} placeholder="Córnea, cámara anterior, cristalino…"></textarea></div>
              <div className="field"><label><span className="eye-tag" style={{ color: "#9f2d3e" }}>OS</span> · Segmento anterior</label><textarea rows="2" value={f.bmcOS} onChange={(e) => set("bmcOS", e.target.value)} placeholder="Córnea, cámara anterior, cristalino…"></textarea></div>
              <div className="field"><label><span className="eye-tag" style={{ color: "#2e5e99" }}>OD</span> · Fondo de ojo</label><textarea rows="2" value={f.fondoOD} onChange={(e) => set("fondoOD", e.target.value)} placeholder="Papila, mácula, retina periférica…"></textarea></div>
              <div className="field"><label><span className="eye-tag" style={{ color: "#9f2d3e" }}>OS</span> · Fondo de ojo</label><textarea rows="2" value={f.fondoOS} onChange={(e) => set("fondoOS", e.target.value)} placeholder="Papila, mácula, retina periférica…"></textarea></div>
            </div>
          </div>

          {/* Diagnósticos */}
          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-clipboard-pulse-outline"></i><span className="t">Diagnósticos (CIE-10)</span>{dx.length > 0 && <span className="badge badge--primary">{dx.length}</span>}</div>
            <div className="sb">
              <div className="dx-input">
                <input value={dxText} onChange={(e) => setDxText(e.target.value)} placeholder="Buscar o escribir diagnóstico…"
                  onKeyDown={(e) => { if (e.key === "Enter" && dxText.trim()) addDx({ code: "—", nm: dxText.trim() }); }} />
                <button className="btn btn-primary" onClick={() => dxText.trim() && addDx({ code: "—", nm: dxText.trim() })}><i className="mdi mdi-plus"></i>Añadir</button>
              </div>
              <div className="dx-sugg">
                {CIE10.filter((d) => !dx.some((x) => x.code === d.code)).map((d) => (
                  <button key={d.code} onClick={() => addDx(d)}>{d.code} · {d.nm}</button>
                ))}
              </div>
              {dx.length > 0 && (
                <div className="dx-list">
                  {dx.map((d, i) => (
                    <div key={i} className="dx-item">
                      <span className="code">{d.code}</span><span className="nm">{d.nm}</span>
                      <button className="rm" onClick={() => setDx(dx.filter((_, j) => j !== i))}><i className="mdi mdi-close"></i></button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Exámenes complementarios */}
          <ExamenesSelector examenes={examenes} setExamenes={setExamenes} />

          {/* Solicitudes */}
          <SolicitudBuilder solicitudes={solicitudes} setSolicitudes={setSolicitudes} dxList={dx} notify={notify} />

          {/* Plan */}
          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-file-sign"></i><span className="t">Plan e indicaciones</span></div>
            <div className="sb" style={{ display: "grid", gap: 12 }}>
              <div className="field"><label>Plan e indicaciones</label><textarea rows="3" value={f.plan} onChange={(e) => set("plan", e.target.value)} placeholder="Conducta, derivaciones, control…"></textarea></div>
            </div>
          </div>

          {/* Receta */}
          <RecetaBuilder recetas={recetas} setRecetas={setRecetas} notify={notify} />
        </div>

        {/* Lateral */}
        <div className="hc-side">
          <AsistenteVirtual ctx={{ f, dx, recetas, examenes, solicitudes, antec }} acts={acts} />

          <div className="hc-pat">
            <div className="ph">
              <div className="av">{c.paciente.split(" ").map((w) => w[0]).slice(0, 2).join("")}</div>
              <div><div className="nm">{c.paciente}</div><div className="sb">{c.hc}</div></div>
            </div>
            <div className="pr"><span className="k">Edad</span><span className="v">{c.edad} años</span></div>
            <div className="pr"><span className="k">Afiliación</span><span className="v">{c.afil}</span></div>
            <div className="pr"><span className="k">Teléfono</span><span className="v">{c.tel || "—"}</span></div>
            <div className="pr"><span className="k">Sede</span><span className="v">{sede(c.sede).label}</span></div>
            <div className="pr"><span className="k">WhatsApp</span><span className="v"><WaIcon status={c.whatsapp} size={15} /></span></div>
          </div>

          <div className="hc-sec">
            <div className="sh"><i className="mdi mdi-history"></i><span className="t">Consultas previas</span></div>
            <div className="sb" style={{ paddingTop: 6, paddingBottom: 6 }}>
              {histPrev.map((h, i) => (
                <div key={i} className="hist-item">
                  <div className="d">{h.d}</div>
                  <div className="dx">{h.dx}</div>
                  <div className="dr">{h.dr}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { MiAgenda, ConsultaScreen });
