/* MedForge · WhatsApp Chat v3 — Inbox panel (ES module)
   Shared hooks + WaInbox + WaChips + filter menus + agent view */

import React, { useState, useRef, useEffect, useMemo, useCallback } from 'react';

// ── Static data ───────────────────────────────────────────────────────────────

export const TABS = [
  { id: 'requires_attention', label: 'Atención',    icon: 'mdi-alert-circle-outline' },
  { id: 'mine',               label: 'Mías',        icon: 'mdi-account-check-outline' },
  { id: 'in_progress',        label: 'En gestión',  icon: 'mdi-account-clock-outline' },
  { id: 'waiting_patient',    label: 'Esperando',   icon: 'mdi-account-arrow-left-outline' },
  { id: 'scheduled',          label: 'Agendados',   icon: 'mdi-calendar-check-outline' },
  { id: 'closed',             label: 'Cerrados',    icon: 'mdi-archive-check-outline' },
];

export const ADV_FILTERS = [
  { id: 'critical_backlog', label: 'Backlog >24h',       icon: 'mdi-alert-octagon-outline',      hint: 'Casos vencidos o sin atención oportuna.' },
  { id: 'captacion',        label: 'Captación',          icon: 'mdi-bullseye-arrow',             hint: 'Pacientes nuevos o intención de agendar.' },
  { id: 'operacion',        label: 'Operación',          icon: 'mdi-calendar-sync-outline',      hint: 'Citas vigentes, cambios o seguimiento.' },
  { id: 'informacion',      label: 'Información',        icon: 'mdi-information-outline',        hint: 'Consultas generales sin proceso activo.' },
  { id: 'unread',           label: 'Sin leer',           icon: 'mdi-bell-outline',               hint: 'Mensajes entrantes pendientes de revisión.' },
  { id: 'window_open',      label: '24h abierta',        icon: 'mdi-timer-sand',                 hint: 'Chats donde se puede responder libremente.' },
  { id: 'needs_template',   label: 'Requiere plantilla', icon: 'mdi-file-document-edit-outline', hint: 'Ventana vencida; requiere plantilla aprobada.' },
];

export const BUCKET_TAG = {
  requires_attention: { tone: 'attention', label: 'Atención' },
  mine:               { tone: 'mine',      label: 'Mía' },
  in_progress:        { tone: 'progress',  label: 'Gestión' },
  waiting_patient:    { tone: 'waiting',   label: 'Esperando' },
  scheduled:          { tone: 'scheduled', label: 'Agendada' },
  closed:             { tone: 'closed',    label: 'Cerrada' },
};

// ── Shared hook ───────────────────────────────────────────────────────────────

export function useWaMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    if (!open) return;
    const onDown = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', onDown);
    return () => document.removeEventListener('mousedown', onDown);
  }, [open]);
  return [open, setOpen, ref];
}

// ── Inbox rows ────────────────────────────────────────────────────────────────

function WaInboxRow({ c, isActive, onClick }) {
  const tag = BUCKET_TAG[c.bucket];
  return (
    <div className={`wa3-row${isActive ? ' is-active' : ''}${c.unreadFlag ? ' is-unread' : ''}`} onClick={onClick}>
      <div className="wa3-avatar" data-tone={c.tone}>{c.initials}
        {c.status && <span className="wa3-avatar__status" data-state={c.status}></span>}
      </div>
      <div className="wa3-row__main">
        <div className="wa3-row__name">{c.name}</div>
        <div className="wa3-row__sub">{c.wa}{c.hc ? ` · HC ${c.hc}` : ' · Sin HC'}</div>
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
    <div className={`wa3-agent${isActive ? ' is-active' : ''}`} onClick={onClick}>
      <div className="wa3-avatar" data-tone={a.tone}>{a.initials}
        <span className="wa3-avatar__status" data-state={a.status === 'online' ? 'open' : a.status === 'busy' ? 'warn' : 'away'}></span>
      </div>
      <div className="wa3-agent__main">
        <div className="wa3-agent__name">{a.name}{a.isMe && <span className="wa3-agent__me">tú</span>}</div>
        <div className="wa3-agent__role">{a.role}</div>
        <div className="wa3-agent__stats">
          <span><i className="mdi mdi-chat-processing-outline"></i>{a.active} activos</span>
          <span style={a.resolved > 0 ? { color: 'var(--wa3-danger)', fontWeight: 700 } : {}}>
            <i className="mdi mdi-message-alert-outline"></i>{a.resolved} sin leer
          </span>
          {a.avgResp && a.avgResp !== '—' && <span><i className="mdi mdi-timer-outline"></i>{a.avgResp}</span>}
        </div>
      </div>
      <div className="wa3-agent__workload" title={`${a.active} chats activos`}>
        <div className="wa3-agent__workload-bar" style={{ width: `${Math.min(100, a.active * 12)}%` }}></div>
      </div>
    </div>
  );
}

