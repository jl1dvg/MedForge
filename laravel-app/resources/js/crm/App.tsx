import React, { useState, useMemo, useCallback, useEffect } from 'react';
import type { OpportunityView, Tarea, Comunicacion } from './types';
import { STAGES, STAGE_MAP, PHASES } from './stages';
import { adaptOpportunity, fmtMoney, nextActionState, initials } from './helpers';
import { api } from './api';
import { Kpi } from './components/Kpi';
import { Board } from './components/Board';
import { MiDia } from './components/MiDia';
import { TableView } from './components/TableView';
import { DetailPanel } from './components/DetailPanel';
import { CloseModal } from './components/CloseModal';
import { MetricsView } from './components/MetricsView';

type View = 'embudo' | 'midia' | 'tabla' | 'metricas';

interface Toast { msg: string; icon: string; kind: string; }

function nowIso() { return new Date().toISOString(); }

export default function App() {
  const [ops, setOps] = useState<OpportunityView[]>([]);
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState<View>('embudo');
  const [filters, setFilters] = useState({ search: '', afiliacion: '', fuente: '', tipo: '' });
  const [preset, setPreset] = useState('');
  const [kpiFilter, setKpiFilter] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [closeModal, setCloseModal] = useState<{ id: number | null; mode: 'win' | 'lose' | null }>({ id: null, mode: null });
  const [sort, setSort] = useState<{ key: string; dir: 'asc' | 'desc' }>({ key: 'probabilidad', dir: 'desc' });
  const [draggingId, setDraggingId] = useState<number | null>(null);
  const [dropTarget, setDropTarget] = useState<string | null>(null);
  const [toast, setToast] = useState<Toast | null>(null);
  const [groupPhases, setGroupPhases] = useState(true);

  // Load opportunities (exclude publico/IESS — handled via separate workflow)
  useEffect(() => {
    api.opportunities.list({ limit: 500 })
      .then(r => setOps(r.data.map(adaptOpportunity).filter(o => o.afiliacion !== 'publico')))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const showToast = useCallback((msg: string, icon = 'mdi-check-circle', kind = 'ok') => {
    setToast({ msg, icon, kind });
    const t = setTimeout(() => setToast(null), 2600);
    return () => clearTimeout(t);
  }, []);

  // ── Filtering ──────────────────────────────────────────────────────────────

  const filtered = useMemo(() => {
    const q = filters.search.trim().toLowerCase();
    return ops.filter(o => {
      if (q && !(`${o.full_name} ${o.hc_number || ''} ${o.procedimiento_short} ${o.telefono}`.toLowerCase().includes(q))) return false;
      if (filters.afiliacion && o.afiliacion !== filters.afiliacion) return false;
      if (filters.fuente && o.fuente !== filters.fuente) return false;
      if (filters.tipo && o.tipo !== filters.tipo) return false;
      if (preset === 'urgentes' && !(o.prioridad === 'urgente' || nextActionState(o) === 'vencida')) return false;
      if (kpiFilter === 'hoy' && !((['vencida', 'hoy'] as string[]).includes(nextActionState(o)) && o.stage !== 'ganado' && o.stage !== 'perdido')) return false;
      if (kpiFilter === 'vencida' && nextActionState(o) !== 'vencida') return false;
      if (kpiFilter === 'ganada' && o.stage !== 'ganado') return false;
      return true;
    });
  }, [ops, filters, preset, kpiFilter]);

  const sorted = useMemo(() => {
    const arr = [...filtered];
    arr.sort((a: any, b: any) => {
      let av = a[sort.key], bv = b[sort.key];
      if (sort.key === 'stage') {
        av = STAGES.findIndex(s => s.slug === a.stage);
        bv = STAGES.findIndex(s => s.slug === b.stage);
      }
      if (typeof av === 'string') return sort.dir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
      return sort.dir === 'asc' ? (av ?? 0) - (bv ?? 0) : (bv ?? 0) - (av ?? 0);
    });
    return arr;
  }, [filtered, sort]);

  const byStage = useMemo(() => {
    const m: Record<string, OpportunityView[]> = {};
    STAGES.forEach(s => (m[s.slug] = []));
    filtered.forEach(o => { (m[o.stage] = m[o.stage] || []).push(o); });
    return m;
  }, [filtered]);

  // ── Metrics ────────────────────────────────────────────────────────────────

  const metrics = useMemo(() => {
    const active = ops.filter(o => o.stage !== 'ganado' && o.stage !== 'perdido');
    const pipelineVal = active.reduce((a, o) => a + (o.valor || 0), 0);
    const hoy = active.filter(o => (['vencida', 'hoy'] as string[]).includes(nextActionState(o))).length;
    const vencidas = active.filter(o => nextActionState(o) === 'vencida').length;
    const ganadas = ops.filter(o => o.stage === 'ganado');
    const ganadasVal = ganadas.reduce((a, o) => a + (o.cierre?.valor_final || o.valor || 0), 0);
    const cerradas = ops.filter(o => o.stage === 'ganado' || o.stage === 'perdido').length;
    const conv = cerradas ? Math.round((ganadas.length / cerradas) * 100) : 0;
    return { pipelineCount: active.length, pipelineVal, hoy, vencidas, ganadasCount: ganadas.length, ganadasVal, conv };
  }, [ops]);

  // ── Mutations ──────────────────────────────────────────────────────────────

  const patch = useCallback((id: number, fn: (o: OpportunityView) => OpportunityView) =>
    setOps(list => list.map(o => o.id === id ? fn(o) : o)), []);

  const moveStage = useCallback(async (id: number, slug: string, opts: { reason?: string; reason_label?: string; note?: string } = {}) => {
    const stageConf = STAGE_MAP[slug];
    if (!stageConf) return;
    try {
      await api.opportunities.update(id, {
        stage: slug as any,
        ...(slug === 'perdido' && opts.reason ? { lost_reason: opts.reason } : {}),
      });
    } catch (e) { console.error(e); }
    patch(id, o => {
      const tl = [...o.timeline, {
        tipo: slug === 'ganado' ? 'won' : slug === 'perdido' ? 'lost' : 'stage',
        txt: slug === 'ganado' ? 'Oportunidad ganada' : slug === 'perdido' ? 'Oportunidad perdida' : `Avanzó a ${stageConf.label}`,
        by: 'Usuario',
        at: nowIso(),
      }];
      const upd: OpportunityView = { ...o, stage: slug as any, probabilidad: stageConf.prob, last_activity_at: nowIso(), timeline: tl };
      if (slug === 'ganado') upd.cierre = { resultado: 'ganada', motivo: opts.note || null, at: nowIso() };
      if (slug === 'perdido') upd.cierre = { resultado: 'perdida', motivo: opts.reason || null, motivo_label: opts.reason_label, at: nowIso() };
      if (slug !== 'ganado' && slug !== 'perdido') upd.cierre = null;
      return upd;
    });
  }, [patch]);

  const advance = useCallback((id: number) => {
    const o = ops.find(x => x.id === id);
    if (!o) return;
    const idx = STAGES.findIndex(s => s.slug === o.stage);
    const next = STAGES[idx + 1];
    if (!next || next.slug === 'ganado' || next.slug === 'perdido') {
      if (next?.slug === 'ganado') setCloseModal({ id, mode: 'win' });
      return;
    }
    moveStage(id, next.slug);
    showToast(`${o.full_name.split(' ')[0]} → ${next.label}`);
  }, [ops, moveStage, showToast]);

  const onDrop = useCallback((id: number, slug: string) => {
    const o = ops.find(x => x.id === id);
    if (!o || o.stage === slug) return;
    if (slug === 'ganado') { setCloseModal({ id, mode: 'win' }); return; }
    if (slug === 'perdido') { setCloseModal({ id, mode: 'lose' }); return; }
    moveStage(id, slug);
    showToast(`${o.full_name.split(' ')[0]} movido a ${STAGE_MAP[slug]?.label}`);
  }, [ops, moveStage, showToast]);

  const confirmClose = useCallback((id: number, mode: 'win' | 'lose', data: { reason: string; reason_label?: string; note: string }) => {
    const o = ops.find(x => x.id === id);
    moveStage(id, mode === 'win' ? 'ganado' : 'perdido', data);
    setCloseModal({ id: null, mode: null });
    if (mode === 'win') showToast(`¡${o?.full_name.split(' ')[0]} ganada!`, 'mdi-trophy-variant', 'win');
    else showToast(`${o?.full_name.split(' ')[0]} marcada como perdida`, 'mdi-close-octagon', 'lose');
  }, [ops, moveStage, showToast]);

  const toggleTask = useCallback((id: number, idx: number) =>
    patch(id, o => ({ ...o, tareas: o.tareas.map((t: Tarea, i: number) => i === idx ? { ...t, done: !t.done } : t) })), [patch]);

  const addTask = useCallback((id: number, titulo: string) => {
    patch(id, o => ({ ...o, tareas: [...o.tareas, { titulo, resp: 'yo', due: new Date(Date.now() + 86400000).toISOString(), prioridad: 'normal' as const, done: false }] }));
    showToast('Tarea añadida', 'mdi-playlist-check');
  }, [patch, showToast]);

  const sendCom = useCallback((id: number, txt: string) => {
    patch(id, o => ({
      ...o,
      comunicaciones: [...o.comunicaciones, { canal: 'whatsapp' as const, dir: 'out' as const, txt, at: nowIso(), by: 'Yo' }],
      last_activity_at: nowIso(),
    }));
    showToast('Mensaje enviado', 'mdi-whatsapp');
  }, [patch, showToast]);

  const onQuick = useCallback((op: OpportunityView, kind: string) => {
    const msgs: Record<string, { msg: string; icon: string }> = {
      whatsapp: { msg: `Abriendo WhatsApp con ${op.full_name.split(' ')[0]}…`, icon: 'mdi-whatsapp' },
      llamada: { msg: `Llamando a ${op.telefono}…`, icon: 'mdi-phone' },
      agendar: { msg: 'Abriendo agenda…', icon: 'mdi-calendar-plus' },
      cotizar: { msg: 'Preparando cotización…', icon: 'mdi-file-document-outline' },
    };
    const m = msgs[kind] || msgs.whatsapp;
    showToast(m.msg, m.icon);
    if (kind === 'whatsapp' || kind === 'cotizar') setSelectedId(op.id);
  }, [showToast]);

  const dnd = useMemo(() => ({
    draggingId,
    dropTarget,
    onDragStart: (e: React.DragEvent, op: OpportunityView) => {
      setDraggingId(op.id);
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', String(op.id)); } catch {}
    },
    onDragEnd: () => { setDraggingId(null); setDropTarget(null); },
    onDragOver: (e: React.DragEvent, slug: string) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; if (dropTarget !== slug) setDropTarget(slug); },
    onDragLeave: () => {},
    onDrop: (e: React.DragEvent, slug: string) => { e.preventDefault(); if (draggingId != null) onDrop(draggingId, slug); setDraggingId(null); setDropTarget(null); },
  }), [draggingId, dropTarget, onDrop]);

  const selected = useMemo(() => ops.find(o => o.id === selectedId) || null, [ops, selectedId]);
  const closeModalOp = useMemo(() => ops.find(o => o.id === closeModal.id) || null, [ops, closeModal.id]);
  const toggleKpi = (k: string) => setKpiFilter(cur => cur === k ? '' : k);
  const hasFilters = filters.search || filters.afiliacion || filters.fuente || filters.tipo || preset || kpiFilter;

  return (
    <div className="app-shell">
      <div className="kpi-row">
        <Kpi tone="pipeline" icon="mdi-chart-timeline-variant" value={metrics.pipelineCount} label="Oportunidades abiertas" active={kpiFilter === ''} onClick={() => setKpiFilter('')} />
        <Kpi tone="money" icon="mdi-cash-multiple" value={fmtMoney(metrics.pipelineVal)} label="Valor del embudo" active={false} onClick={() => {}} />
        <Kpi tone="today" icon="mdi-clock-alert-outline" value={metrics.hoy} label="Acciones para hoy" active={kpiFilter === 'hoy'} onClick={() => toggleKpi('hoy')} />
        <Kpi tone="overdue" icon="mdi-alert-octagon-outline" value={metrics.vencidas} label="Atrasadas" active={kpiFilter === 'vencida'} onClick={() => toggleKpi('vencida')} />
        <Kpi tone="win" icon="mdi-trophy-variant-outline" value={metrics.ganadasCount} label={`Ganadas · ${metrics.conv}% conversión`} active={kpiFilter === 'ganada'} onClick={() => toggleKpi('ganada')} spark={{ dir: 'up', txt: fmtMoney(metrics.ganadasVal) }} hideSmall />
      </div>

      <div className="toolbar">
        <div className="topbar-search">
          <i className="mdi mdi-magnify"></i>
          <input
            placeholder="Buscar paciente, HC, teléfono…"
            value={filters.search}
            onChange={e => setFilters(f => ({ ...f, search: e.target.value }))}
          />
        </div>
        <div className="seg">
          <button className={view === 'embudo' ? 'is-active' : ''} onClick={() => setView('embudo')}>
            <i className="mdi mdi-view-column-outline"></i><span className="seg-lbl">Embudo</span>
          </button>
          <button className={view === 'midia' ? 'is-active' : ''} onClick={() => setView('midia')}>
            <i className="mdi mdi-clipboard-list-outline"></i><span className="seg-lbl">Mi día</span>
            {metrics.hoy > 0 && <span className="seg-badge">{metrics.hoy}</span>}
          </button>
          <button className={view === 'tabla' ? 'is-active' : ''} onClick={() => setView('tabla')}>
            <i className="mdi mdi-table"></i><span className="seg-lbl">Tabla</span>
          </button>
          <button className={view === 'metricas' ? 'is-active' : ''} onClick={() => setView('metricas')}>
            <i className="mdi mdi-chart-box-outline"></i><span className="seg-lbl">Métricas</span>
          </button>
        </div>

        <button className={`chip-toggle t-urgent${preset === 'urgentes' ? ' is-active' : ''}`} onClick={() => setPreset(p => p === 'urgentes' ? '' : 'urgentes')}>
          <i className="mdi mdi-fire"></i>Urgentes
        </button>

        <select className="filter-select" value={filters.tipo} onChange={e => setFilters(f => ({ ...f, tipo: e.target.value }))}>
          <option value="">Todo tipo</option>
          <option value="quirurgico">Quirúrgicas</option>
          <option value="examen">Exámenes</option>
        </select>
        <select className="filter-select" value={filters.afiliacion} onChange={e => setFilters(f => ({ ...f, afiliacion: e.target.value }))}>
          <option value="">Toda afiliación</option>
          <option value="particular">Particular</option>
          <option value="privado">Privado</option>
          <option value="fundacional">Fundacional</option>
        </select>
        <select className="filter-select" value={filters.fuente} onChange={e => setFilters(f => ({ ...f, fuente: e.target.value }))}>
          <option value="">Todo origen</option>
          <option value="whatsapp">WhatsApp</option>
          <option value="solicitud">Solicitud</option>
          <option value="examen">Examen</option>
          <option value="manual">Manual</option>
        </select>

        <div className="toolbar-spacer"></div>
        {hasFilters && (
          <button className="tip-clear" onClick={() => { setFilters({ search: '', afiliacion: '', fuente: '', tipo: '' }); setPreset(''); setKpiFilter(''); }}>
            <i className="mdi mdi-close-circle-outline"></i>Limpiar
          </button>
        )}
        <button className="btn-new"><i className="mdi mdi-plus"></i>Nueva</button>
      </div>

      {loading ? (
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--fg-mute)', gap: 12 }}>
          <i className="mdi mdi-loading mdi-spin" style={{ fontSize: 28 }}></i>
          Cargando oportunidades…
        </div>
      ) : (
        <>
          {view === 'embudo' && <Board byStage={byStage} onOpen={setSelectedId} onQuick={onQuick} dnd={dnd} groupPhases={groupPhases} />}
          {view === 'tabla' && <TableView rows={sorted} onOpen={setSelectedId} sort={sort} setSort={setSort} />}
          {view === 'midia' && <MiDia ops={filtered} onOpen={setSelectedId} onQuick={onQuick} onAdvance={advance} />}
          {view === 'metricas' && <MetricsView ops={filtered} />}
        </>
      )}

      <DetailPanel
        op={selected} open={selectedId != null} onClose={() => setSelectedId(null)}
        onAdvance={advance} onToggleTask={toggleTask} onAddTask={addTask}
        onSendCom={sendCom} onQuick={onQuick}
        onWin={id => setCloseModal({ id, mode: 'win' })}
        onLose={id => setCloseModal({ id, mode: 'lose' })}
      />

      <CloseModal
        op={closeModalOp} mode={closeModal.mode} open={closeModal.id != null}
        onClose={() => setCloseModal({ id: null, mode: null })} onConfirm={confirmClose}
      />

      {toast && (
        <div className="toast-wrap">
          <div className={`toast ${toast.kind}`}>
            <i className={`mdi ${toast.icon}`}></i>{toast.msg}
          </div>
        </div>
      )}
    </div>
  );
}
