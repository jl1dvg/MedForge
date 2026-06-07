// ============================================================
// MedForge · Solicitudes v3 — App root
// ============================================================
import React, { useState, useMemo, useCallback, useEffect } from 'react';
import type { Solicitud, Filters, TweakValues, Alert, ChecklistStep, CrmCaseState } from './types';
import {
  COLUMNS,
  PHASES,
  fetchKanbanData,
  fetchDetalle,
  rebuildState,
  updateEstado,
  fetchCrmCase,
  createCrmNote,
  deleteCrmNote,
  createCrmTask,
  updateCrmTask,
  updateCrmCase,
  storeCrmContact,
  sendCrmWhatsapp,
  sendCrmEmail,
  storeCrmProposal,
  sendCrmProposalEmail,
  sendCrmProposalWhatsapp,
  rescrapeDerivacion,
  uploadCrmDocument,
  sendCoverageMail,
} from './api';
import { Kpi } from './components';
import { Toolbar, Board, TableView } from './Board';
import { DetailPanel } from './DetailPanel';
import { PrefacturaModal } from './Prefactura';
import { ConciliacionView } from './Conciliacion';
import { TweaksPanel, useTweaks } from './TweaksPanel';

const CURRENT_USER = { name: 'M. Quishpe', role: 'Coordinación quirúrgica', responsable: 'Coord. M. Quishpe' };

