/* ============================================================
   MedForge · WhatsApp Chat v3 — shared helpers + Inbox
   (first babel file: declares shared hooks/data for all others)
   ============================================================ */

const { useState, useRef, useEffect, useMemo, useCallback } = React;
const WAD = window.WA_DATA;

/* click-outside menu hook */
function useWaMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const onDown = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", onDown);
    return () => document.removeEventListener("mousedown", onDown);
  }, [open]);
  return [open, setOpen, ref];
}

/* lightweight WhatsApp markdown → html (*, _, ~, `) */
function waFormat(text) {
  if (!text) return "";
  let s = text
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  s = s.replace(/\*([^*\n]+)\*/g, "<strong>$1</strong>")
       .replace(/_([^_\n]+)_/g, "<em>$1</em>")
       .replace(/~([^~\n]+)~/g, "<del>$1</del>")
       .replace(/`([^`\n]+)`/g, "<code>$1</code>");
  return s;
}

/* ====================== INBOX ROWS ====================== */

function WaInboxRow({ c, isActive, onClick }) {
  const tag = WAD.BUCKET_TAG[c.bucket];
  return (
    <div className={`wa3-row${isActive ? " is-active" : ""}${c.unreadFlag ? " is-unread" : ""}`} onClick={onClick}>
      <div className="wa3-avatar" data-tone={c.tone}>
        {c.initials}
        {c.status && <span className="wa3-avatar__status" data-state={c.status}></span>}
      </div>
      <div className="wa3-row__main">
        <div className="wa3-row__name">{c.name}</div>
        <div className="wa3-row__sub">{c.wa}{c.hc ? ` · HC ${c.hc}` : " · Sin HC"}</div>
        <div className="wa3-row__preview">{c.preview}</div>
      </div>
      <div className="wa3-row__aside">
        <span className="wa3-row__time">{c.time}</span>
        {c.unread > 0
          ? <span className="wa3-row__unread">{c.unread}</span>
          : tag && <span className="wa3-row__tag" data-tone={tag.tone}>{tag.label}</span>}
      </div>
    </div>
  );
}

function WaAgentRow({ a, isActive, onClick }) {
  return (
    <div className={`wa3-agent${isActive ? " is-active" : ""}`} onClick={onClick}>
      <div className="wa3-avatar" data-tone={a.tone}>
        {a.initials}
        <span className="wa3-avatar__status" data-state={a.status === "online" ? "open" : a.status === "busy" ? "warn" : "away"}></span>
      </div>
      <div className="wa3-agent__main">
        <div className="wa3-agent__name">{a.name}{a.isMe && <span className="wa3-agent__me">tú</span>}</div>
        <div className="wa3-agent__role">{a.role}</div>
        <div className="wa3-agent__stats">
          <span><i className="mdi mdi-chat-processing-outline"></i>{a.active} activos</span>
          <span><i className="mdi mdi-check-circle-outline"></i>{a.resolved} hoy</span>
          <span><i className="mdi mdi-timer-outline"></i>{a.avgResp}</span>
        </div>
      </div>
      <div className="wa3-agent__workload" title={`${a.active} chats activos`}>
        <div className="wa3-agent__workload-bar" style={{ width: `${Math.min(100, a.active * 12)}%` }}></div>
      </div>
    </div>
  );
}

function WaSupervisorSummary({ agents }) {
  const online = agents.filter((a) => a.status === "online").length;
  const totalActive = agents.reduce((s, a) => s + a.active, 0);
  const totalResolved = agents.reduce((s, a) => s + a.resolved, 0);
  return (
    <div className="wa3-supervisor">
      <div className="wa3-supervisor__metric"><span className="k">Agentes online</span><span className="v">{online}<span className="of">/ {agents.length}</span></span></div>
      <div className="wa3-supervisor__metric"><span className="k">Chats activos</span><span className="v">{totalActive}</span></div>
      <div className="wa3-supervisor__metric"><span className="k">Resueltos hoy</span><span className="v">{totalResolved}</span></div>
    </div>
  );
}

/* ====================== INBOX HEADER MENUS ====================== */

function WaManagerMenu({ counts, onPickFilter }) {
  const [open, setOpen, ref] = useWaMenu();
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className="wa3-hbtn wa3-manager-btn" type="button" title="Vista gerencial"
              onClick={() => setOpen((v) => !v)}>
        <i className="mdi mdi-view-dashboard-outline"></i>
      </button>
      {open && (
        <div className="wa3-hbtn__menu wa3-manager-menu">
          <h6>Métricas rápidas</h6>
          <div className="wa3-metric-grid">
            {WAD.MANAGER_METRICS.map((m) => (
              <div key={m.id} className="wa3-metric" data-tone={m.tone}>
                <strong>{counts[m.id] != null ? counts[m.id] : m.value}</strong>
                <span>{m.label}</span>
              </div>
            ))}
          </div>
          <div className="wa3-menu-footer">
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter("critical_backlog"); setOpen(false); }}>Ver backlog</button>
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter("unread"); setOpen(false); }}>Sin leer</button>
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter("needs_template"); setOpen(false); }}>Plantilla</button>
          </div>
        </div>
      )}
    </div>
  );
}

function WaFilterMenu({ filter, counts, dateFrom, dateTo, onDate, onPickFilter }) {
  const [open, setOpen, ref] = useWaMenu();
  const [from, setFrom] = useState(dateFrom || "");
  const [to, setTo] = useState(dateTo || "");
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className="wa3-iconbtn" type="button" title="Filtros avanzados"
              onClick={() => setOpen((v) => !v)}>
        <i className="mdi mdi-tune-variant"></i>
      </button>
      {open && (
        <div className="wa3-hbtn__menu wa3-filter-menu">
          <h6>Rango de fechas</h6>
          <div className="wa3-filter-grid">
            <div className="wa3-field"><label>Desde</label><input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></div>
            <div className="wa3-field"><label>Hasta</label><input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></div>
          </div>
          <div className="wa3-menu-footer">
            <button className="wa3-secondary-btn" onClick={() => { setFrom(""); setTo(""); onDate("", ""); }}>Limpiar</button>
            <button onClick={() => { onDate(from, to); setOpen(false); }}>Aplicar fechas</button>
          </div>
          <h6>Bandejas avanzadas</h6>
          <div className="wa3-filter-list">
            {WAD.ADV_FILTERS.map((f) => (
              <button key={f.id} className={`wa3-filter-link${filter === f.id ? " is-active" : ""}`}
                      onClick={() => { onPickFilter(f.id); setOpen(false); }}>
                <i className={`mdi ${f.icon}`}></i>
                <span><strong>{f.label}</strong><small>{f.hint}</small></span>
                <span className="count">{counts[f.id] || 0}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ====================== CHIPS (tabs) with scroll arrows ====================== */

function WaChips({ filter, counts, onPick }) {
  const navRef = useRef(null);
  const [showL, setShowL] = useState(false);
  const [showR, setShowR] = useState(true);
  const update = useCallback(() => {
    const n = navRef.current; if (!n) return;
    setShowL(n.scrollLeft > 2);
    setShowR(n.scrollLeft + n.clientWidth < n.scrollWidth - 2);
  }, []);
  useEffect(() => { update(); }, [update]);
  const by = (dx) => navRef.current?.scrollBy({ left: dx, behavior: "smooth" });
  return (
    <div className="wa3-chips-wrap">
      <button className={`wa3-chips-arrow wa3-chips-arrow--left${showL ? "" : " is-hidden"}`} onClick={() => by(-160)} aria-label="Anterior">
        <i className="mdi mdi-chevron-left" style={{ fontSize: 18 }}></i>
      </button>
      <nav className="wa3-chips" ref={navRef} onScroll={update}>
        {WAD.TABS.map((t) => (
          <button key={t.id} className={`wa3-chip${filter === t.id ? " is-active" : ""}`} onClick={() => onPick(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}<span className="wa3-chip__count">{counts[t.id] || 0}</span>
          </button>
        ))}
      </nav>
      <button className={`wa3-chips-arrow wa3-chips-arrow--right${showR ? "" : " is-hidden"}`} onClick={() => by(160)} aria-label="Siguiente">
        <i className="mdi mdi-chevron-right" style={{ fontSize: 18 }}></i>
      </button>
    </div>
  );
}

/* ====================== INBOX ====================== */

function WaInbox({
  activeId, filter, search, view, canSupervise, counts, dateFrom, dateTo, agentFilter,
  visible, onPickConvo, onFilter, onSearch, onView, onDate, onAgentFilter,
  onNewConvo, onRequeue,
}) {
  return (
    <aside className="wa3-inbox">
      <div className="wa3-inbox__head">
        <div className="wa3-inbox__title-row">
          {canSupervise ? (
            <div className="wa3-inbox__tabs">
              <button className={`wa3-inbox__tab${view === "conversations" ? " is-active" : ""}`} onClick={() => onView("conversations")}>
                <i className="mdi mdi-message-text-outline"></i>Chats
              </button>
              <button className={`wa3-inbox__tab${view === "agents" ? " is-active" : ""}`} onClick={() => onView("agents")}>
                <i className="mdi mdi-account-group-outline"></i>Agentes
              </button>
            </div>
          ) : (
            <h2 className="wa3-inbox__title">Conversaciones</h2>
          )}
          <div style={{ display: "flex", gap: 2, alignItems: "center" }}>
            {canSupervise && <WaManagerMenu counts={counts} onPickFilter={onFilter} />}
            {canSupervise && (
              <button className="wa3-iconbtn" title="Reencolar handoffs vencidos" onClick={onRequeue}>
                <i className="mdi mdi-restore-alert"></i>
              </button>
            )}
            <button className="wa3-iconbtn" title="Nueva conversación" onClick={onNewConvo}><i className="mdi mdi-plus"></i></button>
            <WaFilterMenu filter={filter} counts={counts} dateFrom={dateFrom} dateTo={dateTo} onDate={onDate} onPickFilter={onFilter} />
          </div>
        </div>
        <div className="wa3-search">
          <i className="mdi mdi-magnify"></i>
          <input value={search} onChange={(e) => onSearch(e.target.value)}
                 placeholder={view === "agents" ? "Buscar agente…" : "Buscar nombre, número o HC…"} />
        </div>
      </div>

      {view === "conversations" && (
        <>
          <WaChips filter={filter} counts={counts} onPick={onFilter} />
          {agentFilter && (
            <div className="wa3-agentfilter">
              <i className="mdi mdi-account-filter-outline"></i>
              <span>Filtrado por <strong>{agentFilter.name}</strong></span>
              <button onClick={() => onAgentFilter(null)} title="Quitar filtro"><i className="mdi mdi-close"></i></button>
            </div>
          )}
          <div className="wa3-list">
            {visible.map((c) => (
              <WaInboxRow key={c.id} c={c} isActive={c.id === activeId} onClick={() => onPickConvo(c.id)} />
            ))}
            {visible.length === 0 && (
              <div style={{ padding: 28, textAlign: "center", color: "var(--wa3-text-mute)", font: "400 13px var(--font-body)" }}>
                Sin conversaciones en esta bandeja{search ? ` para "${search}"` : ""}.
              </div>
            )}
          </div>
        </>
      )}

      {view === "agents" && (
        <>
          <WaSupervisorSummary agents={WAD.AGENTS} />
          <div className="wa3-list">
            {WAD.AGENTS.filter((a) => !search.trim() || a.name.toLowerCase().includes(search.toLowerCase())).map((a) => (
              <WaAgentRow key={a.id} a={a} isActive={agentFilter?.id === a.id}
                          onClick={() => { onAgentFilter(a); onView("conversations"); }} />
            ))}
          </div>
        </>
      )}
    </aside>
  );
}
