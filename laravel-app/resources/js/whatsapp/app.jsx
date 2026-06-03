/* ============================================================
   MedForge · WhatsApp Chat v3 — app root
   Isolated React prototype. All V3 features wired as interactions.
   ============================================================ */

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "accent": "#5156be",
  "density": "comodo",
  "supervise": true,
  "showDrawer": true
}/*EDITMODE-END*/;

const WA_ME = { name: "Dra. Carolina Rivera", short: "tú" };

function genThread(convo) {
  return [
    { kind: "date", text: "Hoy" },
    { kind: "msg", dir: "in", body: convo.preview.replace(/^Tú: /, ""), time: convo.time },
  ];
}

function WaApp() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [convos, setConvos] = useState(() => WAD.CONVOS.map((c) => ({ ...c })));
  const [activeId, setActiveId] = useState(1);
  const [filter, setFilter] = useState("mine");
  const [search, setSearch] = useState("");
  const [view, setView] = useState("conversations");
  const [agentFilter, setAgentFilter] = useState(null);
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [threads, setThreads] = useState(() => ({ 1: WAD.THREAD.map((m) => ({ ...m })) }));
  const [notesByConvo, setNotesByConvo] = useState(() => ({ 1: WAD.NOTES.map((n) => ({ ...n })) }));
  const [quickReplies, setQuickReplies] = useState(() => WAD.QUICK_REPLIES.slice());
  const [draft, setDraft] = useState("");
  const [typing, setTyping] = useState(false);
  const [realtime, setRealtime] = useState(false);
  const [modal, setModal] = useState(null); // 'new' | 'followup' | 'tour'
  const [toastMsg, setToastState] = useState(null);

  const showDrawer = t.showDrawer;
  const canSupervise = t.supervise;
  const canOperate = true;

  const convo = useMemo(() => convos.find((c) => c.id === activeId) || null, [convos, activeId]);
  const thread = threads[activeId] || (convo ? genThread(convo) : []);
  const notes = notesByConvo[activeId] || [];

  const notify = useCallback((msg, icon = "mdi-check-circle") => {
    setToastState({ msg, icon });
    clearTimeout(notify._t);
    notify._t = setTimeout(() => setToastState(null), 2600);
  }, []);

  // apply accent + derived tokens
  const rootStyle = useMemo(() => ({
    "--wa3-accent": t.accent,
    "--wa3-accent-soft": `color-mix(in srgb, ${t.accent} 12%, white)`,
    "--wa3-bubble-out": `color-mix(in srgb, ${t.accent} 14%, white)`,
  }), [t.accent]);

  // first-visit tour
  useEffect(() => {
    let seen = false;
    try { seen = localStorage.getItem("medforge_chat_tour_visto") === "1"; } catch (_) {}
    if (!seen) { const id = setTimeout(() => setModal("tour"), 450); return () => clearTimeout(id); }
  }, []);

  // demo realtime banner — surfaces once a few seconds in
  useEffect(() => {
    const id = setTimeout(() => setRealtime(true), 9000);
    return () => clearTimeout(id);
  }, [activeId]);

  /* ---- counts derived from buckets ---- */
  const counts = useMemo(() => {
    const acc = {};
    [...WAD.TABS, ...WAD.ADV_FILTERS].forEach((b) => { acc[b.id] = 0; });
    convos.forEach((c) => (c.buckets || []).forEach((b) => { acc[b] = (acc[b] || 0) + 1; }));
    return acc;
  }, [convos]);

  /* ---- visible list ---- */
  const visible = useMemo(() => {
    const q = search.trim().toLowerCase();
    return convos.filter((c) => {
      if (q && !(`${c.name} ${c.wa} ${c.hc || ""} ${c.preview}`.toLowerCase().includes(q))) return false;
      if (agentFilter && c.assignedTo !== agentFilter.name) return false;
      return (c.buckets || []).includes(filter);
    });
  }, [convos, filter, search, agentFilter]);

  const pickConvo = useCallback((id) => {
    setActiveId(id);
    setThreads((prev) => prev[id] ? prev : { ...prev, [id]: genThread(convos.find((c) => c.id === id)) });
    setDraft("");
    setRealtime(false);
  }, [convos]);

  /* ---- composer send ---- */
  const onSend = useCallback(() => {
    const text = draft.trim();
    if (!text || !convo) return;
    const time = new Date().toTimeString().slice(0, 5);
    setThreads((prev) => ({ ...prev, [activeId]: [...(prev[activeId] || genThread(convo)), { kind: "msg", dir: "out", body: text, time, status: "sent" }] }));
    setDraft("");
    const mark = (status, ms) => setTimeout(() => setThreads((prev) => {
      const arr = prev[activeId] || []; if (!arr.length) return prev;
      const copy = arr.slice(); copy[copy.length - 1] = { ...copy[copy.length - 1], status };
      return { ...prev, [activeId]: copy };
    }), ms);
    mark("delivered", 600); mark("read", 1300);
    setTimeout(() => setTyping(true), 1600);
    setTimeout(() => {
      setTyping(false);
      setThreads((prev) => ({ ...prev, [activeId]: [...(prev[activeId] || []), { kind: "msg", dir: "in", body: "Recibido, muchas gracias doctora 🙌", time: new Date().toTimeString().slice(0, 5) }] }));
    }, 3200);
  }, [draft, convo, activeId]);

  /* ---- conversation actions ---- */
  const patchConvo = (id, patch) => setConvos((list) => list.map((c) => c.id === id ? { ...c, ...patch } : c));

  const handlers = useMemo(() => ({
    onSend,
    onToggleDrawer: () => setTweak("showDrawer", !showDrawer),
    onAssignSelf: () => {
      patchConvo(activeId, { isMine: true, assignedTo: WA_ME.name, assignedRole: "Oftalmología", opStatus: "En gestión", buckets: Array.from(new Set([...(convo.buckets || []).filter((b) => b !== "requires_attention"), "mine", "in_progress"])) });
      notify("Conversación asignada a ti", "mdi-hand-back-right");
    },
    onTransfer: (agent) => { patchConvo(activeId, { assignedTo: agent.name, isMine: agent.isMe || false, assignedRole: agent.role }); notify(`Transferida a ${agent.name}`, "mdi-account-arrow-right"); },
    onQueueRole: (role) => { patchConvo(activeId, { assignedRole: role.name, assignedTo: null, isMine: false }); notify(`Derivada al equipo de ${role.name}`, "mdi-account-multiple"); },
    onResolve: () => {
      patchConvo(activeId, { opStatus: "Cerrada", status: null, window: "template", buckets: ["closed"] });
      notify("Conversación resuelta", "mdi-check-decagram");
      setFilter("closed");
    },
    onApplyTemplate: (tpl) => {
      const filled = tpl.body.replace(/\{\{\s*(\d+)\s*\}\}/g, (mm, i) => tpl.examples[Number(i) - 1] || mm);
      setDraft(filled);
      notify(`Plantilla "${tpl.name}" cargada`, "mdi-file-document");
    },
    onCopy: (value, label) => {
      const text = value.startsWith("/") ? `${window.location.origin}${value}` : value;
      try { navigator.clipboard?.writeText(text); } catch (_) {}
      notify(`${label} copiado`, "mdi-content-copy");
    },
    onOpenTrail: () => {
      if (!showDrawer) setTweak("showDrawer", true);
      setTimeout(() => document.getElementById("wa3-trail-section")?.scrollIntoView({ behavior: "smooth", block: "center" }), 120);
    },
    onFollowup: () => setModal("followup"),
    onRealtimeReload: () => { setRealtime(false); notify("Conversación actualizada", "mdi-refresh"); },
  }), [onSend, activeId, convo, showDrawer, setTweak, notify]);

  const addNote = useCallback((body) => {
    setNotesByConvo((prev) => ({ ...prev, [activeId]: [{ author: WA_ME.name, at: "ahora", body }, ...(prev[activeId] || [])] }));
    notify("Nota interna guardada", "mdi-note-check-outline");
  }, [activeId, notify]);

  const createQuickReply = useCallback((title, body) => {
    setQuickReplies((prev) => [...prev, { id: Date.now(), title, body }]);
    notify("Respuesta rápida creada", "mdi-lightning-bolt");
  }, [notify]);

  const closeTour = () => { try { localStorage.setItem("medforge_chat_tour_visto", "1"); } catch (_) {} setModal(null); };

  const shellClass = ["wa3", showDrawer ? "has-drawer" : "", t.density === "compacto" ? "is-compact" : ""].filter(Boolean).join(" ");

  return (
    <div className={shellClass} id="wa3-root" style={rootStyle}>
      <WaInbox
        activeId={activeId} filter={filter} search={search} view={view}
        canSupervise={canSupervise} counts={counts} dateFrom={dateFrom} dateTo={dateTo}
        agentFilter={agentFilter} visible={visible}
        onPickConvo={pickConvo} onFilter={setFilter} onSearch={setSearch} onView={setView}
        onDate={(f, to) => { setDateFrom(f); setDateTo(to); if (f || to) notify("Rango de fechas aplicado", "mdi-calendar-range"); }}
        onAgentFilter={setAgentFilter}
        onNewConvo={() => setModal("new")}
        onRequeue={() => notify("Handoffs vencidos reencolados", "mdi-restore-alert")}
      />

      <WaThreadPane
        convo={convo} thread={thread} typing={typing} draft={draft} setDraft={setDraft}
        showDrawer={showDrawer} canSupervise={canSupervise} canOperate={canOperate}
        realtime={realtime} handlers={handlers} toast={notify}
      />

      {showDrawer && (
        <WaDrawer convo={convo} notes={notes} trail={WAD.TRAIL} canOperate={canOperate}
                  onAddNote={addNote} onCreateQuickReply={createQuickReply} onFollowup={() => setModal("followup")} />
      )}

      {modal === "new" && <WaNewConvoModal onClose={() => setModal(null)} toast={notify} />}
      {modal === "followup" && <WaFollowupModal onClose={() => setModal(null)} onConfirm={(reason) => { handlers.onResolve(); setModal(null); notify("Seguimiento cerrado · lead generado", "mdi-archive-check"); }} />}
      {modal === "tour" && <WaTourModal onClose={closeTour} />}

      {toastMsg && <div className="wa3-toast-wrap"><div className="wa3-toast"><i className={`mdi ${toastMsg.icon}`}></i>{toastMsg.msg}</div></div>}

      <TweaksPanel title="Tweaks">
        <TweakSection label="Marca" />
        <TweakColor label="Acento" value={t.accent}
          options={["#5156be", "#1f9d7a", "#0c6fb0", "#7C4DFF", "#c0392b"]}
          onChange={(v) => setTweak("accent", v)} />
        <TweakSection label="Disposición" />
        <TweakRadio label="Densidad" value={t.density}
          options={[{ value: "comodo", label: "Cómodo" }, { value: "compacto", label: "Compacto" }]}
          onChange={(v) => setTweak("density", v)} />
        <TweakToggle label="Mostrar ficha (drawer)" value={t.showDrawer} onChange={(v) => setTweak("showDrawer", v)} />
        <TweakSection label="Rol" />
        <TweakToggle label="Vista supervisor" value={t.supervise} onChange={(v) => setTweak("supervise", v)} />
        <TweakButton label="Ver tour de bienvenida" onClick={() => setModal("tour")} />
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<WaApp />);