function isoDateOffset(daysOffset: number): string {
  const date = new Date();
  date.setDate(date.getDate() + daysOffset);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const DEFAULT_DATE_FILTERS: Pick<Filters, 'date_from' | 'date_to'> = {
  date_from: isoDateOffset(-15),
  date_to: isoDateOffset(0),
};

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

  const [filters, setFilters] = useState<Filters>({ search: '', afiliacion: '', doctor: '', ...DEFAULT_DATE_FILTERS });
  const [preset, setPreset] = useState('');
  const [kpiFilter, setKpiFilter] = useState('');
  const [view, setView] = useState('kanban');

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [crmCase, setCrmCase] = useState<CrmCaseState | null>(null);
  const [crmLoading, setCrmLoading] = useState(false);
  const [crmError, setCrmError] = useState<string | null>(null);
  const [prefacturaId, setPrefacturaId] = useState<number | null>(null);
  const [draggingId, setDraggingId] = useState<number | null>(null);
  const [dropTarget, setDropTarget] = useState<string | null>(null);
  const [toast, setToast] = useState<{ msg: string; icon: string } | null>(null);
  const [lastRefreshed, setLastRefreshed] = useState<Date | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [tick, setTick] = useState(0);
  const selectedIdRef = React.useRef<number | null>(selectedId);
  selectedIdRef.current = selectedId;
  const firstLoadRef = React.useRef(true);
  const filtersRef = React.useRef(filters);
  filtersRef.current = filters;
  const refreshingRef = React.useRef(false);

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
      setLastRefreshed(new Date());
    } catch {
      setError('No se pudo cargar las solicitudes. Intente de nuevo.');
    } finally {
      setLoading(false);
    }
  }, []);

  // Silent background refresh (polling)
  const silentLoad = useCallback(async () => {
    if (refreshingRef.current) return;
    refreshingRef.current = true;
    setRefreshing(true);
    try {
      const result = await fetchKanbanData(filtersRef.current);
      setSolicitudes(Object.values(result.byColumn).flat());
      setAfiliaciones(result.afiliaciones);
      setDoctores(result.doctores);
      setLastRefreshed(new Date());
    } catch {
      // silent — don't surface transient network errors during polling
    } finally {
      refreshingRef.current = false;
      setRefreshing(false);
    }
  }, []);

  // Tick every 10s to update the "Actualizado hace Xs" label
  useEffect(() => {
    const id = setInterval(() => setTick((t) => t + 1), 10_000);
    return () => clearInterval(id);
  }, []);

  // Auto-refresh every 60s — skip if user is mid-drag or has a panel/modal open
  useEffect(() => {
    const id = setInterval(() => {
      if (draggingId !== null || selectedId !== null || prefacturaId !== null) return;
      void silentLoad();
    }, 60_000);
    return () => clearInterval(id);
  }, [draggingId, selectedId, prefacturaId, silentLoad]);

  useEffect(() => {
    const delay = firstLoadRef.current ? 0 : 250;
    firstLoadRef.current = false;
    const timer = setTimeout(() => {
      void load(filters);
    }, delay);

    return () => clearTimeout(timer);
  }, [filters, load]);

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
      if (filters.date_from && new Date(s.fecha) < new Date(`${filters.date_from}T00:00:00`)) return false;
      if (filters.date_to && new Date(s.fecha) > new Date(`${filters.date_to}T23:59:59`)) return false;
      if (preset === 'urgentes' && !(s.prioridad === 'urgente' || s.sla_status === 'vencido' || s.sla_status === 'critico')) return false;
      if (kpiFilter === 'vencido' && s.sla_status !== 'vencido') return false;
      if (kpiFilter === 'critico' && s.sla_status !== 'critico') return false;
      if (kpiFilter === 'docs' && !s.alerts.some((a: Alert) => a.key === 'docs')) return false;
      if (kpiFilter === 'auth' && !s.alerts.some((a: Alert) => a.key === 'auth')) return false;
      if (kpiFilter === 'propuestas' && s.crm.propuestas <= 0) return false;
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
    propuestas: solicitudes.filter((s: Solicitud) => s.crm.propuestas > 0).length,
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

  const addCrmNote = useCallback(async (txt: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await createCrmNote('solicitud', caseId, txt);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? { ...s, crm: { ...s.crm, notas: updated.notes.length } } : s));
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Nota guardada', 'mdi-comment-check-outline');
  }, [selectedId, showToast]);

  const removeCrmNote = useCallback(async (noteId: number) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await deleteCrmNote('solicitud', caseId, noteId);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? { ...s, crm: { ...s.crm, notas: updated.notes.length } } : s));
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Nota eliminada', 'mdi-delete-outline');
  }, [selectedId, showToast]);

  const syncCrmCounts = useCallback((caseId: number, updated: CrmCaseState) => {
    const pendientes = updated.tasks.filter((task) => task.status !== 'done' && task.status !== 'completed').length;
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? {
      ...s,
      crm: {
        ...s.crm,
        notas: updated.notes.length,
        propuestas: updated.proposals.length,
        tareas_total: updated.tasks.length,
        tareas_pendientes: pendientes,
      },
    } : s));
  }, []);

  const addTask = useCallback(async (title: string, priority: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await createCrmTask('solicitud', caseId, { title, priority });
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Tarea añadida', 'mdi-playlist-check');
  }, [selectedId, showToast, syncCrmCounts]);

  const toggleTask = useCallback(async (taskId: number, currentStatus: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const completed = currentStatus === 'done' || currentStatus === 'completed';
    const updated = await updateCrmTask('solicitud', caseId, taskId, { status: completed ? 'pending' : 'done' });
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
  }, [selectedId, syncCrmCounts]);

  const assignResponsible = useCallback(async (responsibleId: number | null) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await updateCrmCase('solicitud', caseId, { responsable_id: responsibleId });
    syncCrmCounts(caseId, updated);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? {
      ...s,
      crm: {
        ...s.crm,
        responsable: updated.responsibleName || 'Coordinación',
      },
    } : s));
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Responsable actualizado', 'mdi-account-check-outline');
  }, [selectedId, showToast, syncCrmCounts]);

  const addContact = useCallback(async (type: 'phone' | 'email', value: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await storeCrmContact('solicitud', caseId, { type, value });
    syncCrmCounts(caseId, updated);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? {
      ...s,
      crm: {
        ...s.crm,
        telefono: updated.contacts.primaryPhone || s.crm.telefono,
        email: updated.contacts.primaryEmail || s.crm.email,
      },
    } : s));
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast(type === 'phone' ? 'Teléfono guardado' : 'Correo guardado', type === 'phone' ? 'mdi-phone-check-outline' : 'mdi-email-check-outline');
  }, [selectedId, showToast, syncCrmCounts]);

  const refreshDerivacion = useCallback(async (id: number) => {
    const sol = solicitudes.find((s: Solicitud) => s.id === id);
    if (!sol) return;
    const detalle = await rescrapeDerivacion(sol);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === id ? { ...s, detalle } : s));
    showToast(detalle.derivacion.tiene ? 'Derivación actualizada' : 'No se encontró derivación', detalle.derivacion.tiene ? 'mdi-shield-check-outline' : 'mdi-shield-alert-outline');
  }, [solicitudes, showToast]);

  const uploadDocument = useCallback(async (file: File, descripcion: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const detalle = await uploadCrmDocument({ id: caseId }, file, descripcion);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? {
      ...s,
      detalle,
      crm: { ...s.crm, adjuntos: detalle.adjuntos.length },
    } : s));
    const updated = await fetchCrmCase('solicitud', caseId);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current === caseId && updated.sourceId === caseId) setCrmCase(updated);
    showToast('Documento subido', 'mdi-paperclip-check');
  }, [selectedId, showToast, syncCrmCounts]);

  const sendCoverage = useCallback(async (payload: { to: string; cc: string; subject: string; body: string; attachment?: File | null; isHtml?: boolean; templateKey?: string | null; derivacionPdf?: string | null }) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const sol = solicitudes.find((s: Solicitud) => s.id === caseId);
    if (!sol) return;
    const detalle = await sendCoverageMail(sol, payload);
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === caseId ? { ...s, detalle } : s));
    const updated = await fetchCrmCase('solicitud', caseId);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current === caseId && updated.sourceId === caseId) setCrmCase(updated);
    showToast('Correo de cobertura enviado', 'mdi-email-check-outline');
  }, [selectedId, solicitudes, showToast, syncCrmCounts]);

  const sendWhatsapp = useCallback(async (payload: { recipients: string[]; message: string }) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await sendCrmWhatsapp('solicitud', caseId, payload);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('WhatsApp enviado', 'mdi-whatsapp');
  }, [selectedId, showToast, syncCrmCounts]);

  const sendEmail = useCallback(async (payload: { to: string[]; cc?: string[]; subject: string; body: string }) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await sendCrmEmail('solicitud', caseId, payload);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Correo enviado', 'mdi-email-check-outline');
  }, [selectedId, showToast, syncCrmCounts]);

  const createProposal = useCallback(async (payload: Record<string, unknown>) => {
    const caseId = selectedId;
    if (caseId == null) return;
    const updated = await storeCrmProposal('solicitud', caseId, payload);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Borrador de propuesta creado', 'mdi-file-document-check-outline');
  }, [selectedId, showToast, syncCrmCounts]);

  const sendProposalEmail = useCallback(async (proposalId: number, to: string) => {
    const caseId = selectedId;
    if (caseId == null) return;
    await sendCrmProposalEmail(proposalId, {
      to,
      subject: `Propuesta #${proposalId}`,
      attach_pdf: true,
    });
    const updated = await fetchCrmCase('solicitud', caseId);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Propuesta enviada por correo', 'mdi-email-check-outline');
  }, [selectedId, showToast, syncCrmCounts]);

  const sendProposalWhatsapp = useCallback(async (proposalId: number) => {
    const caseId = selectedId;
    if (caseId == null) return;
    await sendCrmProposalWhatsapp(proposalId, { solicitud_id: caseId });
    const updated = await fetchCrmCase('solicitud', caseId);
    syncCrmCounts(caseId, updated);
    if (selectedIdRef.current !== caseId || updated.sourceId !== caseId) return;
    setCrmCase(updated);
    showToast('Propuesta enviada por WhatsApp', 'mdi-whatsapp');
  }, [selectedId, showToast, syncCrmCounts]);

  const togglePreop = useCallback((id: number, idx: number) => {
    setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => {
      if (s.id !== id) return s;
      const preop = s.detalle.preop.map((p: { label: string; done: boolean }, i: number) => i === idx ? { ...p, done: !p.done } : p);
      return { ...s, detalle: { ...s.detalle, preop } };
    }));
  }, []);

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
  const selectedCrmCase = crmCase?.sourceId === selectedId ? crmCase : null;

  useEffect(() => {
    let cancelled = false;
    if (!selectedId || !selected) {
      setCrmCase(null);
      setCrmLoading(false);
      setCrmError(null);
      return () => { cancelled = true; };
    }

    setCrmCase(null);
    setCrmLoading(true);
    setCrmError(null);
    fetchCrmCase('solicitud', selectedId)
      .then((crm) => {
        if (!cancelled && selectedIdRef.current === selectedId && crm.sourceId === selectedId) setCrmCase(crm);
      })
      .catch(() => {
        if (!cancelled) {
          setCrmCase(null);
          setCrmError('No se pudo cargar el seguimiento CRM.');
        }
      })
      .finally(() => {
        if (!cancelled) setCrmLoading(false);
      });

    return () => { cancelled = true; };
  }, [selectedId, selected?.id]);

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

  useEffect(() => {
    if (prefacturaId == null) return;
    const sol = solicitudes.find((s: Solicitud) => s.id === prefacturaId);
    if (!sol) return;
    const hasFreshCoverage = sol.detalle.derivacion.tiene || sol.detalle.paciente.cedula !== '—';
    if (hasFreshCoverage) return;

    fetchDetalle(prefacturaId).then((detalle) => {
      setSolicitudes((list: Solicitud[]) => list.map((s: Solicitud) => s.id === prefacturaId ? { ...s, detalle } : s));
    }).catch(() => {
      // non-critical; prefactura keeps its empty state if the scraper/backend fails
    });
  }, [prefacturaId, solicitudes]);

  // Export (reuses V2 backend routes)
  const doExport = useCallback(async (format: 'excel' | 'pdf') => {
    const f = filtersRef.current;
    const body: Record<string, string> = {};
    if (f.date_from) body.date_from = f.date_from;
    if (f.date_to) body.date_to = f.date_to;
    if (f.afiliacion) body.afiliacion = f.afiliacion;
    if (f.doctor) body.doctor = f.doctor;
    if (f.search) body.search = f.search;
    const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    try {
      const res = await fetch(
        format === 'excel' ? '/v2/solicitudes/reportes/excel' : '/v2/solicitudes/reportes/pdf',
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify(body),
        },
      );
      if (!res.ok) { showToast('No se pudo generar el reporte', 'mdi-alert-circle-outline'); return; }
      const blob = await res.blob();
      const cd = res.headers.get('Content-Disposition') ?? '';
      const m = cd.match(/filename="([^"]+)"/);
      const filename = m ? m[1] : `solicitudes.${format === 'excel' ? 'xlsx' : 'pdf'}`;
      const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: filename });
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(a.href);
    } catch {
      showToast('No se pudo generar el reporte', 'mdi-alert-circle-outline');
    }
  }, [showToast]);

  // "Actualizado hace Xs" label — recomputed every 10s via tick
  const lastRefreshedLabel = useMemo(() => {
    void tick;
    if (refreshing) return 'Actualizando…';
    if (!lastRefreshed) return null;
    const secs = Math.round((Date.now() - lastRefreshed.getTime()) / 1000);
    if (secs < 5) return 'Actualizado ahora';
    if (secs < 60) return `Actualizado hace ${secs}s`;
    return `Actualizado hace ${Math.floor(secs / 60)}m`;
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lastRefreshed, refreshing, tick]);

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
      {/* ---- KPI row ---- */}
      <div className="kpi-row">
        <Kpi tone="total"   icon="mdi-clipboard-text-multiple-outline" value={metrics.total}   label="Solicitudes totales"    active={kpiFilter === ''}        onClick={() => setKpiFilter('')} />
        <Kpi tone="vencido" icon="mdi-alert-octagon-outline"           value={metrics.vencido} label="SLA vencido"            active={kpiFilter === 'vencido'} onClick={() => toggleKpi('vencido')} />
        <Kpi tone="critico" icon="mdi-clock-alert-outline"             value={metrics.critico} label="SLA crítico"            active={kpiFilter === 'critico'} onClick={() => toggleKpi('critico')} />
        <Kpi tone="docs"    icon="mdi-file-alert-outline"              value={metrics.docs}    label="Docs faltantes"         active={kpiFilter === 'docs'}    onClick={() => toggleKpi('docs')} />
        <Kpi tone="auth"    icon="mdi-shield-clock-outline"            value={metrics.auth}    label="Autorización pendiente" active={kpiFilter === 'auth'}    onClick={() => toggleKpi('auth')} />
        <Kpi tone="proposal" icon="mdi-file-document-edit-outline"     value={metrics.propuestas} label="Con propuesta"       active={kpiFilter === 'propuestas'} onClick={() => toggleKpi('propuestas')} />
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
        onExportExcel={() => void doExport('excel')}
        onExportPdf={() => void doExport('pdf')}
        lastRefreshedLabel={lastRefreshedLabel}
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
      {view === 'conciliacion' && !loading && (
        <ConciliacionView
          rows={filtered}
          filters={filters}
          preset={preset}
          kpiFilter={kpiFilter}
          onConfirm={confirmConcil}
        />
      )}

      {/* ---- Detail panel ---- */}
      <DetailPanel
        sol={selected}
        open={selectedId != null}
        onClose={() => setSelectedId(null)}
        onToggleStep={toggleStep}
        onAdvance={advance}
        onToggleTask={toggleTask}
        onAddTask={addTask}
        onAssignResponsible={assignResponsible}
        onAddContact={addContact}
        onRescrapeDerivacion={refreshDerivacion}
        onUploadDocument={uploadDocument}
        onSendCoverageMail={sendCoverage}
        crmCase={selectedCrmCase}
        crmLoading={crmLoading}
        crmError={crmError}
        onAddNote={addCrmNote}
        onDeleteNote={removeCrmNote}
        onSendWhatsapp={sendWhatsapp}
        onSendEmail={sendEmail}
        onCreateProposal={createProposal}
        onSendProposalEmail={sendProposalEmail}
        onSendProposalWhatsapp={sendProposalWhatsapp}
        onOpenPrefactura={(id) => setPrefacturaId(id)}
      />

      {/* ---- Prefactura modal ---- */}
      <PrefacturaModal
        sol={solicitudes.find((s: Solicitud) => s.id === prefacturaId) ?? null}
        open={prefacturaId != null}
        onClose={() => setPrefacturaId(null)}
        onTogglePreop={togglePreop}
        showToast={showToast}
        onRescrapeDerivacion={refreshDerivacion}
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