function WaSupervisorSummary({ agents }) {
  const online = agents.filter(a => a.status === 'online').length;
  const totalActive = agents.reduce((s, a) => s + (a.active || 0), 0);
  const totalResolved = agents.reduce((s, a) => s + (a.resolved || 0), 0);
  return (
    <div className="wa3-supervisor">
      <div className="wa3-supervisor__metric"><span className="k">Agentes online</span><span className="v">{online}<span className="of">/ {agents.length}</span></span></div>
      <div className="wa3-supervisor__metric"><span className="k">Chats activos</span><span className="v">{totalActive}</span></div>
      <div className="wa3-supervisor__metric"><span className="k">Sin leer</span><span className="v">{totalResolved}</span></div>
    </div>
  );
}

// ── Header menus ──────────────────────────────────────────────────────────────

function WaManagerMenu({ tabCounts, onPickFilter }) {
  const [open, setOpen, ref] = useWaMenu();
  const metrics = [
    { id: 'critical_backlog',   label: 'SLA / backlog', tone: 'danger'  },
    { id: 'requires_attention', label: 'Atención',       tone: 'danger'  },
    { id: 'unread',             label: 'Sin leer',       tone: 'accent'  },
    { id: 'waiting_patient',    label: 'Esperando',      tone: 'warning' },
    { id: 'needs_template',     label: 'Plantilla',      tone: 'muted'   },
    { id: 'scheduled',          label: 'Agendados',      tone: 'success' },
  ];
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className="wa3-hbtn wa3-manager-btn" type="button" title="Vista gerencial" onClick={() => setOpen(v => !v)}>
        <i className="mdi mdi-view-dashboard-outline"></i>
      </button>
      {open && (
        <div className="wa3-hbtn__menu wa3-manager-menu">
          <h6>Métricas rápidas</h6>
          <div className="wa3-metric-grid">
            {metrics.map(m => (
              <div key={m.id} className="wa3-metric" data-tone={m.tone}>
                <strong>{tabCounts[m.id] ?? 0}</strong>
                <span>{m.label}</span>
              </div>
            ))}
          </div>
          <div className="wa3-menu-footer">
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter('critical_backlog'); setOpen(false); }}>Ver backlog</button>
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter('unread'); setOpen(false); }}>Sin leer</button>
            <button className="wa3-secondary-btn" onClick={() => { onPickFilter('needs_template'); setOpen(false); }}>Plantilla</button>
          </div>
        </div>
      )}
    </div>
  );
}

