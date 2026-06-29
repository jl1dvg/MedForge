/* MedForge Agendamiento — FlowBoard de recepción (tiempo real) */

function FlowBoard({ state, sedeId, nowMin, onOpen, onAdvance, onResend }) {
  const flujo = ["agendado", "confirmado", "en_sala", "en_consulta", "completado"];
  const citas = state.citas.filter((c) => c.sede === sedeId);

  // alertas
  const retrasados = citas.filter((c) => (c.estado === "agendado" || c.estado === "confirmado") && toMin(c.ini) < nowMin);
  const sinConfirmar = citas.filter((c) => c.estado === "agendado" && c.whatsapp !== "confirmado" && c.whatsapp !== "cancelado_wa");
  const enAtencion = citas.filter((c) => c.estado === "en_sala" || c.estado === "en_consulta");
  const completados = citas.filter((c) => c.estado === "completado");
  const ausentes = citas.filter((c) => c.estado === "ausente");

  const colData = flujo.map((id) => ({
    e: estado(id),
    cards: citas.filter((c) => c.estado === id).sort((a, b) => toMin(a.ini) - toMin(b.ini)),
  }));

  return (
    <React.Fragment>
      <div className="fb-alerts">
        <div className="fb-alert danger">
          <i className="mdi mdi-clock-alert-outline"></i>
          <div><div className="n">{retrasados.length}</div><div className="l">Con retraso</div></div>
        </div>
        <div className="fb-alert warn">
          <i className="mdi mdi-help-circle-outline"></i>
          <div><div className="n">{sinConfirmar.length}</div><div className="l">Sin confirmar</div></div>
        </div>
        <div className="fb-alert info">
          <i className="mdi mdi-account-clock-outline"></i>
          <div><div className="n">{enAtencion.length}</div><div className="l">En atención ahora</div></div>
        </div>
        <div className="fb-alert ok">
          <i className="mdi mdi-check-all"></i>
          <div><div className="n">{completados.length}</div><div className="l">Completados hoy</div></div>
        </div>
        <div className="fb-alert" style={{ background: "var(--bg-surface)", borderColor: "var(--border)", color: "var(--fg-2)" }}>
          <i className="mdi mdi-account-cancel-outline" style={{ color: "var(--fg-mute)" }}></i>
          <div><div className="n" style={{ color: "var(--fg-1)" }}>{ausentes.length}</div><div className="l">Ausentes</div></div>
        </div>
      </div>

      <div className="fb-board">
        {colData.map(({ e, cards }) => (
          <div key={e.id} className="fb-col">
            <div className="fb-col-h">
              <span className="dot" style={{ background: `var(--${e.tone === "light" ? "fg-mute" : e.tone})` }}></span>
              <span className="t">{e.label}</span>
              <span className="c">{cards.length}</span>
            </div>
            {cards.length === 0 && <div className="fb-empty">Sin pacientes</div>}
            {cards.map((c) => <FlowCard key={c.id} c={c} nowMin={nowMin} onOpen={onOpen} onAdvance={onAdvance} onResend={onResend} />)}
          </div>
        ))}
      </div>

      {(ausentes.length > 0) && (
        <div style={{ marginTop: 16 }}>
          <Box title="Sin atención" icon="mdi-account-alert-outline" noPad>
            <div style={{ padding: "12px 16px", display: "flex", flexWrap: "wrap", gap: 10 }}>
              {ausentes.map((c) => (
                <div key={c.id} className="fb-card" style={{ width: 280, marginBottom: 0, borderLeftColor: area(c.area).color, cursor: "pointer" }} onClick={() => onOpen(c.id)}>
                  <div className="top"><span className="nm">{c.paciente}</span><span className="tm">{c.ini}</span></div>
                  <div className="meta">{c.hc} · {medico(c.medico).nombre}</div>
                  <div className="row2"><Badge tone="danger" icon="mdi-account-cancel-outline">No se presentó</Badge>
                    <span className="wait">recordatorio: {c.whatsapp === "sin_respuesta" ? "sin respuesta" : "—"}</span></div>
                </div>
              ))}
            </div>
          </Box>
        </div>
      )}
    </React.Fragment>
  );
}

