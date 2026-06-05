/* MedForge Agendamiento V3 — shell + estado + orquestación (API real) */

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "slotMin"     : 15,
  "density"     : "normal",
  "dark"        : false,
  "showBloqueos": true,
  "showSobre"   : true
}/*EDITMODE-END*/;

function App() {
  const [loading,    setLoading]    = React.useState(true);
  const [loadError,  setLoadError]  = React.useState(null);
  const [t,          setTweak]      = useTweaks(TWEAK_DEFAULTS);
  const [view,       setView]       = React.useState("agenda");
  const [sedeId,     setSedeId]     = React.useState("ceibos");
  const [citas,      setCitas]      = React.useState([]);
  const [bloqueos,   setBloqueos]   = React.useState([]);
  const [modal,      setModal]      = React.useState(null);
  const [toast,      setToast]      = React.useState(null);
  const [docId,      setDocId]      = React.useState("m_ramirez");
  const [consultaId, setConsultaId] = React.useState(null);

  /* ---------- carga inicial ---------- */
  React.useEffect(() => {
    const today = new Date().toISOString().slice(0, 10);
    Promise.all([
      AgendaAPI.fetchConfig(),
      AgendaAPI.fetchCitas(today),
      AgendaAPI.fetchBloqueos(today),
    ]).then(([config, citasData, bData]) => {
      Object.assign(window.AG, config, { HOY: today, CITAS: citasData, BLOQUEOS: bData });
      setCitas(citasData);
      setBloqueos(bData);
      // update docId to first medico if available
      if (config.MEDICOS && config.MEDICOS.length) setDocId(config.MEDICOS[0].id);
      setLoading(false);
    }).catch((err) => {
      setLoadError(err.message || 'Error al cargar agenda');
      setLoading(false);
    });
  }, []);

  /* ---------- polling FlowBoard 30 s ---------- */
  React.useEffect(() => {
    if (view !== 'flowboard' || loading) return;
    const timer = setInterval(() => {
      AgendaAPI.fetchCitas(AG.HOY, sedeId)
        .then((data) => { setCitas(data); window.AG.CITAS = data; })
        .catch(() => {});
    }, 30000);
    return () => clearInterval(timer);
  }, [view, sedeId, loading]);

  /* ---------- dark mode ---------- */
  React.useEffect(() => { document.body.classList.toggle("dark", !!t.dark); }, [t.dark]);

  const nowMin = new Date().getHours() * 60 + new Date().getMinutes();
  const tweaks = { slotMin: Number(t.slotMin), density: t.density, showBloqueos: t.showBloqueos, showSobre: t.showSobre };
  const state  = { citas, bloqueos };

  function notify(msg, tone = "info") {
    setToast({ msg, tone });
    clearTimeout(window.__tt); window.__tt = setTimeout(() => setToast(null), 2600);
  }

  /* ---------- acciones ---------- */
  function openNew(prefill)  { setModal({ mode: "new", prefill: { ...prefill, sede: sedeId } }); }
  function openView(id)      { setModal({ mode: "view", id }); }
  function openEdit(c)       { setModal({ mode: "new", prefill: { ...c } }); }

  function openConsulta(id) {
    setModal(null);
    setConsultaId(id);
    const dbId = parseInt(id.replace('C', ''));
    const now  = toHHMM(nowMin);
    setCitas((prev) => prev.map((c) => {
      if (c.id !== id) return c;
      if (!['confirmado', 'en_sala', 'agendado'].includes(c.estado)) return c;
      AgendaAPI.avanzarCita(dbId, {}).catch(() => {});
      return { ...c, estado: 'en_consulta', horaConsulta: c.horaConsulta || now, horaSala: c.horaSala || now };
    }));
  }

  async function finishConsulta(id, data) {
    const dbId = parseInt(id.replace('C', ''));
    try {
      const saved = await AgendaAPI.finalizarConsulta(dbId, data);
      setCitas((prev) => prev.map((c) => c.id === id ? saved : c));
      setConsultaId(null);
      notify("Consulta finalizada e historia clínica guardada", "success");
    } catch (err) {
      notify(err.message, "danger");
    }
  }

  async function saveCita(data) {
    const payload = {
      fecha      : data.fecha || AG.HOY,
      sede_id    : data.sede,
      medico_id  : data.medico,
      sala_id    : data.sala,
      tipo_id    : data.tipo,
      paciente   : data.paciente,
      hc_number  : data.hc  || '',
      edad       : data.edad || null,
      afiliacion : data.afil || '',
      tel        : data.tel  || '',
      hora_ini   : data.ini,
      sobreturno : !!data.sobreturno,
      notas      : data.notas || '',
    };
    try {
      let saved;
      if (data.id) {
        const dbId = parseInt(data.id.replace('C', ''));
        saved = await AgendaAPI.updateCita(dbId, payload);
        setCitas((prev) => prev.map((c) => c.id === data.id ? saved : c));
      } else {
        saved = await AgendaAPI.createCita(payload);
        setCitas((prev) => [...prev, saved]);
      }
      setModal(null);
      notify(data.id ? "Cita actualizada" : "Cita creada y recordatorio enviado", "success");
    } catch (err) {
      notify(err.message, "danger");
    }
  }

  async function cancelCita() {
    const id   = modal.id;
    const dbId = parseInt(id.replace('C', ''));
    try {
      await AgendaAPI.cancelarCita(dbId);
      setCitas((prev) => prev.map((c) => c.id === id ? { ...c, estado: "cancelado" } : c));
      setModal(null);
      notify("Cita cancelada", "danger");
    } catch (err) {
      notify(err.message, "danger");
    }
  }

  async function advance(id, force) {
    const dbId = parseInt(id.replace('C', ''));
    try {
      const saved = await AgendaAPI.avanzarCita(dbId, force === 'ausente' ? { force: 'ausente' } : {});
      setCitas((prev) => prev.map((c) => c.id === id ? saved : c));
      setModal((m) => (m && m.mode === "view" && m.id === id ? { ...m } : m));
    } catch (err) {
      notify(err.message, "danger");
    }
  }

  function resend(id) {
    const realId = id || (modal && modal.id);
    setCitas((prev) => prev.map((c) => c.id === realId ? { ...c, whatsapp: "enviado" } : c));
    notify("Recordatorio reenviado por WhatsApp", "success");
  }

  const citaActual = modal && modal.mode === "view" ? citas.find((c) => c.id === modal.id) : null;

  /* ---------- nav ---------- */
  const nav = [
    { g: "Agendamiento" },
    { id: "agenda",    icon: "mdi-calendar-month-outline",   label: "Calendario operativo" },
    { id: "flowboard", icon: "mdi-monitor-dashboard",        label: "FlowBoard recepción", live: true },
    { id: "miagenda",  icon: "mdi-clipboard-pulse-outline",  label: "Mi agenda (médico)" },
    { id: "config",    icon: "mdi-cog-outline",              label: "Configuración base" },
    { id: "spec",      icon: "mdi-code-braces",              label: "Especificación técnica" },
    { g: "Otros módulos" },
    { id: "_pac",  icon: "mdi-account-multiple-outline",     label: "Pacientes",    muted: true },
    { id: "_cir",  icon: "mdi-hospital-box-outline",         label: "Cirugías",     muted: true },
    { id: "_sol",  icon: "mdi-clipboard-text-clock-outline", label: "Solicitudes",  muted: true },
    { id: "_wa",   icon: "mdi-whatsapp",                     label: "WhatsApp CRM", muted: true },
    { g: "Sistema" },
    { id: "_back", icon: "mdi-arrow-left-circle-outline",    label: "Volver a MedForge",
      href: (window.__MF__ && window.__MF__.backUrl) || "/v2/dashboard" },
  ];

  const heads = {
    agenda   : { crumb: "Agenda › Calendario",     title: "Calendario operativo",   sub: "Consulta, crea y reagenda citas por médico, sala o área." },
    flowboard: { crumb: "Agenda › FlowBoard",      title: "FlowBoard de recepción", sub: "Estado de cada paciente en tiempo real para la recepción." },
    miagenda : { crumb: "Agenda › Mi agenda",      title: "Mi agenda del día",      sub: "Tus pacientes del día: abre la consulta y llena la historia clínica." },
    config   : { crumb: "Agenda › Configuración",  title: "Configuración base",     sub: "Horarios, salas, tipos de cita, áreas y bloqueos." },
    spec     : { crumb: "Agenda › Especificación", title: "Especificación técnica", sub: "Modelo de datos, reglas, endpoints y estructura React." },
  };
  const h = heads[view] || heads.agenda;
  const citaConsulta = consultaId ? citas.find((c) => c.id === consultaId) : null;
  const totalSede    = citas.filter((c) => c.sede === sedeId && c.estado !== "cancelado").length;
  const pendConfirm  = citas.filter((c) => c.sede === sedeId && c.estado === "agendado").length;

  /* ---------- loading / error screen ---------- */
  if (loading || loadError) {
    return (
      <div style={{ display:"grid", placeItems:"center", minHeight:"100vh", background:"var(--bg-page)", fontFamily:"var(--font-body)" }}>
        {loadError ? (
          <div style={{ textAlign:"center", maxWidth:400 }}>
            <i className="mdi mdi-alert-circle-outline" style={{ fontSize:40, color:"var(--danger)", display:"block", marginBottom:12 }}></i>
            <div style={{ fontWeight:700, fontSize:15, marginBottom:6, color:"var(--fg-1)" }}>No se pudo cargar la agenda</div>
            <div style={{ fontSize:12.5, color:"var(--fg-2)", marginBottom:18 }}>{loadError}</div>
            <button className="btn btn-primary" onClick={() => location.reload()}>
              <i className="mdi mdi-refresh"></i>Reintentar
            </button>
          </div>
        ) : (
          <div style={{ textAlign:"center" }}>
            <i className="mdi mdi-calendar-clock-outline" style={{ fontSize:40, color:"var(--primary)", display:"block", marginBottom:12, animation:"spin 1.2s linear infinite" }}></i>
            <div style={{ fontWeight:600, fontSize:14, color:"var(--fg-1)" }}>Cargando agenda…</div>
          </div>
        )}
      </div>
    );
  }

  const userNombre = (window.__MF__ && window.__MF__.user && window.__MF__.user.nombre) || '';

  return (
    <div className="app">
      <div className="app-logo">
        <img src={t.dark ? "/agenda-v3/assets/logo-on-dark.png" : "/agenda-v3/assets/logo-on-light.png"} alt="MedForge" />
        <span className="env">Agenda</span>
      </div>

      <div className="app-header">
        <div className="clock">
          {toHHMM(nowMin)} <small>· {new Date().toLocaleDateString("es-EC", { weekday:"short", day:"numeric", month:"short" })}</small>
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
          <div className="avatar">{userNombre ? userNombre.slice(0,2).toUpperCase() : 'RV'}</div>
          <div>
            <div className="name">{userNombre || 'Recepción'}</div>
            <div className="role">MedForge</div>
          </div>
        </div>
      </div>

      <div className="app-sidebar">
        {nav.map((n, i) => n.g
          ? <h6 key={i}>{n.g}</h6>
          : n.href
            ? <a key={n.id} href={n.href}><i className={`mdi ${n.icon}`}></i>{n.label}</a>
            : (
              <a key={n.id} className={`${view===n.id ? "active":""}${n.muted ? " muted-link":""}`}
                 onClick={() => { if (n.muted) { notify("Módulo fuera del alcance de este prototipo"); } else setView(n.id); }}>
                <i className={`mdi ${n.icon}`}></i>{n.label}
                {n.id==="flowboard" && pendConfirm>0 && <span className="pill">{pendConfirm}</span>}
                {n.live && view!=="flowboard" && <span className="pill soft" style={{display:"inline-flex",alignItems:"center",gap:4}}><i className="mdi mdi-circle" style={{fontSize:7}}></i>vivo</span>}
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
                  {(view==="agenda"||view==="flowboard") && (
                    <span className="badge badge--light" style={{padding:"6px 11px"}}>
                      <i className="mdi mdi-map-marker-outline"></i>{sede(sedeId).label} · {totalSede} citas hoy
                    </span>
                  )}
                  {view==="agenda"    && <button className="btn btn-primary" onClick={()=>openNew({})}><i className="mdi mdi-plus"></i>Nueva cita</button>}
                  {view==="flowboard" && <span className="badge badge--success" style={{padding:"6px 11px"}}><i className="mdi mdi-circle" style={{fontSize:8}}></i>En vivo · actualiza 30 s</span>}
                  {view==="spec"      && <button className="btn btn-outline-secondary" onClick={()=>notify("Exportación de spec (demo)")}><i className="mdi mdi-download-outline"></i>Exportar</button>}
                </div>
              </div>
            </div>

            <div className="page-scroll">
              {view==="agenda"    && <Calendario state={state} tweaks={tweaks} sedeId={sedeId} nowMin={nowMin} onNew={openNew} onOpen={openView} />}
              {view==="flowboard" && <FlowBoard  state={state} sedeId={sedeId} nowMin={nowMin} onOpen={openView} onAdvance={advance} onResend={resend} />}
              {view==="miagenda"  && <MiAgenda   state={state} docId={docId} setDocId={setDocId} nowMin={nowMin} onConsulta={openConsulta} onOpen={openView} />}
              {view==="config"    && <ConfigModule sedeId={sedeId} notify={notify} />}
              {view==="spec"      && <SpecModule />}
            </div>
          </React.Fragment>
        )}
      </div>

      {modal && modal.mode==="new" && (
        <ApptForm prefill={modal.prefill} state={state} sedeId={sedeId} onSave={saveCita} onClose={()=>setModal(null)} />
      )}
      {citaActual && (
        <ApptDetail cita={citaActual} onClose={()=>setModal(null)} onEdit={openEdit}
          onAdvance={advance} onCancel={cancelCita} onResend={()=>resend()} onConsulta={openConsulta} />
      )}

      {toast && (
        <div style={{position:"fixed",bottom:22,left:"50%",transform:"translateX(-50%)",zIndex:900,
          background:"var(--bg-surface)",border:"1px solid var(--border)",borderLeft:`4px solid var(--${toast.tone==="info"?"primary":toast.tone})`,
          borderRadius:10,boxShadow:"var(--shadow)",padding:"11px 16px",display:"flex",alignItems:"center",gap:9,
          font:"600 12.5px var(--font-body)",color:"var(--fg-1)",animation:"pop .2s ease-out"}}>
          <i className={`mdi ${toast.tone==="success"?"mdi-check-circle-outline":toast.tone==="danger"?"mdi-information-outline":"mdi-bell-outline"}`}
             style={{color:`var(--${toast.tone==="info"?"primary":toast.tone})`,fontSize:17}}></i>
          {toast.msg}
        </div>
      )}

      <TweaksPanel>
        <TweakSection label="Agenda" />
        <TweakRadio  label="Densidad"              value={t.density}      options={["compacta","normal","comoda"]} onChange={(v)=>setTweak("density",v)} />
        <TweakSelect label="Duración base del slot" value={String(t.slotMin)} options={["10","15","20","30"]}      onChange={(v)=>setTweak("slotMin",Number(v))} />
        <TweakToggle label="Mostrar sobreturnos"   value={t.showSobre}    onChange={(v)=>setTweak("showSobre",v)} />
        <TweakToggle label="Mostrar bloqueos"      value={t.showBloqueos} onChange={(v)=>setTweak("showBloqueos",v)} />
        <TweakSection label="Tema" />
        <TweakToggle label="Modo oscuro"           value={t.dark}         onChange={(v)=>setTweak("dark",v)} />
      </TweaksPanel>
    </div>
  );
}

/* spinner keyframe */
(function(){ var s=document.createElement('style'); s.textContent='@keyframes spin{to{transform:rotate(360deg)}}@keyframes pop{from{opacity:0;transform:translateX(-50%) scale(.9)}to{opacity:1;transform:translateX(-50%) scale(1)}}'; document.head.appendChild(s); })();

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