function WaFilterMenu({ filter, tabCounts, dateFrom, dateTo, onDate, onPickFilter }) {
  const [open, setOpen, ref] = useWaMenu();
  const [from, setFrom] = useState(dateFrom || '');
  const [to, setTo] = useState(dateTo || '');
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className="wa3-iconbtn" type="button" title="Filtros avanzados" onClick={() => setOpen(v => !v)}>
        <i className="mdi mdi-tune-variant"></i>
      </button>
      {open && (
        <div className="wa3-hbtn__menu wa3-filter-menu">
          <h6>Rango de fechas</h6>
          <div className="wa3-filter-grid">
            <div className="wa3-field"><label>Desde</label><input type="date" value={from} onChange={e => setFrom(e.target.value)} /></div>
            <div className="wa3-field"><label>Hasta</label><input type="date" value={to} onChange={e => setTo(e.target.value)} /></div>
          </div>
          <div className="wa3-menu-footer">
            <button className="wa3-secondary-btn" onClick={() => { setFrom(''); setTo(''); onDate('', ''); }}>Limpiar</button>
            <button onClick={() => { onDate(from, to); setOpen(false); }}>Aplicar fechas</button>
          </div>
          <h6>Bandejas avanzadas</h6>
          <div className="wa3-filter-list">
            {ADV_FILTERS.map(f => (
              <button key={f.id} className={`wa3-filter-link${filter === f.id ? ' is-active' : ''}`}
                      onClick={() => { onPickFilter(f.id); setOpen(false); }}>
                <i className={`mdi ${f.icon}`}></i>
                <span><strong>{f.label}</strong><small>{f.hint}</small></span>
                <span className="count">{tabCounts[f.id] || 0}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// ── Chips (tabs with scroll arrows) ──────────────────────────────────────────

function WaChips({ filter, tabCounts, onPick }) {
  const navRef = useRef(null);
  const [showL, setShowL] = useState(false);
  const [showR, setShowR] = useState(true);
  const update = useCallback(() => {
    const n = navRef.current; if (!n) return;
    setShowL(n.scrollLeft > 2);
    setShowR(n.scrollLeft + n.clientWidth < n.scrollWidth - 2);
  }, []);
  useEffect(() => { update(); }, [update]);
  const by = (dx) => navRef.current?.scrollBy({ left: dx, behavior: 'smooth' });
  return (
    <div className="wa3-chips-wrap">
      <button className={`wa3-chips-arrow wa3-chips-arrow--left${showL ? '' : ' is-hidden'}`} onClick={() => by(-160)} aria-label="Anterior">
        <i className="mdi mdi-chevron-left" style={{ fontSize: 18 }}></i>
      </button>
      <nav className="wa3-chips" ref={navRef} onScroll={update}>
        {TABS.map(t => (
          <button key={t.id} className={`wa3-chip${filter === t.id ? ' is-active' : ''}`} onClick={() => onPick(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}<span className="wa3-chip__count">{tabCounts[t.id] ?? 0}</span>
          </button>
        ))}
      </nav>
      <button className={`wa3-chips-arrow wa3-chips-arrow--right${showR ? '' : ' is-hidden'}`} onClick={() => by(160)} aria-label="Siguiente">
        <i className="mdi mdi-chevron-right" style={{ fontSize: 18 }}></i>
      </button>
    </div>
  );
}

// ── WaInbox ───────────────────────────────────────────────────────────────────

export function WaInbox({
  activeId, filter, search, view, canSupervise, tabCounts, dateFrom, dateTo,
  agentFilter, agents, visible, loading, hasMore, loadMore, loadingMore,
  onPickConvo, onFilter, onSearch, onView, onDate, onAgentFilter, onNewConvo, onRequeue,
}) {
  return (
    <aside className="wa3-inbox">
      <div className="wa3-inbox__head">
        <div className="wa3-inbox__title-row">
          {canSupervise ? (
            <div className="wa3-inbox__tabs">
              <button className={`wa3-inbox__tab${view === 'conversations' ? ' is-active' : ''}`} onClick={() => onView('conversations')}>
                <i className="mdi mdi-message-text-outline"></i>Chats
              </button>
              <button className={`wa3-inbox__tab${view === 'agents' ? ' is-active' : ''}`} onClick={() => onView('agents')}>
                <i className="mdi mdi-account-group-outline"></i>Agentes
              </button>
            </div>
          ) : (
            <h2 className="wa3-inbox__title">Conversaciones</h2>
          )}
          <div style={{ display: 'flex', gap: 2, alignItems: 'center' }}>
            {canSupervise && <WaManagerMenu tabCounts={tabCounts} onPickFilter={onFilter} />}
            {canSupervise && (
              <button className="wa3-iconbtn" title="Reencolar handoffs vencidos" onClick={onRequeue}>
                <i className="mdi mdi-restore-alert"></i>
              </button>
            )}
            <button className="wa3-iconbtn" title="Nueva conversación" onClick={onNewConvo}><i className="mdi mdi-plus"></i></button>
            <WaFilterMenu filter={filter} tabCounts={tabCounts} dateFrom={dateFrom} dateTo={dateTo} onDate={onDate} onPickFilter={onFilter} />
          </div>
        </div>
        <div className="wa3-search">
          <i className="mdi mdi-magnify"></i>
          <input value={search} onChange={e => onSearch(e.target.value)}
                 placeholder={view === 'agents' ? 'Buscar agente…' : 'Buscar nombre, número o HC…'} />
        </div>
      </div>

      {view === 'conversations' && (
        <>
          <WaChips filter={filter} tabCounts={tabCounts} onPick={onFilter} />
          {agentFilter && (
            <div className="wa3-agentfilter">
              <i className="mdi mdi-account-filter-outline"></i>
              <span>Filtrado por <strong>{agentFilter.name}</strong></span>
              <button onClick={() => onAgentFilter(null)} title="Quitar filtro"><i className="mdi mdi-close"></i></button>
            </div>
          )}
          <div className="wa3-list">
            {loading && visible.length === 0 && (
              <div style={{ padding: 28, textAlign: 'center', color: 'var(--wa3-text-mute)', font: '400 13px var(--font-body)' }}>
                Cargando conversaciones…
              </div>
            )}
            {visible.map(c => (
              <WaInboxRow key={c.id} c={c} isActive={c.id === activeId} onClick={() => onPickConvo(c.id)} />
            ))}
            {!loading && visible.length === 0 && (
              <div style={{ padding: 28, textAlign: 'center', color: 'var(--wa3-text-mute)', font: '400 13px var(--font-body)' }}>
                Sin conversaciones en esta bandeja{search ? ` para "${search}"` : ''}.
              </div>
            )}
            {hasMore && (
              <button className="wa3-load-more" onClick={loadMore} disabled={loadingMore}>
                {loadingMore ? 'Cargando…' : 'Cargar más'}
              </button>
            )}
          </div>
        </>
      )}

      {view === 'agents' && (
        <>
          {agents.length > 0 && <WaSupervisorSummary agents={agents} />}
          <div className="wa3-list">
            {agents
              .filter(a => !search.trim() || a.name.toLowerCase().includes(search.toLowerCase()))
              .map(a => (
                <WaAgentRow key={a.id} a={a} isActive={agentFilter?.id === a.id}
                            onClick={() => { onAgentFilter(a); onView('conversations'); }} />
              ))}
          </div>
        </>
      )}
    </aside>
  );
}
