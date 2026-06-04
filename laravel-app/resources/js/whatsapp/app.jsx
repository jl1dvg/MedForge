/* MedForge · WhatsApp Chat v3 — Application root (ES module)
   Wires real API data, Pusher real-time, and all UI panels. */

import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { WaInbox } from './components.jsx';
import { WaThreadPane } from './thread.jsx';
import { WaDrawer } from './drawer.jsx';
import { WaNewConvoModal, WaFollowupModal, WaTourModal } from './modals.jsx';
import { useConversations } from './hooks/useConversations.js';
import { useMessages } from './hooks/useMessages.js';
import { usePusher } from './hooks/usePusher.js';
import { adaptAgent, rolesFromAgents } from './adapt.js';
import {
  fetchAgentSummary, fetchQuickReplies, fetchTemplates,
  assignConversation, transferConversation, queueByRole, closeConversation,
  requeueExpired, startWithTemplate,
} from './api.js';

// ── Config ────────────────────────────────────────────────────────────────────

function readConfig() {
  try {
    const el = document.getElementById('wa3-config');
    return JSON.parse(el?.textContent || '{}');
  } catch { return {}; }
}

// ── App root ──────────────────────────────────────────────────────────────────

export function WaApp() {
  const config = useMemo(() => readConfig(), []);
  const me = config.currentUser || { id: null, name: 'Usuario' };
  const pusherConfig = config.pusher || {};
  const canSupervise = config.canSupervise || false;

  // ── UI state ────────────────────────────────────────────────────────────────
  const [filter, setFilter] = useState('mine');
  const [search, setSearch] = useState('');
  const [view, setView] = useState('conversations');
  const [agentFilter, setAgentFilter] = useState(null);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [activeId, setActiveId] = useState(null);
  const [showDrawer, setShowDrawer] = useState(true);
  const [draft, setDraft] = useState('');
  const [modal, setModal] = useState(null);
  const [toastMsg, setToastState] = useState(null);
  const [realtimePending, setRealtimePending] = useState(false);

  // ── Remote data ─────────────────────────────────────────────────────────────
  const [agents, setAgents] = useState([]);
  const [quickReplies, setQuickReplies] = useState([]);
  const [templates, setTemplates] = useState([]);

  useEffect(() => {
    fetchAgentSummary().then(r => setAgents((r.data?.agents || []).map(adaptAgent))).catch(() => {});
    fetchQuickReplies().then(r => setQuickReplies(r.data || [])).catch(() => {});
    fetchTemplates().then(r => setTemplates(r.data || [])).catch(() => {});
  }, []);

  // First-visit tour — also activates with ?tour=1 in the URL
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tour') === '1') { setModal('tour'); return; }
    let seen = false;
    try { seen = localStorage.getItem('medforge_chat_tour_visto') === '1'; } catch {}
    if (!seen) { const id = setTimeout(() => setModal('tour'), 600); return () => clearTimeout(id); }
  }, []);

  // ── Conversation list ────────────────────────────────────────────────────────
  const { convos, tabCounts, loading: convosLoading, loadingMore, hasMore, reload: reloadConvos, loadMore } = useConversations({
    filter,
    search,
    agentId: agentFilter?.id,
  });

  const activeConvo = useMemo(() => convos.find(c => c.id === activeId) || null, [convos, activeId]);

  // ── Messages for active conversation ────────────────────────────────────────
  const {
    thread, notes, trail, loading: messagesLoading,
    sendMessage, sendMedia, appendInbound, addNote,
  } = useMessages(activeId);

  // ── Pusher real-time ─────────────────────────────────────────────────────────
  usePusher(pusherConfig, useCallback((event, payload) => {
    reloadConvos();
    const payloadConvId = payload?.conversation?.id ?? payload?.conversation_id ?? payload?.id;
    if (payloadConvId === activeId && payload?.message) {
      appendInbound(payload.message);
    } else if (payloadConvId === activeId) {
      setRealtimePending(true);
    }
  }, [activeId, reloadConvos, appendInbound]));

  // ── Toast ────────────────────────────────────────────────────────────────────
  const notify = useCallback((msg, icon = 'mdi-check-circle') => {
    setToastState({ msg, icon });
    clearTimeout(notify._t);
    notify._t = setTimeout(() => setToastState(null), 2800);
  }, []);

  // ── Visual tweaks — compact mode + accent color, persisted in localStorage ───
  const [compact, setCompact] = useState(() => {
    try { return localStorage.getItem('wa3_compact') === '1'; } catch { return false; }
  });
  const [accent, setAccent] = useState(() => {
    try { return localStorage.getItem('wa3_accent') || '#5156be'; } catch { return '#5156be'; }
  });

  const applyCompact = useCallback((v) => {
    setCompact(v);
    try { localStorage.setItem('wa3_compact', v ? '1' : '0'); } catch {}
  }, []);

  const applyAccent = useCallback((v) => {
    setAccent(v);
    try { localStorage.setItem('wa3_accent', v); } catch {}
  }, []);

  // ── Accent CSS vars (dynamic) ─────────────────────────────────────────────────
  const rootStyle = useMemo(() => ({
    '--wa3-accent': accent,
    '--wa3-accent-soft': `color-mix(in srgb, ${accent} 12%, white)`,
    '--wa3-bubble-out': `color-mix(in srgb, ${accent} 14%, white)`,
  }), [accent]);

  // ── Pick conversation ────────────────────────────────────────────────────────
  const pickConvo = useCallback((id) => {
    setActiveId(id);
    setDraft('');
    setRealtimePending(false);
  }, []);

  // ── Roles derived from agents ────────────────────────────────────────────────
  const roles = useMemo(() => rolesFromAgents(agents), [agents]);

  // ── Action handlers ──────────────────────────────────────────────────────────
  const handlers = useMemo(() => ({
    onSend: async (text) => {
      if (!text.trim()) return;
      setDraft('');
      await sendMessage(text);
    },

    onSendMedia: async (type, uploadedData, caption) => {
      setDraft('');
      await sendMedia(type, uploadedData, caption);
    },
    onToggleDrawer: () => setShowDrawer(v => !v),

    onAssignSelf: async () => {
      if (!activeConvo || !me.id) return;
      try {
        await assignConversation(activeConvo.id, me.id);
        await reloadConvos();
        notify('Conversación asignada a ti', 'mdi-hand-back-right');
      } catch { notify('Error al asignar', 'mdi-alert'); }
    },

    onTransfer: async (agent, note) => {
      if (!activeConvo) return;
      try {
        await transferConversation(activeConvo.id, agent.id, note);
        await reloadConvos();
        notify(`Transferida a ${agent.name}`, 'mdi-account-arrow-right');
      } catch { notify('Error al transferir', 'mdi-alert'); }
    },

    onQueueRole: async (role, note) => {
      if (!activeConvo) return;
      try {
        await queueByRole(activeConvo.id, role.id, note);
        await reloadConvos();
        notify(`Derivada al equipo de ${role.name}`, 'mdi-account-multiple');
      } catch { notify('Error al derivar', 'mdi-alert'); }
    },

    onResolve: async () => {
      if (!activeConvo) return;
      try {
        await closeConversation(activeConvo.id);
        await reloadConvos();
        notify('Conversación resuelta', 'mdi-check-decagram');
        setFilter('closed');
        setActiveId(null);
      } catch { notify('Error al cerrar', 'mdi-alert'); }
    },

    onOpenSendTemplate: (tpl) => setModal({ type: 'sendTemplate', tpl }),

    onCopy: (value, label) => {
      const text = value.startsWith('/') ? `${window.location.origin}${value}` : value;
      try { navigator.clipboard?.writeText(text); } catch {}
      notify(`${label} copiado`, 'mdi-content-copy');
    },

    onOpenTrail: () => {
      if (!showDrawer) setShowDrawer(true);
      setTimeout(() => document.getElementById('wa3-trail-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 120);
    },

    onFollowup: () => setModal('followup'),

    onRealtimeReload: async () => {
      setRealtimePending(false);
      await reloadConvos();
      notify('Conversación actualizada', 'mdi-refresh');
    },

    onBackMobile: () => setActiveId(null),
  }), [activeConvo, me.id, showDrawer, sendMessage, sendMedia, notify, reloadConvos]);

  const shellClass = ['wa3', showDrawer ? 'has-drawer' : '', compact ? 'is-compact' : '', activeId ? 'has-active' : ''].filter(Boolean).join(' ');

  return (
    <div className={shellClass} id="wa3-root" style={rootStyle}>
      <WaInbox
        activeId={activeId} filter={filter} search={search} view={view}
        canSupervise={canSupervise} tabCounts={tabCounts}
        dateFrom={dateFrom} dateTo={dateTo}
        agentFilter={agentFilter} agents={agents}
        visible={convos} loading={convosLoading} hasMore={hasMore} loadMore={loadMore} loadingMore={loadingMore}
        onPickConvo={pickConvo} onFilter={setFilter} onSearch={setSearch}
        onView={setView}
        onDate={(f, to) => {
          setDateFrom(f); setDateTo(to);
          if (f || to) notify('Rango de fechas aplicado', 'mdi-calendar-range');
        }}
        onAgentFilter={setAgentFilter}
        onNewConvo={() => setModal('new')}
        onRequeue={async () => {
          try { await requeueExpired(); notify('Handoffs vencidos reencolados', 'mdi-restore-alert'); }
          catch { notify('Error al reencolar', 'mdi-alert'); }
        }}
        compact={compact} accent={accent}
        onTweakCompact={applyCompact} onTweakAccent={applyAccent}
        onTour={() => setModal('tour')}
      />

      <WaThreadPane
        convo={activeConvo} thread={thread} typing={false}
        draft={draft} setDraft={setDraft}
        showDrawer={showDrawer} canSupervise={canSupervise} canOperate={true}
        realtime={realtimePending} handlers={handlers} toast={notify}
        templates={templates} agents={agents} roles={roles}
      />

      {showDrawer && (
        <WaDrawer
          convo={activeConvo} notes={notes} trail={trail} canOperate={true}
          onAddNote={addNote}
          quickReplies={quickReplies}
          onApplyQuickReply={setDraft}
          onCreateQuickReply={(title, body) => {
            setQuickReplies(prev => [...prev, { id: Date.now(), title, body }]);
            notify('Respuesta rápida creada', 'mdi-lightning-bolt');
          }}
          onFollowup={() => setModal('followup')}
        />
      )}

      {(modal === 'new' || modal?.type === 'sendTemplate') && (
        <WaNewConvoModal
          onClose={async (convId) => {
            setModal(null);
            if (convId) { await reloadConvos(); setActiveId(convId); }
          }}
          toast={notify} templates={templates} convos={convos}
          prefill={modal?.type === 'sendTemplate' && activeConvo ? {
            number: activeConvo.wa,
            contact: activeConvo.name,
            patient: activeConvo.patient?.name || activeConvo.name,
            hc: activeConvo.hc || '',
            tplId: modal.tpl.id,
          } : null}
        />
      )}
      {modal === 'followup' && (
        <WaFollowupModal
          onClose={() => setModal(null)}
          onConfirm={async (reason) => {
            await handlers.onResolve();
            setModal(null);
            notify('Seguimiento cerrado · lead generado', 'mdi-archive-check');
          }}
        />
      )}
      {modal === 'tour' && (
        <WaTourModal onClose={() => {
          try { localStorage.setItem('medforge_chat_tour_visto', '1'); } catch {}
          setModal(null);
        }} />
      )}

      {toastMsg && (
        <div className="wa3-toast-wrap">
          <div className="wa3-toast"><i className={`mdi ${toastMsg.icon}`}></i>{toastMsg.msg}</div>
        </div>
      )}
    </div>
  );
}
