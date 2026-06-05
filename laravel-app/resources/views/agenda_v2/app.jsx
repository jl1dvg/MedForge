/* MedForge Agendamiento — app shell + estado + orquestación */

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "slotMin": 15,
  "density": "normal",
  "dark": false,
  "showBloqueos": true,
  "showSobre": true
}/*EDITMODE-END*/;

const NOW_MIN = 10 * 60 + 12; // 10:12 — hora simulada de la clínica

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [view, setView] = React.useState("agenda");
  const [sedeId, setSedeId] = React.useState("ceibos");
  const [citas, setCitas] = React.useState(() => AG.CITAS.map((c) => ({ ...c })));
  const [bloqueos] = React.useState(() => AG.BLOQUEOS.map((b) => ({ ...b })));
  const [modal, setModal] = React.useState(null); // {mode, prefill} | {mode:'view', id}
  const [toast, setToast] = React.useState(null);
  const [docId, setDocId] = React.useState("m_ramirez");
  const [consultaId, setConsultaId] = React.useState(null);

  React.useEffect(() => { document.body.classList.toggle("dark", !!t.dark); }, [t.dark]);

  const tweaks = { slotMin: Number(t.slotMin), density: t.density, showBloqueos: t.showBloqueos, showSobre: t.showSobre };
  const state = { citas, bloqueos };

  function notify(msg, tone = "info") {
    setToast({ msg, tone });
    clearTimeout(window.__tt); window.__tt = setTimeout(() => setToast(null), 2600);
  }

  /* ---------- acciones ---------- */
  function openNew(prefill) { setModal({ mode: "new", prefill: { ...prefill, sede: sedeId } }); }
  function openView(id) { setModal({ mode: "view", id }); }
  function openEdit(c) { setModal({ mode: "new", prefill: { ...c } }); }
  function openConsulta(id) {
    setModal(null);
    setConsultaId(id);
    // al abrir la consulta, marca al paciente en consulta si aún no se atendió
    setCitas((prev) => prev.map((c) => (c.id === id && (c.estado === "confirmado" || c.estado === "en_sala" || c.estado === "agendado")
      ? { ...c, estado: "en_consulta", horaConsulta: c.horaConsulta || toHHMM(NOW_MIN), horaSala: c.horaSala || toHHMM(NOW_MIN) } : c)));
  }
  function finishConsulta(id, data) {
    setCitas((prev) => prev.map((c) => (c.id === id ? { ...c, estado: "completado", hcLlena: true, hcData: data, horaFin: toHHMM(NOW_MIN) } : c)));
    setConsultaId(null);
    notify("Consulta finalizada e historia clínica guardada", "success");
  }

  function saveCita(data) {
    setCitas((prev) => {
      if (data.id) return prev.map((c) => (c.id === data.id ? { ...c, ...data } : c));
      return [...prev, { ...data, id: "C" + Date.now(), horaLlegada: null, horaSala: null, horaConsulta: null, horaFin: null }];
    });
    setModal(null);
    notify(data.id ? "Cita actualizada" : "Cita creada y recordatorio enviado", "success");
  }

  function cancelCita() {
    const id = modal.id;
    setCitas((prev) => prev.map((c) => (c.id === id ? { ...c, estado: "cancelado" } : c)));
    setModal(null);
    notify("Cita cancelada", "danger");
  }

  function advance(id, force) {
    const order = ["agendado", "confirmado", "en_sala", "en_consulta", "completado"];
    setCitas((prev) => prev.map((c) => {
      if (c.id !== id) return c;
      if (force === "ausente") return { ...c, estado: "ausente" };
      const i = order.indexOf(c.estado);
      const next = order[Math.min(i + 1, order.length - 1)];
      const stamp = toHHMM(NOW_MIN);
      const patch = { estado: next };
      if (next === "confirmado") patch.whatsapp = "confirmado";
      if (next === "en_sala") patch.horaSala = stamp;
      if (next === "en_consulta") patch.horaConsulta = stamp;
      if (next === "completado") patch.horaFin = stamp;
      return { ...c, ...patch };
    }));
    // mantener modal de vista sincronizado
    setModal((m) => (m && m.mode === "view" && m.id === id ? { ...m } : m));
  }

  function resend(id) {
    const realId = id || (modal && modal.id);
    setCitas((prev) => prev.map((c) => (c.id === realId ? { ...c, whatsapp: "enviado" } : c)));
    notify("Recordatorio reenviado por WhatsApp", "success");
  }

  const citaActual = modal && modal.mode === "view" ? citas.find((c) => c.id === modal.id) : null;

  /* ---------- nav ---------- */
  const nav = [
    { g: "Agendamiento" },
    { id: "agenda", icon: "mdi-calendar-month-outline", label: "Calendario operativo" },
    { id: "flowboard", icon: "mdi-monitor-dashboard", label: "FlowBoard recepción", live: true },
    { id: "miagenda", icon: "mdi-clipboard-pulse-outline", label: "Mi agenda (médico)" },
    { id: "config", icon: "mdi-cog-outline", label: "Configuración base" },
    { id: "spec", icon: "mdi-code-braces", label: "Especificación técnica" },
    { g: "Otros módulos" },
    { id: "_pac", icon: "mdi-account-multiple-outline", label: "Pacientes", muted: true },
    { id: "_cir", icon: "mdi-hospital-box-outline", label: "Cirugías", muted: true },
    { id: "_sol", icon: "mdi-clipboard-text-clock-outline", label: "Solicitudes", muted: true },
    { id: "_wa", icon: "mdi-whatsapp", label: "WhatsApp CRM", muted: true },
  ];

  const heads = {
    agenda: { crumb: "Agenda › Calendario", title: "Calendario operativo", sub: "Consulta, crea y reagenda citas por médico, sala o área." },
    flowboard: { crumb: "Agenda › FlowBoard", title: "FlowBoard de recepción", sub: "Estado de cada paciente en tiempo real para la recepción." },
    miagenda: { crumb: "Agenda › Mi agenda", title: "Mi agenda del día", sub: "Tus pacientes del día: abre la consulta y llena la historia clínica." },
    config: { crumb: "Agenda › Configuración", title: "Configuración base", sub: "Horarios, salas, tipos de cita, áreas y bloqueos." },
    spec: { crumb: "Agenda › Especificación", title: "Especificación técnica", sub: "Modelo de datos, reglas, endpoints y estructura React." },
  };
  const h = heads[view] || heads.agenda;
  const citaConsulta = consultaId ? citas.find((c) => c.id === consultaId) : null;
  const totalSede = citas.filter((c) => c.sede === sedeId && c.estado !== "cancelado").length;
  const pendConfirm = citas.filter((c) => c.sede === sedeId && c.estado === "agendado").length;

  return (
    <div className="app">
      <div className="app-logo">
        <img src={t.dark ? "assets/logo-on-dark.png" : "assets/logo-on-light.png"} alt="MedForge" />
        <span className="env">Agenda</span>
      </div>

      <div className="app-header">
        <div className="clock">
          {toHHMM(NOW_MIN)} <small>· jue 5 jun</small>
        </div>
        <div className="app-search">
          <i className="mdi mdi-magnify"></i>
          <input placeholder="Buscar paciente, HC, médico…" />
        </div>
        <div className="spacer" style={{ flex: 1 }}></div>
        <div className="sede-switch">
          {AG.SEDES.map((s) => (
            <button key={s.id} className={sedeId === s.id ? "on" : ""} onClick={() => setSedeId(s.id)}>
              <i className="mdi mdi-map-marker-outline"></i>{s.label}
            </button>
          ))}
        </div>
        <button className="bell-btn"><i className="mdi mdi-bell-outline"></i><span className="dot">{pendConfirm}</span></button>
        <div className="user-chip">
          <div className="avatar">RV</div>
          <div><div className="name">Recepción Ceibos</div><div className="role">Front desk</div></div>
        </div>
      </div>

      <div className="app-sidebar">
        {nav.map((n, i) => n.g
          ? <h6 key={i}>{n.g}</h6>
          : (
            <a key={n.id} className={`${view === n.id ? "active" : ""}${n.muted ? " muted-link" : ""}`}
               onClick={() => { if (n.muted) { notify("Módulo fuera del alcance de este prototipo"); } else setView(n.id); }}>
              <i className={`mdi ${n.icon}`}></i>{n.label}
              {n.id === "flowboard" && pendConfirm > 0 && <span className="pill">{pendConfirm}</span>}
              {n.live && view !== "flowboard" && <span className="pill soft" style={{ display: "inline-flex", alignItems: "center", gap: 4 }}><i className="mdi mdi-circle" style={{ fontSize: 7 }}></i>vivo</span>}
            </a>
          ))}
      </div>

      <div className="app-content">
        {citaConsulta ? (
          <ConsultaScreen cita={citaConsulta} onClose={() => setConsultaId(null)} onFinish={finishConsulta} notify={notify} />
        ) : (
        <React.Fragment>
        <div className="page-head">
          <div className="row">
            <div>
              <div className="crumb"><i className="mdi mdi-home-outline"></i> {h.crumb}</div>
              <h1>{h.title}</h1>
              <div className="sub">{h.sub}</div>
            </div>
            <div className="actions">
              {(view === "agenda" || view === "flowboard") && (
                <span className="badge badge--light" style={{ padding: "6px 11px" }}>
                  <i className="mdi mdi-map-marker-outline"></i>{sede(sedeId).label} · {totalSede} citas hoy
                </span>
              )}
              {view === "agenda" && <button className="btn btn-primary" onClick={() => openNew({})}><i className="mdi mdi-plus"></i>Nueva cita</button>}
              {view === "flowboard" && <span className="badge badge--success" style={{ padding: "6px 11px" }}><i className="mdi mdi-circle spin" style={{ fontSize: 8 }}></i>En vivo · actualiza 30 s</span>}
              {view === "spec" && <button className="btn btn-outline-secondary" onClick={() => notify("Exportación de spec (demo)")}><i className="mdi mdi-download-outline"></i>Exportar</button>}
            </div>
          </div>
        </div>

        <div className="page-scroll">
          {view === "agenda" && <Calendario state={state} tweaks={tweaks} sedeId={sedeId} nowMin={NOW_MIN} onNew={openNew} onOpen={openView} />}
          {view === "flowboard" && <FlowBoard state={state} sedeId={sedeId} nowMin={NOW_MIN} onOpen={openView} onAdvance={advance} onResend={resend} />}
          {view === "miagenda" && <MiAgenda state={state} docId={docId} setDocId={setDocId} nowMin={NOW_MIN} onConsulta={openConsulta} onOpen={openView} />}
          {view === "config" && <ConfigModule sedeId={sedeId} notify={notify} />}
          {view === "spec" && <SpecModule />}
        </div>
        </React.Fragment>
        )}
      </div>

      {/* modales */}
      {modal && modal.mode === "new" && (
        <ApptForm prefill={modal.prefill} state={state} sedeId={sedeId} onSave={saveCita} onClose={() => setModal(null)} />
      )}
      {citaActual && (
        <ApptDetail cita={citaActual} onClose={() => setModal(null)} onEdit={openEdit}
          onAdvance={advance} onCancel={cancelCita} onResend={() => resend()} onConsulta={openConsulta} />
      )}

      {/* toast */}
      {toast && (
        <div style={{ position: "fixed", bottom: 22, left: "50%", transform: "translateX(-50%)", zIndex: 900,
          background: "var(--bg-surface)", border: "1px solid var(--border)", borderLeft: `4px solid var(--${toast.tone === "info" ? "primary" : toast.tone})`,
          borderRadius: 10, boxShadow: "var(--shadow)", padding: "11px 16px", display: "flex", alignItems: "center", gap: 9, font: "600 12.5px var(--font-body)", color: "var(--fg-1)", animation: "pop .2s ease-out" }}>
          <i className={`mdi ${toast.tone === "success" ? "mdi-check-circle-outline" : toast.tone === "danger" ? "mdi-information-outline" : "mdi-bell-outline"}`}
             style={{ color: `var(--${toast.tone === "info" ? "primary" : toast.tone})`, fontSize: 17 }}></i>
          {toast.msg}
        </div>
      )}

      {/* tweaks */}
      <TweaksPanel>
        <TweakSection label="Agenda" />
        <TweakRadio label="Densidad" value={t.density} options={["compacta", "normal", "comoda"]} onChange={(v) => setTweak("density", v)} />
        <TweakSelect label="Duración base del slot" value={String(t.slotMin)} options={["10", "15", "20", "30"]} onChange={(v) => setTweak("slotMin", Number(v))} />
        <TweakToggle label="Mostrar sobreturnos" value={t.showSobre} onChange={(v) => setTweak("showSobre", v)} />
        <TweakToggle label="Mostrar bloqueos" value={t.showBloqueos} onChange={(v) => setTweak("showBloqueos", v)} />
        <TweakSection label="Tema" />
        <TweakToggle label="Modo oscuro" value={t.dark} onChange={(v) => setTweak("dark", v)} />
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
