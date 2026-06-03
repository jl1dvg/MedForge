/* ============================================================
   MedForge · WhatsApp Chat v3 — Modals
   nueva conversación (plantilla) · cerrar seguimiento · tour
   ============================================================ */

const WA_CONTACTS = [
  { name: "Rosa Maldonado", wa: "593 99 771 4480", hc: "82110" },
  { name: "Marco Tulio Ríos", wa: "593 98 334 1209", hc: "80540" },
  { name: "Verónica Salas", wa: "593 96 220 7781", hc: null },
];

function WaNewConvoModal({ onClose, toast }) {
  const [q, setQ] = useState("");
  const [results, setResults] = useState([]);
  const [picked, setPicked] = useState(null);
  const [number, setNumber] = useState("");
  const [contact, setContact] = useState("");
  const [patient, setPatient] = useState("");
  const [hc, setHc] = useState("");
  const [tplId, setTplId] = useState("");
  const [vars, setVars] = useState([]);
  const [fb, setFb] = useState({ tone: "", text: "Selecciona un contacto o escribe el número manualmente." });

  const tpl = WAD.TEMPLATES.find((t) => String(t.id) === String(tplId));

  const doSearch = () => {
    if (!q.trim()) { setFb({ tone: "danger", text: "Escribe celular, HC o nombre." }); return; }
    const r = WA_CONTACTS.filter((c) => `${c.name} ${c.wa} ${c.hc || ""}`.toLowerCase().includes(q.toLowerCase()));
    setResults(r.length ? r : WA_CONTACTS);
    setFb({ tone: "success", text: "Selecciona un resultado o ajusta el número." });
  };
  const pick = (c) => {
    setPicked(c.wa); setNumber(c.wa); setContact(c.name); setPatient(c.name); setHc(c.hc || "");
  };
  const onTpl = (id) => {
    setTplId(id);
    const t = WAD.TEMPLATES.find((x) => String(x.id) === String(id));
    setVars(t ? t.variables.map(() => "") : []);
  };
  const preview = tpl
    ? tpl.body.replace(/\{\{\s*(\d+)\s*\}\}/g, (mm, i) => vars[Number(i) - 1] || tpl.examples[Number(i) - 1] || mm)
    : "Selecciona una plantilla para revisar el mensaje final.";

  const submit = () => {
    if (!number.trim() || !tplId) { setFb({ tone: "danger", text: "Número y plantilla son obligatorios." }); return; }
    toast(`Conversación iniciada con ${contact || number}`, "mdi-message-plus");
    onClose();
  };

  return (
    <div className="wa3-modal" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card">
        <div className="wa3-modal__head">
          <div>
            <h3>Nueva conversación con plantilla</h3>
            <div className="wa3-modal__sub">Usa una plantilla aprobada para iniciar o continuar fuera de ventana.</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          <div className="wa3-modal__grid">
            <div>
              <div className="wa3-field">
                <label>Buscar paciente o número</label>
                <div style={{ display: "flex", gap: 8 }}>
                  <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") doSearch(); }} placeholder="Celular, HC, nombres o apellidos" />
                  <button className="wa3-secondary-btn" onClick={doSearch}>Buscar</button>
                </div>
              </div>
              <div className="wa3-picker-results">
                {results.map((c, i) => (
                  <div key={i} className={`wa3-picker-card${picked === c.wa ? " is-active" : ""}`} onClick={() => pick(c)}>
                    <div><strong>{c.name}</strong><small>{c.wa}{c.hc ? ` · HC ${c.hc}` : ""}</small></div>
                    <button className="wa3-secondary-btn" onClick={(e) => { e.stopPropagation(); pick(c); }}>Usar</button>
                  </div>
                ))}
              </div>
              <div className="wa3-field" style={{ marginTop: 12 }}>
                <label>Preview del mensaje</label>
                <div className="wa3-template-preview">{preview}</div>
              </div>
            </div>
            <div>
              <div className="wa3-field"><label>Número WhatsApp</label><input value={number} onChange={(e) => setNumber(e.target.value)} placeholder="593999111222" /></div>
              <div className="wa3-field"><label>Nombre visible</label><input value={contact} onChange={(e) => setContact(e.target.value)} placeholder="Nombre del contacto" /></div>
              <div className="wa3-field"><label>Paciente</label><input value={patient} onChange={(e) => setPatient(e.target.value)} placeholder="Nombres y apellidos" /></div>
              <div className="wa3-field"><label>HC</label><input value={hc} onChange={(e) => setHc(e.target.value)} placeholder="Historia clínica" /></div>
              <div className="wa3-field">
                <label>Plantilla aprobada</label>
                <select value={tplId} onChange={(e) => onTpl(e.target.value)}>
                  <option value="">Selecciona una plantilla</option>
                  {WAD.TEMPLATES.map((t) => <option key={t.id} value={t.id}>{t.name} · {t.language} · {t.status}</option>)}
                </select>
              </div>
              {tpl && tpl.variables.map((v, i) => (
                <div key={i} className="wa3-field">
                  <label>Variable {i + 1} · {v}</label>
                  <input value={vars[i] || ""} placeholder={tpl.examples[i] || "Valor"}
                         onChange={(e) => setVars((arr) => { const n = [...arr]; n[i] = e.target.value; return n; })} />
                </div>
              ))}
            </div>
          </div>
          <div className="wa3-feedback" data-tone={fb.tone} style={{ marginTop: 12 }}>{fb.text}</div>
        </div>
        <div className="wa3-modal__foot">
          <div className="wa3-modal__sub">Esto crea o reutiliza la conversación y la deja abierta en tu inbox.</div>
          <button className="wa3-primary-btn" onClick={submit}>Iniciar con plantilla</button>
        </div>
      </div>
    </div>
  );
}

