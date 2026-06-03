// ============================================================
// MedForge · Solicitudes v3 — App root
// ============================================================
import React, { useState, useMemo, useCallback, useEffect } from 'react';
import type { Solicitud, Filters, Tarea, TweakValues, Alert, ChecklistStep } from './types';
import { COLUMNS, PHASES, fetchKanbanData, fetchDetalle, rebuildState, updateEstado } from './api';
import { Kpi } from './components';
import { Toolbar, Board, TableView } from './Board';
import { DetailPanel } from './DetailPanel';
import { PrefacturaModal } from './Prefactura';
import { ConciliacionView } from './Conciliacion';
import { TweaksPanel, useTweaks } from './TweaksPanel';

const CURRENT_USER = { name: 'M. Quishpe', role: 'Coordinación quirúrgica', responsable: 'Coord. M. Quishpe' };

const TWEAK_DEFAULTS: TweakValues = {
  direction: 'a',
  density: 'comodo',
  afilColor: true,
  groupPhases: true,
  showDoctorAvatar: true,
  accent: '#5156be',
};

export function App() {
  const [tweaks, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [solicitudes, setSolicitudes] = useState<Solicitud[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [afiliaciones, setAfiliaciones] = useState<string[]>([]);
  const [doctores, setDoctores] = useState<string[]>([]);

  const [filters, setFilters] = useState<Filters>({ search: '', afiliacion: '', doctor: '' });
  const [preset, setPreset] = useState('');
  const [kpiFilter, setKpiFilter] = useState('');
  const [view, setView] = useState('kanban');

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [prefacturaId, setPrefacturaId] = useState<number | null>(null);
  const [draggingId, setDraggingId] = useState<number | null>(null);
  const [dropTarget, setDropTarget] = useState<string | null>(null);
  const [toast, setToast] = useState<{ msg: string; icon: string } | null>(null);

  // Apply accent CSS variable
  useEffect(() => {
    document.documentElement.style.setProperty('--accent', tweaks.accent || '#5156be');
  }, [tweaks.accent]);

  // Load data
  const load = useCallback(async (f?: Partial<Filters>) => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetchKanbanData(f);
      const all = Object.values(result.byColumn).flat();
      setSolicitudes(all);
      setAfiliaciones(result.afiliaciones);
      setDoctores(result.doctores);
    } catch {
      setError('No se pudo cargar las solicitudes. Intente de nuevo.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, []);

  // Toast helper
  const toastTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
  const showToast = useCallback((msg: string, icon = 'mdi-check-circle') => {
    setToast({ msg, icon });
    if (toastTimerRef.current) clearTimeout(toastTimerRef.current);
    toastTimerRef.current = setTimeout(() => setToast(null), 2600);
  }, []);

  // ---- Filtering ----
  const filtered = useMemo(() => {
    const q = filters.search.trim().toLowerCase();
    return solicitudes.filter((s: Solicitud) => {
      if (q && !`${s.full_name} ${s.hc_number} ${s.procedimiento} ${s.procedimiento_short} ${s.form_id}`.toLowerCase().includes(q)) return false;
      if (filters.afiliacion && s.empresa_seguro !== filters.afiliacion) return false;
      if (filters.doctor && s.doctor !== filters.doctor) return false;
      if (preset === 'mis-casos' && s.crm.responsable !== CURRENT_USER.responsable) return false;
      if (preset === 'urgentes' && !(s.prioridad === 'urgente' || s.sla_status === 'vencido' || s.sla_status === 'critico')) return false;
      if (kpiFilter === 'vencido' && s.sla_status !== 'vencido') return false;
      if (kpiFilter === 'critico' && s.sla_status !== 'critico') return false;
      if (kpiFilter === 'docs' && !s.alerts.some((a: Alert) => a.key === 'docs')) return false;
      if (kpiFilter === 'auth' && !s.alerts.some((a: Alert) => a.key === 'auth')) return false;
      return true;
    });
  }, [solicitudes, filters, preset, kpiFilter]);

  const byColumn = useMemo(() => {
    const m: Record<string, Solicitud[]> = {};
    COLUMNS.forEach((c) => (m[c.slug] = []));
    filtered.forEach((s: Solicitud) => { (m[s.estado] = m[s.estado] || []).push(s); });
    return m;
  }, [filtered]);

  // ---- Metrics ----
  const metrics = useMemo(() => ({
    total: solicitudes.length,
    vencido: solicitudes.filter((s: Solicitud) => s.sla_status === 'vencido').length,
    critico: solicitudes.filter((s: Solicitud) => s.sla_status === 'critico').length,
    docs: solicitudes.filter((s: Solicitud) => s.alerts.some((a: Alert) => a.key === 'docs')).length,
    auth: solicitudes.filter((s: Solicitud) => s.alerts.some((a: Alert) => a.key === 'auth')).length,
  }), [solicitudes]);

  // ---- Actions ----
  const advance = useCallback((id: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const idx = COLUMNS.findIndex((c) => c.slug === s.estado);
      if (idx >= COLUMNS.length - 1) return s;
      const next = COLUMNS[idx + 1].slug;
      showToast(`${s.full_name.split(' ')[0]} → ${COLUMNS[idx + 1].label}`);
      void updateEstado(id, next);
      return rebuildState(s, next);
    }));
  }, [showToast]);

  const moveTo = useCallback((id: number, slug: string) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id || s.estado === slug) return s;
      const col = COLUMNS.find((c) => c.slug === slug);
      if (!col) return s;
      showToast(`${s.full_name.split(' ')[0]} movido a ${col.label}`);
      void updateEstado(id, col.slug);
      return rebuildState(s, col.slug);
    }));
  }, [showToast]);

  const toggleStep = useCallback((id: number, slug: string) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const checklist = s.checklist.map((st: ChecklistStep) => st.slug === slug ? { ...st, completed: !st.completed } : st);
      const completed = checklist.filter((x: ChecklistStep) => x.completed).length;
      const total = checklist.length;
      const firstPending = checklist.find((x: ChecklistStep) => !x.completed);
      return { ...s, checklist, checklist_progress: { completed, total, percent: Math.round((completed / total) * 100), next_label: firstPending ? firstPending.label : 'Completado' } };
    }));
  }, []);

  const confirmConcil = useCallback((id: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id || !s.protocolo_posterior_compatible) return s;
      const confirmado = { ...s.protocolo_posterior_compatible, confirmado_at: new Date().toISOString(), confirmado_by: CURRENT_USER.responsable };
      showToast(`Cirugía de ${s.full_name.split(' ')[0]} confirmada · #${confirmado.form_id}`, 'mdi-check-decagram');
      return { ...rebuildState(s, 'completado'), protocolo_confirmado: confirmado, detalle: s.detalle };
    }));
  }, [showToast]);

  const patchDetalle = useCallback((id: number, fn: (s: Solicitud) => Partial<Solicitud['detalle']>) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === id ? { ...s, detalle: { ...s.detalle, ...fn(s) } } : s));
  }, []);

  const addNote = useCallback((id: number, txt: string) => {
    patchDetalle(id, (s: Solicitud) => ({ notas: [{ txt, by: CURRENT_USER.responsable, at: new Date().toISOString() }, ...s.detalle.notas] }));
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === id ? { ...s, crm: { ...s.crm, notas: s.crm.notas + 1 } } : s));
    showToast('Nota guardada', 'mdi-comment-check-outline');
  }, [patchDetalle, showToast]);

  const addTask = useCallback((id: number, task: Tarea) => {
    patchDetalle(id, (s: Solicitud) => ({ tareas: [...s.detalle.tareas, task] }));
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === id ? { ...s, crm: { ...s.crm, tareas_total: s.crm.tareas_total + 1, tareas_pendientes: s.crm.tareas_pendientes + 1 } } : s));
    showToast('Tarea añadida', 'mdi-playlist-check');
  }, [patchDetalle, showToast]);

  const toggleTask = useCallback((id: number, idx: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const tareas = s.detalle.tareas.map((t: Tarea, i: number) => i === idx ? { ...t, done: !t.done } : t);
      const pend = tareas.filter((t: Tarea) => !t.done).length;
      return { ...s, detalle: { ...s.detalle, tareas }, crm: { ...s.crm, tareas_pendientes: pend } };
    }));
  }, []);

  const togglePreop = useCallback((id: number, idx: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const preop = s.detalle.preop.map((p: { label: string; done: boolean }, i: number) => i === idx ? { ...p, done: !p.done } : p);
      return { ...s, detalle: { ...s.detalle, preop } };
    }));
  }, []);

  const addProposal = useCallback((id: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const items = [
        { cod: 'DQX-001', desc: 'Derecho de quirófano', cant: 1, valor: 320 },
        { cod: 'HON-014', desc: 'Honorarios cirujano oftalmólogo', cant: 1, valor: 480 },
        { cod: 'ANE-007', desc: 'Anestesia y honorarios', cant: 1, valor: 180 },
      ];
      const subtotal = items.reduce((a: number, it: { cod: string; desc: string; cant: number; valor: number }) => a + it.cant * it.valor, 0);
      const iva = Math.round(subtotal * 0.15 * 100) / 100;
      const prop = {
        titulo: 'Paquete quirúrgico — ' + s.procedimiento_short,
        estado: 'Borrador',
        vigencia: new Date(Date.now() + 20 * 86400000).toISOString(),
        items, subtotal, iva, total: subtotal + iva,
      };
      return { ...s, detalle: { ...s.detalle, propuestas: [...s.detalle.propuestas, prop] } };
    }));
    showToast('Borrador de propuesta creado', 'mdi-file-document-plus-outline');
  }, [showToast]);

  // ---- DnD ----
  const dnd = useMemo(() => ({
    draggingId,
    dropTarget,
    onDragStart: (e: React.DragEvent, sol: Solicitud) => {
      setDraggingId(sol.id);
      e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', String(sol.id)); } catch (_) {}
    },
    onDragEnd: () => { setDraggingId(null); setDropTarget(null); },
    onDragOver: (e: React.DragEvent, slug: string) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; if (dropTarget !== slug) setDropTarget(slug); },
    onDragLeave: () => {},
    onDrop: (e: React.DragEvent, slug: string) => { e.preventDefault(); if (draggingId != null) moveTo(draggingId, slug); setDraggingId(null); setDropTarget(null); },
  }), [draggingId, dropTarget, moveTo]);

  const selected = useMemo(() => solicitudes.find((s: Solicitud) => s.id === selectedId) ?? null, [solicitudes, selectedId]);

  // Lazy-load full detalle when a card is opened and detalle hasn't been fetched yet
  useEffect(() => {
    if (!selectedId) return;
    const sol = solicitudes.find((s: Solicitud) => s.id === selectedId);
    if (!sol) return;
    // Check if detalle is still empty (default emptyDetalle has paciente.cedula === '—')
    const alreadyLoaded = sol.detalle.paciente.cedula !== '—' || sol.detalle.notas.length > 0;
    if (alreadyLoaded) return;
    fetchDetalle(selectedId).then((detalle) => {
      setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === selectedId ? { ...s, detalle } : s));
    }).catch(() => {
      // non-critical — prefactura just shows empty sections
    });
  }, [selectedId]);

  const toggleKpi = (k: string) => setKpiFilter((cur: string) => cur === k ? '' : k);

  const shellClass = [
    'app-shell',
    `dir-${tweaks.direction}`,
    tweaks.density === 'compacto' ? 'density-compact' : '',
    tweaks.groupPhases ? '' : 'flat-phases',
    tweaks.showDoctorAvatar ? '' : 'no-doc-avatar',
    tweaks.afilColor ? '' : 'no-afil-color',
  ].filter(Boolean).join(' ');

  return (
    <div className={shellClass}>
      {/* ---- Topbar ---- */}
      <header className="app-topbar">
        <div className="app-brand">
          <span className="app-brand-mark"><i className="mdi mdi-flash"></i></span>
          <div className="app-title">
            <h1>Solicitudes quirúrgicas</h1>
            <span className="crumb">MedForge · Coordinación · /v3/solicitudes</span>
          </div>
        </div>
        <div className="topbar-spacer"></div>
        <div className="dir-switch" role="group" aria-label="Dirección visual">
          <span className="ds-label">Dirección</span>
          {(['a', 'b', 'c'] as const).map((v, i) => (
            <button
              key={v}
              className={tweaks.direction === v ? 'is-active' : ''}
              onClick={() => setTweak('direction', v)}
            >{['Clínico', 'Aireado', 'Denso'][i]}</button>
          ))}
        </div>
        <div className="topbar-actions">
          <button className="icon-btn" title="Notificaciones" onClick={() => showToast('No hay notificaciones nuevas', 'mdi-bell-check-outline')}>
            <i className="mdi mdi-bell-outline"></i><span className="dot"></span>
          </button>
          <button className="icon-btn" title="Actualizar" onClick={() => void load(filters)}>
            <i className={`mdi mdi-refresh${loading ? ' mdi-spin' : ''}`}></i>
          </button>
          <div className="user-chip">
            <span className="av">{CURRENT_USER.name.split(' ').map((w) => w[0]).join('')}</span>
            <span className="meta"><b>{CURRENT_USER.name}</b><span>{CURRENT_USER.role}</span></span>
          </div>
        </div>
      </header>

      {/* ---- KPI row ---- */}
      <div className="kpi-row">
        <Kpi tone="total"   icon="mdi-clipboard-text-multiple-outline" value={metrics.total}   label="Solicitudes totales"    active={kpiFilter === ''}        onClick={() => setKpiFilter('')} />
        <Kpi tone="vencido" icon="mdi-alert-octagon-outline"           value={metrics.vencido} label="SLA vencido"            active={kpiFilter === 'vencido'} onClick={() => toggleKpi('vencido')} />
        <Kpi tone="critico" icon="mdi-clock-alert-outline"             value={metrics.critico} label="SLA crítico"            active={kpiFilter === 'critico'} onClick={() => toggleKpi('critico')} />
        <Kpi tone="docs"    icon="mdi-file-alert-outline"              value={metrics.docs}    label="Docs faltantes"         active={kpiFilter === 'docs'}    onClick={() => toggleKpi('docs')} />
        <Kpi tone="auth"    icon="mdi-shield-clock-outline"            value={metrics.auth}    label="Autorización pendiente" active={kpiFilter === 'auth'}    onClick={() => toggleKpi('auth')} />
      </div>

      {/* ---- Toolbar ---- */}
      <Toolbar
        filters={filters}
        setFilters={setFilters}
        preset={preset}
        setPreset={setPreset}
        view={view}
        setView={setView}
        doctores={doctores}
        afiliaciones={afiliaciones}
      />

      {/* ---- Error banner ---- */}
      {error && (
        <div className="v3-error-banner" role="alert">
          {error}
          <button className="btn" style={{ height: 34, fontSize: 12, padding: '0 12px' }} onClick={() => void load(filters)}>Reintentar</button>
        </div>
      )}

      {/* ---- Loading ---- */}
      {loading && solicitudes.length === 0 && (
        <div className="v3-loading">
          <i className="mdi mdi-loading mdi-spin" style={{ fontSize: 22, marginRight: 8 }}></i>
          Cargando solicitudes…
        </div>
      )}

      {/* ---- Main view ---- */}
      {view === 'kanban' && !loading && (
        <Board
          columns={COLUMNS}
          phases={PHASES}
          byColumn={byColumn}
          onOpen={setSelectedId}
          onAdvance={advance}
          dnd={dnd}
          groupPhases={tweaks.groupPhases}
        />
      )}
      {view === 'tabla' && !loading && <TableView rows={filtered} onOpen={setSelectedId} />}
      {view === 'conciliacion' && !loading && <ConciliacionView rows={filtered} onConfirm={confirmConcil} />}

      {/* ---- Detail panel ---- */}
      <DetailPanel
        sol={selected}
        open={selectedId != null}
        onClose={() => setSelectedId(null)}
        onToggleStep={toggleStep}
        onAdvance={advance}
        onToggleTask={toggleTask}
        onAddTask={addTask}
        onAddNote={addNote}
        onAddProposal={addProposal}
        onOpenPrefactura={(id) => setPrefacturaId(id)}
        showToast={showToast}
      />

      {/* ---- Prefactura modal ---- */}
      <PrefacturaModal
        sol={solicitudes.find((s: Solicitud) => s.id === prefacturaId) ?? null}
        open={prefacturaId != null}
        onClose={() => setPrefacturaId(null)}
        onTogglePreop={togglePreop}
        showToast={showToast}
      />

      {/* ---- Toast ---- */}
      {toast && (
        <div className="toast-wrap">
          <div className="toast ok"><i className={`mdi ${toast.icon}`}></i>{toast.msg}</div>
        </div>
      )}

      {/* ---- Tweaks panel ---- */}
      <TweaksPanel tweaks={tweaks} setTweak={setTweak} />
    </div>
  );
}
