/* ============================================================
   MedForge · Solicitudes v2 — app root
   ============================================================ */
const { useState, useMemo, useCallback, useEffect } = React;
const D = window.MEDF_DATA;
const CURRENT_USER = { name: "M. Quishpe", role: "Coordinación quirúrgica", responsable: "Coord. M. Quishpe" };

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "direction": "a",
  "density": "comodo",
  "afilColor": true,
  "groupPhases": true,
  "showDoctorAvatar": true,
  "accent": "#5156be"
}/*EDITMODE-END*/;

// recompute checklist + progress when a card changes column
function rebuildState(sol, newSlug) {
  const colIdx = D.COLUMNS.findIndex((c) => c.slug === newSlug);
  const col = D.COLUMNS[colIdx];
  const checklist = sol.checklist.map((step) => {
    const stepColIdx = D.COLUMNS.findIndex((c) => c.slug === step.slug);
    return { ...step, completed: stepColIdx <= colIdx, can_toggle: true };
  });
  const completed = checklist.filter((s) => s.completed).length;
  const total = checklist.length;
  const firstPending = checklist.find((s) => !s.completed);
  const sla = newSlug === "completado"
    ? { sla_status: "ok", sla_hours_remaining: null, sla_label: "Cerrada" }
    : { sla_status: sol.sla_status, sla_hours_remaining: sol.sla_hours_remaining, sla_label: sol.sla_label };
  return {
    ...sol, ...sla,
    estado: newSlug, estado_label: col.label,
    turno: (newSlug === "recibida" || newSlug === "llamado") ? sol.turno : null,
    checklist,
    checklist_progress: {
      completed, total,
      percent: Math.round((completed / total) * 100),
      next_label: firstPending ? firstPending.label : "Completado",
    },
  };
}

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [solicitudes, setSolicitudes] = useState(() => D.solicitudes.map((s) => ({ ...s })));
  const [filters, setFilters] = useState({ search: "", afiliacion: "", doctor: "" });
  const [preset, setPreset] = useState("");
  const [kpiFilter, setKpiFilter] = useState("");
  const [view, setView] = useState("kanban");
  const [selectedId, setSelectedId] = useState(null);
  const [prefacturaId, setPrefacturaId] = useState(null);
  const [draggingId, setDraggingId] = useState(null);
  const [dropTarget, setDropTarget] = useState(null);
  const [toast, setToast] = useState(null);

  // apply accent color
  useEffect(() => {
    document.documentElement.style.setProperty("--accent", t.accent || "#5156be");
  }, [t.accent]);

  const showToast = useCallback((msg, icon = "mdi-check-circle") => {
    setToast({ msg, icon });
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => setToast(null), 2600);
  }, []);

  // ---- filtering ----
  const filtered = useMemo(() => {
    const q = filters.search.trim().toLowerCase();
    return solicitudes.filter((s) => {
      if (q && !(`${s.full_name} ${s.hc_number} ${s.procedimiento} ${s.procedimiento_short} ${s.form_id}`.toLowerCase().includes(q))) return false;
      if (filters.afiliacion && s.afiliacion !== filters.afiliacion) return false;
      if (filters.doctor && s.doctor !== filters.doctor) return false;
      if (preset === "mis-casos" && s.crm.responsable !== CURRENT_USER.responsable) return false;
      if (preset === "urgentes" && !(s.prioridad === "urgente" || s.sla_status === "vencido" || s.sla_status === "critico")) return false;
      if (kpiFilter === "vencido" && s.sla_status !== "vencido") return false;
      if (kpiFilter === "critico" && s.sla_status !== "critico") return false;
      if (kpiFilter === "docs" && !s.alerts.some((a) => a.key === "docs")) return false;
      if (kpiFilter === "auth" && !s.alerts.some((a) => a.key === "auth")) return false;
      return true;
    });
  }, [solicitudes, filters, preset, kpiFilter]);

  const byColumn = useMemo(() => {
    const m = {};
    D.COLUMNS.forEach((c) => (m[c.slug] = []));
    filtered.forEach((s) => { (m[s.estado] = m[s.estado] || []).push(s); });
    return m;
  }, [filtered]);

  // ---- metrics (over all, not filtered) ----
  const metrics = useMemo(() => {
    const open = solicitudes.filter((s) => s.estado !== "completado");
    return {
      total: solicitudes.length,
      vencido: solicitudes.filter((s) => s.sla_status === "vencido").length,
      critico: solicitudes.filter((s) => s.sla_status === "critico").length,
      docs: solicitudes.filter((s) => s.alerts.some((a) => a.key === "docs")).length,
      auth: solicitudes.filter((s) => s.alerts.some((a) => a.key === "auth")).length,
    };
  }, [solicitudes]);

  // ---- actions ----
  const advance = useCallback((id) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id) return s;
      const idx = D.COLUMNS.findIndex((c) => c.slug === s.estado);
      if (idx >= D.COLUMNS.length - 1) return s;
      const next = D.COLUMNS[idx + 1].slug;
      showToast(`${s.full_name.split(" ")[0]} → ${D.COLUMNS[idx + 1].label}`);
      return rebuildState(s, next);
    }));
  }, [showToast]);

  const moveTo = useCallback((id, slug) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id || s.estado === slug) return s;
      const col = D.COLUMNS.find((c) => c.slug === slug);
      showToast(`${s.full_name.split(" ")[0]} movido a ${col.label}`);
      return rebuildState(s, slug);
    }));
  }, [showToast]);

  const toggleStep = useCallback((id, slug) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id) return s;
      const checklist = s.checklist.map((st) => st.slug === slug ? { ...st, completed: !st.completed } : st);
      const completed = checklist.filter((x) => x.completed).length;
      const total = checklist.length;
      const firstPending = checklist.find((x) => !x.completed);
      return { ...s, checklist, checklist_progress: { completed, total, percent: Math.round((completed / total) * 100), next_label: firstPending ? firstPending.label : "Completado" } };
    }));
  }, []);

  const confirmConcil = useCallback((id) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id || !s.protocolo_posterior_compatible) return s;
      const confirmado = { ...s.protocolo_posterior_compatible, confirmado_at: new Date().toISOString(), confirmado_by: CURRENT_USER.responsable };
      showToast(`Cirugía de ${s.full_name.split(" ")[0]} confirmada · #${confirmado.form_id}`, "mdi-check-decagram");
      return { ...rebuildState(s, "completado"), protocolo_confirmado: confirmado, detalle: s.detalle };
    }));
  }, [showToast]);

  // ---- CRM / expediente edits ----
  const patchDetalle = useCallback((id, fn) => {
    setSolicitudes((list) => list.map((s) => s.id === id ? { ...s, detalle: { ...s.detalle, ...fn(s) } } : s));
  }, []);

  const addNote = useCallback((id, txt) => {
    patchDetalle(id, (s) => ({ notas: [{ txt, by: CURRENT_USER.responsable, at: new Date().toISOString() }, ...s.detalle.notas] }));
    setSolicitudes((list) => list.map((s) => s.id === id ? { ...s, crm: { ...s.crm, notas: s.crm.notas + 1 } } : s));
    showToast("Nota guardada", "mdi-comment-check-outline");
  }, [patchDetalle, showToast]);

  const addTask = useCallback((id, task) => {
    patchDetalle(id, (s) => ({ tareas: [...s.detalle.tareas, task] }));
    setSolicitudes((list) => list.map((s) => s.id === id ? { ...s, crm: { ...s.crm, tareas_total: s.crm.tareas_total + 1, tareas_pendientes: s.crm.tareas_pendientes + 1 } } : s));
    showToast("Tarea añadida", "mdi-playlist-check");
  }, [patchDetalle, showToast]);

  const toggleTask = useCallback((id, idx) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id) return s;
      const tareas = s.detalle.tareas.map((t, i) => i === idx ? { ...t, done: !t.done } : t);
      const pend = tareas.filter((t) => !t.done).length;
      return { ...s, detalle: { ...s.detalle, tareas }, crm: { ...s.crm, tareas_pendientes: pend } };
    }));
  }, []);

  const togglePreop = useCallback((id, idx) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id) return s;
      const preop = s.detalle.preop.map((p, i) => i === idx ? { ...p, done: !p.done } : p);
      return { ...s, detalle: { ...s.detalle, preop } };
    }));
  }, []);

  const addProposal = useCallback((id) => {
    setSolicitudes((list) => list.map((s) => {
      if (s.id !== id) return s;
      const items = [
        { cod: "DQX-001", desc: "Derecho de quirófano", cant: 1, valor: 320 },
        { cod: "HON-014", desc: "Honorarios cirujano oftalmólogo", cant: 1, valor: 480 },
        { cod: "ANE-007", desc: "Anestesia y honorarios", cant: 1, valor: 180 },
      ];
      const subtotal = items.reduce((a, it) => a + it.cant * it.valor, 0);
      const iva = Math.round(subtotal * 0.15 * 100) / 100;
      const prop = { titulo: "Paquete quirúrgico — " + s.procedimiento_short, estado: "Borrador", vigencia: new Date(Date.now() + 20 * 86400000).toISOString(), items, subtotal, iva, total: subtotal + iva };
      return { ...s, detalle: { ...s.detalle, propuestas: [...s.detalle.propuestas, prop] } };
    }));
    showToast("Borrador de propuesta creado", "mdi-file-document-plus-outline");
  }, [showToast]);
  const dnd = useMemo(() => ({
    draggingId, dropTarget,
    onDragStart: (e, sol) => { setDraggingId(sol.id); e.dataTransfer.effectAllowed = "move"; try { e.dataTransfer.setData("text/plain", String(sol.id)); } catch (_) {} },
    onDragEnd: () => { setDraggingId(null); setDropTarget(null); },
    onDragOver: (e, slug) => { e.preventDefault(); e.dataTransfer.dropEffect = "move"; if (dropTarget !== slug) setDropTarget(slug); },
    onDragLeave: () => {},
    onDrop: (e, slug) => { e.preventDefault(); if (draggingId != null) moveTo(draggingId, slug); setDraggingId(null); setDropTarget(null); },
  }), [draggingId, dropTarget, moveTo]);

  const selected = useMemo(() => solicitudes.find((s) => s.id === selectedId) || null, [solicitudes, selectedId]);

  const toggleKpi = (k) => setKpiFilter((cur) => (cur === k ? "" : k));

  const shellClass = [
    "app-shell",
    `dir-${t.direction}`,
    t.density === "compacto" ? "density-compact" : "",
    t.groupPhases ? "" : "flat-phases",
    t.showDoctorAvatar ? "" : "no-doc-avatar",
    t.afilColor ? "" : "no-afil-color",
  ].filter(Boolean).join(" ");

  return (
    <div className={shellClass}>
      <header className="app-topbar">
        <div className="app-brand">
          <span className="app-brand-mark"><i className="mdi mdi-flash"></i></span>
          <div className="app-title">
            <h1>Solicitudes quirúrgicas</h1>
            <span className="crumb">MedForge · Coordinación · /v2/solicitudes</span>
          </div>
        </div>
        <div className="topbar-spacer"></div>
        <div className="dir-switch" role="group" aria-label="Dirección visual">
          <span className="ds-label">Dirección</span>
          {[{ v: "a", l: "Clínico" }, { v: "b", l: "Aireado" }, { v: "c", l: "Denso" }].map((o) => (
            <button
              key={o.v}
              className={t.direction === o.v ? "is-active" : ""}
              onClick={() => setTweak("direction", o.v)}
              title={`Dirección visual: ${o.l}`}
            >{o.l}</button>
          ))}
        </div>
        <div className="topbar-actions">
          <button className="icon-btn" title="Notificaciones"><i className="mdi mdi-bell-outline"></i><span className="dot"></span></button>
          <button className="icon-btn" title="Turnero"><i className="mdi mdi-monitor-dashboard"></i></button>
          <div className="user-chip">
            <span className="av">{CURRENT_USER.name.split(" ").map(w=>w[0]).join("")}</span>
            <span className="meta"><b>{CURRENT_USER.name}</b><span>{CURRENT_USER.role}</span></span>
          </div>
        </div>
      </header>

      <div className="kpi-row">
        <Kpi tone="total" icon="mdi-clipboard-text-multiple-outline" value={metrics.total} label="Solicitudes totales" active={kpiFilter === ""} onClick={() => setKpiFilter("")} />
        <Kpi tone="vencido" icon="mdi-alert-octagon-outline" value={metrics.vencido} label="SLA vencido" active={kpiFilter === "vencido"} onClick={() => toggleKpi("vencido")} />
        <Kpi tone="critico" icon="mdi-clock-alert-outline" value={metrics.critico} label="SLA crítico" active={kpiFilter === "critico"} onClick={() => toggleKpi("critico")} />
        <Kpi tone="docs" icon="mdi-file-alert-outline" value={metrics.docs} label="Docs faltantes" active={kpiFilter === "docs"} onClick={() => toggleKpi("docs")} />
        <Kpi tone="auth" icon="mdi-shield-clock-outline" value={metrics.auth} label="Autorización pendiente" active={kpiFilter === "auth"} onClick={() => toggleKpi("auth")} />
      </div>

      <Toolbar
        filters={filters} setFilters={setFilters}
        preset={preset} setPreset={setPreset}
        view={view} setView={setView}
        doctors={D.DOCTORS} afiliaciones={D.AFILIACIONES}
      />

      {view === "kanban"
        ? <Board columns={D.COLUMNS} phases={D.PHASES} byColumn={byColumn} onOpen={setSelectedId} onAdvance={advance} dnd={dnd} groupPhases={t.groupPhases} />
        : view === "tabla"
        ? <TableView rows={filtered} onOpen={setSelectedId} />
        : <ConciliacionView rows={filtered} onConfirm={confirmConcil} />}

      <DetailPanel
        sol={selected} open={selectedId != null} onClose={() => setSelectedId(null)}
        onToggleStep={toggleStep} onAdvance={advance}
        onToggleTask={toggleTask} onAddTask={addTask} onAddNote={addNote}
        onAddProposal={addProposal} onOpenPrefactura={(id) => setPrefacturaId(id)}
        showToast={showToast}
      />

      <PrefacturaModal
        sol={solicitudes.find((s) => s.id === prefacturaId) || null}
        open={prefacturaId != null} onClose={() => setPrefacturaId(null)}
        onTogglePreop={togglePreop} showToast={showToast}
      />

      {toast && <div className="toast-wrap"><div className="toast ok"><i className={`mdi ${toast.icon}`}></i>{toast.msg}</div></div>}

      <TweaksPanel title="Tweaks">
        <TweakSection label="Dirección visual" />
        <TweakRadio label="Estilo" value={t.direction}
          options={[{ value: "a", label: "Clínico" }, { value: "b", label: "Aireado" }, { value: "c", label: "Denso" }]}
          onChange={(v) => setTweak("direction", v)} />
        <TweakSection label="Disposición" />
        <TweakRadio label="Densidad" value={t.density}
          options={[{ value: "comodo", label: "Cómodo" }, { value: "compacto", label: "Compacto" }]}
          onChange={(v) => setTweak("density", v)} />
        <TweakToggle label="Agrupar por fase" value={t.groupPhases} onChange={(v) => setTweak("groupPhases", v)} />
        <TweakToggle label="Color por afiliación" value={t.afilColor} onChange={(v) => setTweak("afilColor", v)} />
        <TweakToggle label="Avatar del doctor" value={t.showDoctorAvatar} onChange={(v) => setTweak("showDoctorAvatar", v)} />
        <TweakSection label="Marca" />
        <TweakColor label="Acento" value={t.accent}
          options={["#5156be", "#3596f7", "#1f9d7a", "#7C4DFF", "#0c6fb0"]}
          onChange={(v) => setTweak("accent", v)} />
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