function WaFollowupModal({ onClose, onConfirm }) {
  const [reason, setReason] = useState("");
  const [fb, setFb] = useState({ tone: "", text: "" });
  const submit = () => {
    if (!reason.trim()) { setFb({ tone: "danger", text: "El motivo del cierre es obligatorio." }); return; }
    onConfirm(reason.trim());
  };
  return (
    <div className="wa3-modal" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card" style={{ width: "min(520px, 100%)" }}>
        <div className="wa3-modal__head">
          <div>
            <h3>Cerrar seguimiento</h3>
            <div className="wa3-modal__sub">No elimina al paciente ni el historial. Cierra la conversación y genera un lead de seguimiento.</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          <div className="wa3-field">
            <label>Motivo del cierre</label>
            <textarea value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: paciente no responde, retomar seguimiento comercial, pidió contacto posterior…"></textarea>
          </div>
          <div className="wa3-feedback" data-tone={fb.tone}>{fb.text}</div>
        </div>
        <div className="wa3-modal__foot">
          <button className="wa3-secondary-btn" onClick={onClose}>Cancelar</button>
          <button className="wa3-primary-btn" onClick={submit}>Cerrar seguimiento</button>
        </div>
      </div>
    </div>
  );
}

const WA_TOUR_STEPS = [
  { selector: ".wa3-inbox",          title: "Lista de conversaciones", copy: "Aquí eliges el chat a revisar. Usa las pestañas para ver pendientes, tuyas, agendadas o cerradas." },
  { selector: ".wa3-thread__actions",title: "Acciones principales",    copy: "Desde arriba puedes tomar, transferir, usar plantillas, buscar, resolver o abrir más opciones." },
  { selector: ".wa3-messages",       title: "Mensajes del paciente",   copy: "En el centro ves el historial completo. Los mensajes nuevos aparecen en esta zona." },
  { selector: ".wa3-composer",       title: "Campo para escribir",     copy: "Escribe la respuesta abajo. También puedes adjuntar, usar respuestas rápidas, emojis o audio." },
  { selector: ".wa3-drawer",         title: "Ficha del paciente",      copy: "A la derecha están los datos, notas internas, trazabilidad y acciones administrativas." },
];

function WaTourModal({ onClose }) {
  const [idx, setIdx] = useState(0);
  const steps = useMemo(() => WA_TOUR_STEPS.filter((s) => document.querySelector(s.selector)), []);
  const step = steps[idx];

  useEffect(() => {
    if (!step) return;
    const el = document.querySelector(step.selector);
    if (el) {
      el.classList.add("wa3-tour-focus");
      el.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
    }
    return () => { if (el) el.classList.remove("wa3-tour-focus"); };
  }, [step]);

  if (!step) return null;
  const last = idx === steps.length - 1;
  return (
    <div className="wa3-modal wa3-tour-modal" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card">
        <div className="wa3-modal__head">
          <div>
            <h3>Nuevo Chat de WhatsApp</h3>
            <div className="wa3-modal__sub">Una vista más clara para atender conversaciones.</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          <p>Ahora las conversaciones, los mensajes y la ficha del paciente están separados para que encuentres cada cosa más rápido.</p>
          <div className="wa3-tour-step">
            <span>Paso {idx + 1} de {steps.length}</span>
            <span className="wa3-tour-step__bar"><span style={{ width: `${((idx + 1) / steps.length) * 100}%` }}></span></span>
          </div>
          <h4 className="wa3-tour-step__title">{step.title}</h4>
          <p>{step.copy}</p>
        </div>
        <div className="wa3-modal__foot">
          <button className="wa3-secondary-btn" disabled={idx === 0} onClick={() => setIdx((i) => Math.max(0, i - 1))}>Anterior</button>
          <div style={{ display: "flex", gap: 8 }}>
            {!last && <button className="wa3-secondary-btn" onClick={() => setIdx((i) => Math.min(steps.length - 1, i + 1))}>Siguiente</button>}
            {last && <button className="wa3-primary-btn" onClick={onClose}>Entendido, explorar</button>}
          </div>
        </div>
      </div>
    </div>
  );
}