function FlowCard({ c, nowMin, onOpen, onAdvance, onResend }) {
  const a = area(c.area), m = medico(c.medico), s = sala(c.sala);
  const order = ["agendado", "confirmado", "en_sala", "en_consulta", "completado"];
  const idx = order.indexOf(c.estado);
  const next = order[idx + 1];

  // métricas de tiempo según estado
  let delay = null, wait = null;
  if (c.estado === "agendado" || c.estado === "confirmado") {
    const d = nowMin - toMin(c.ini);
    if (d > 0) delay = d;
  } else if (c.estado === "en_sala" && c.horaSala) {
    wait = nowMin - toMin(c.horaSala);
  } else if (c.estado === "en_consulta" && c.horaConsulta) {
    wait = nowMin - toMin(c.horaConsulta);
  }

  const nextLabel = { confirmado: "Confirmar", en_sala: "Marcar en sala", en_consulta: "Pasar a consulta", completado: "Completar" }[next];
  const nextIcon = { confirmado: "mdi-calendar-check-outline", en_sala: "mdi-door-open", en_consulta: "mdi-stethoscope", completado: "mdi-check-circle-outline" }[next];

  return (
    <div className="fb-card" style={{ borderLeftColor: a.color }}>
      <div className="top" onClick={() => onOpen(c.id)} style={{ cursor: "pointer" }}>
        <span className="nm">{c.paciente}</span>
        <span className="tm">{c.ini}</span>
      </div>
      <div className="meta" onClick={() => onOpen(c.id)} style={{ cursor: "pointer" }}>{c.hc} · {c.edad} a · {m.nombre.replace("Dra. ", "").replace("Dr. ", "").replace("Lic. ", "")}</div>
      <div className="proc" style={{ color: a.fg }} onClick={() => onOpen(c.id)}>{tipo(c.tipo).label || c.notas || "Atención SigCenter"}</div>
      <div className="row2">
        <span className="chip-tag" style={{ background: a.bg, color: a.fg, fontSize: 9.5, padding: "2px 7px" }}>{s.label || "Sin sala"}</span>
        <WaIcon status={c.whatsapp} />
        {c.sobreturno && <i className="mdi mdi-flash" style={{ color: "var(--warning)", fontSize: 13 }} title="Sobreturno"></i>}
        {delay != null && <span className="delay"><i className="mdi mdi-clock-alert-outline"></i>{delay}′ tarde</span>}
        {wait != null && <span className="wait">hace {wait}′</span>}
      </div>
      {!c._readonly && c.estado === "agendado" && c.whatsapp !== "confirmado" && (
        <div className="adv">
          <button className="btn sm btn-outline-success" onClick={() => onResend(c.id)}><i className="mdi mdi-whatsapp"></i>Reenviar confirmación</button>
        </div>
      )}
      {!c._readonly && next && (
        <div className="adv" style={{ display: "flex", gap: 6 }}>
          <button className="btn sm btn-primary" style={{ flex: 1 }} onClick={() => onAdvance(c.id)}><i className={`mdi ${nextIcon}`}></i>{nextLabel}</button>
          {c.estado === "agendado" && <button className="btn sm btn-outline-secondary" title="Marcar ausente" onClick={() => onAdvance(c.id, "ausente")}><i className="mdi mdi-account-cancel-outline"></i></button>}
        </div>
      )}
      {c._readonly && (
        <div style={{ marginTop: 6, fontSize: 10, color: "var(--fg-mute)", display: "flex", alignItems: "center", gap: 4 }}>
          <i className="mdi mdi-sync" style={{ fontSize: 11 }}></i>SigCenter
        </div>
      )}
    </div>
  );
}

Object.assign(window, { FlowBoard });
