// ============================================================
// MedForge · Solicitudes v3 — API layer + transformer
// ============================================================
import type {
  ApiKanbanResponse, ApiSolicitud, Filters, KanbanSlug,
  Solicitud, ChecklistStep, ChecklistProgress, Alert, Detalle,
} from './types';

// ---- Constants shared with the prototype ----------------------

export const COLUMNS: Array<{ slug: KanbanSlug; label: string; phase: string }> = [
  { slug: 'recibida',          label: 'Recibida',          phase: 'ingreso'   },
  { slug: 'llamado',           label: 'Turno llamado',     phase: 'ingreso'   },
  { slug: 'revision-codigos',  label: 'Revisión códigos',  phase: 'validacion'},
  { slug: 'espera-documentos', label: 'Documentación',     phase: 'validacion'},
  { slug: 'apto-oftalmologo',  label: 'Apto oftalmólogo',  phase: 'aptitud'   },
  { slug: 'apto-anestesia',    label: 'Apto anestesia',    phase: 'aptitud'   },
  { slug: 'listo-para-agenda', label: 'Listo p/ agenda',   phase: 'agenda'    },
  { slug: 'programada',        label: 'Programada',        phase: 'agenda'    },
  { slug: 'completado',        label: 'Completado',        phase: 'agenda'    },
];

export const PHASES = [
  { key: 'ingreso',    label: 'Ingreso',           icon: 'mdi-bullhorn-outline'        },
  { key: 'validacion', label: 'Validación & docs', icon: 'mdi-file-search-outline'     },
  { key: 'aptitud',    label: 'Aptitud clínica',   icon: 'mdi-stethoscope'             },
  { key: 'agenda',     label: 'Agenda quirúrgica', icon: 'mdi-calendar-check-outline'  },
];

const CHECK_STEPS = [
  { slug: 'recibida',          label: 'Solicitud recibida'   },
  { slug: 'llamado',           label: 'Turno llamado'        },
  { slug: 'revision-codigos',  label: 'Códigos validados'    },
  { slug: 'espera-documentos', label: 'Documentos completos' },
  { slug: 'apto-oftalmologo',  label: 'Apto oftalmólogo'     },
  { slug: 'apto-anestesia',    label: 'Apto anestesia'       },
  { slug: 'listo-para-agenda', label: 'Listo para agenda'    },
  { slug: 'programada',        label: 'Cirugía programada'   },
];

const AFILIACIONES: Record<string, { label: string; tone: string }> = {
  'IESS':       { label: 'IESS',              tone: 'visita'      },
  'ISSFA':      { label: 'ISSFA',             tone: 'consulta'    },
  'ISSPOL':     { label: 'ISSPOL',            tone: 'optometria'  },
  'MSP':        { label: 'MSP — Red Pública', tone: 'examen'      },
  'Particular': { label: 'Particular',        tone: 'neutral'     },
  'Seguro':     { label: 'Seguro privado',    tone: 'cirugia'     },
};

const PROCEDURES: Array<{ full: string; short: string }> = [
  { full: 'FACOEMULSIFICACIÓN + LIO MONOFOCAL',              short: 'Faco + LIO'            },
  { full: 'FACOEMULSIFICACIÓN + LIO TÓRICA',                 short: 'Faco + LIO tórica'     },
  { full: 'VITRECTOMÍA POSTERIOR (VPP) 23G',                 short: 'Vitrectomía (VPP)'     },
  { full: 'INYECCIÓN INTRAVÍTREA — ANTIANGIOGÉNICO',         short: 'Inyección intravítrea' },
  { full: 'EXÉRESIS DE PTERIGION + INJERTO CONJUNTIVAL',     short: 'Pterigion + injerto'   },
  { full: 'CAPSULOTOMÍA YAG LÁSER',                          short: 'Capsulotomía YAG'      },
  { full: 'TRABECULECTOMÍA',                                 short: 'Trabeculectomía'       },
  { full: 'BLEFAROPLASTIA SUPERIOR FUNCIONAL',               short: 'Blefaroplastia'        },
  { full: 'EXÉRESIS DE CHALAZIÓN',                           short: 'Chalazión'             },
  { full: 'CIRUGÍA DE ESTRABISMO — 2 MÚSCULOS',             short: 'Estrabismo (2 músc.)'  },
  { full: 'DACRIOCISTORRINOSTOMÍA (DCR)',                    short: 'Dacriocistorrinostomía'},
  { full: 'TRASPLANTE DE CÓRNEA (QUERATOPLASTIA)',           short: 'Trasplante de córnea'  },
];

// ---- Helpers --------------------------------------------------

function initials(name: string): string {
  return (name || '')
    .split(/\s+/)
    .filter((w) => /[A-Za-zÁÉÍÓÚÑ]/.test(w))
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase();
}

function buildChecklist(estadoSlug: string): { checklist: ChecklistStep[]; checklist_progress: ChecklistProgress } {
  const idx = COLUMNS.findIndex((c) => c.slug === estadoSlug);
  const list: ChecklistStep[] = CHECK_STEPS.map((step, i) => ({
    slug: step.slug,
    label: step.label,
    completed: i <= idx,
    can_toggle: i === idx + 1 || i === idx,
  }));
  const firstPending = list.find((s) => !s.completed);
  const completed = list.filter((s) => s.completed).length;
  const total = list.length;
  return {
    checklist: list,
    checklist_progress: {
      completed,
      total,
      percent: Math.round((completed / total) * 100),
      next_label: firstPending ? firstPending.label : 'Completado',
    },
  };
}

function resolveAfiliacion(raw: string | undefined): { afiliacion: string; afiliacion_label: string; afiliacion_tone: string } {
  if (!raw) return { afiliacion: 'Particular', afiliacion_label: 'Particular', afiliacion_tone: 'neutral' };
  const entry = AFILIACIONES[raw];
  if (entry) return { afiliacion: raw, afiliacion_label: entry.label, afiliacion_tone: entry.tone };
  // Try partial match
  for (const [key, val] of Object.entries(AFILIACIONES)) {
    if (raw.toUpperCase().includes(key.toUpperCase())) {
      return { afiliacion: key, afiliacion_label: val.label, afiliacion_tone: val.tone };
    }
  }
  return { afiliacion: raw, afiliacion_label: raw, afiliacion_tone: 'neutral' };
}

function resolveProcedimiento(raw: string | undefined): { procedimiento: string; procedimiento_short: string } {
  if (!raw) return { procedimiento: '—', procedimiento_short: '—' };
  const up = raw.toUpperCase().trim();
  const match = PROCEDURES.find((p) => p.full.toUpperCase() === up || up.includes(p.full.substring(0, 10).toUpperCase()));
  if (match) return { procedimiento: match.full, procedimiento_short: match.short };
  return { procedimiento: raw, procedimiento_short: raw.length > 25 ? raw.substring(0, 25) + '…' : raw };
}

function buildAlerts(raw: ApiSolicitud, estadoSlug: string): Alert[] {
  const alerts: Alert[] = [];
  // API uses alert_documentos_faltantes / alert_docs (legacy)
  if (raw.alert_documentos_faltantes || raw.alert_docs || estadoSlug === 'espera-documentos') {
    alerts.push({ key: 'docs', label: 'Documentos faltantes', icon: 'mdi-file-alert-outline', tone: 'warning' });
  }
  // API uses alert_autorizacion_pendiente / alert_auth (legacy)
  if (raw.alert_autorizacion_pendiente || raw.alert_auth) {
    alerts.push({ key: 'auth', label: 'Autorización pendiente', icon: 'mdi-shield-clock-outline', tone: 'danger' });
  }
  if (raw.alert_derivacion_vencida || raw.alert_derivacion_por_vencer) {
    alerts.push({ key: 'derivacion', label: 'Derivación por vencer', icon: 'mdi-alert-circle-outline', tone: 'danger' });
  }
  if (raw.alert_exam) {
    alerts.push({ key: 'exam', label: 'Exámenes por vencer', icon: 'mdi-flask-empty-outline', tone: 'warning' });
  }
  return alerts;
}

function emptyDetalle(): Detalle {
  return {
    paciente: { edad: 0, sexo: '—', cedula: '—', direccion: '—' },
    diagnosticos: [],
    derivacion: {
      tiene: false, cod: null, aseguradora: '—', plan: '—',
      dias_vigencia: null, vencida: false, archivo: false, autorizacion_pendiente: false,
    },
    preop: [],
    notas: [],
    tareas: [],
    propuestas: [],
    adjuntos: [],
    examen: { av_od: '—', av_oi: '—', pio_od: 0, pio_oi: 0, plan: '—' },
    agenda: { sala: '—', fecha: null, duracion: 30, anestesia: '—' },
  };
}

// ---- Main transformer -----------------------------------------

export function buildSolicitudFromApi(raw: ApiSolicitud, estadoSlug: KanbanSlug): Solicitud {
  const col = COLUMNS.find((c) => c.slug === estadoSlug) ?? COLUMNS[0];
  const name = raw.full_name ?? raw.paciente ?? 'Paciente';
  const { afiliacion, afiliacion_label, afiliacion_tone } = resolveAfiliacion(raw.afiliacion);
  const { procedimiento, procedimiento_short } = resolveProcedimiento(raw.procedimiento);
  const { checklist, checklist_progress } = buildChecklist(estadoSlug);
  const alerts = buildAlerts(raw, estadoSlug);
  const showTurno = estadoSlug === 'recibida' || estadoSlug === 'llamado';

  let sla_status: 'ok' | 'critico' | 'vencido' = 'ok';
  if (raw.sla_status === 'vencido') sla_status = 'vencido';
  else if (raw.sla_status === 'critico' || raw.sla_status === 'advertencia') sla_status = 'critico';

  return {
    id: raw.id,
    form_id: raw.form_id ?? `PD-${raw.id}`,
    hc_number: raw.hc_number ?? String(raw.id),
    full_name: name,
    avatar_initials: initials(name),
    doctor: raw.doctor ?? '—',
    afiliacion,
    afiliacion_label,
    afiliacion_tone,
    procedimiento,
    procedimiento_short,
    ojo: (raw.ojo as string | undefined) ?? 'OD',
    prioridad: raw.prioridad === 'urgente' ? 'urgente' : 'normal',
    estado: estadoSlug,
    estado_label: col.label,
    fecha: raw.fecha ?? raw.created_at ?? new Date().toISOString(),
    sede: (raw.sede as string | undefined) ?? '—',
    observacion: (raw.observacion as string | undefined) ?? '',
    sla_status,
    sla_hours_remaining: (raw.sla_hours_remaining as number | null | undefined) ?? null,
    sla_label: raw.sla_label ?? (sla_status === 'ok' ? 'En tiempo' : sla_status === 'critico' ? 'Vence pronto' : 'SLA vencido'),
    checklist,
    checklist_progress,
    protocolo_confirmado: null,
    protocolo_posterior_compatible: null,
    crm: {
      // API sends crm_responsable_nombre; crm_responsable is legacy fallback
      responsable: (raw.crm_responsable_nombre as string | undefined) ?? (raw.crm_responsable as string | undefined) ?? 'Coordinación',
      telefono: (raw.crm_responsable_telefono as string | undefined) ?? (raw.crm_telefono as string | undefined) ?? '—',
      email: (raw.crm_responsable_email as string | undefined) ?? (raw.crm_email as string | undefined) ?? '—',
      fuente: raw.crm_fuente ?? '—',
      notas: (raw.crm_total_notas as number | undefined) ?? (raw.crm_notas as number | undefined) ?? 0,
      adjuntos: (raw.crm_total_adjuntos as number | undefined) ?? (raw.crm_adjuntos as number | undefined) ?? 0,
      tareas_pendientes: raw.crm_tareas_pendientes ?? 0,
      tareas_total: raw.crm_tareas_total ?? 0,
      proximo_vencimiento: raw.crm_proximo_vencimiento ?? null,
      pipeline: col.label,
    },
    alerts,
    turno: showTurno ? (raw.turno ?? null) : null,
    detalle: emptyDetalle(),
  };
}

// ---- Network helpers ------------------------------------------

function csrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function jsonFetch<T>(url: string, init?: RequestInit): Promise<T> {
  const res = await fetch(url, {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    ...init,
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json() as Promise<T>;
}

// ---- Public API calls -----------------------------------------

export async function fetchKanbanData(filters?: Partial<Filters>): Promise<{
  byColumn: Record<KanbanSlug, Solicitud[]>;
  afiliaciones: string[];
  doctores: string[];
}> {
  const params = new URLSearchParams();
  if (filters) {
    for (const [k, v] of Object.entries(filters)) {
      if (v) params.set(k, v);
    }
  }
  const qs = params.toString();
  const raw = await jsonFetch<ApiKanbanResponse>(`/v2/solicitudes/kanban-data${qs ? '?' + qs : ''}`);

  const byColumn: Record<KanbanSlug, Solicitud[]> = {} as Record<KanbanSlug, Solicitud[]>;
  COLUMNS.forEach((col) => { byColumn[col.slug] = []; });

  // API returns a flat array with kanban_estado per item; legacy may return keyed object
  const rawData = raw.data;
  if (Array.isArray(rawData)) {
    for (const item of rawData) {
      const slug = (item.kanban_estado ?? item.estado ?? 'recibida') as KanbanSlug;
      const col = COLUMNS.find((c) => c.slug === slug) ?? COLUMNS[0];
      if (!byColumn[col.slug]) byColumn[col.slug] = [];
      byColumn[col.slug].push(buildSolicitudFromApi(item, col.slug));
    }
  } else {
    for (const col of COLUMNS) {
      const items: ApiSolicitud[] = (rawData as Record<string, ApiSolicitud[]>)?.[col.slug] ?? [];
      byColumn[col.slug] = items.map((item) => buildSolicitudFromApi(item, col.slug));
    }
  }

  const allSolicitudes = Object.values(byColumn).flat();
  const afiliaciones = [...new Set(allSolicitudes.map((s) => s.afiliacion).filter(Boolean))];
  const doctores = [...new Set(allSolicitudes.map((s) => s.doctor).filter((d) => d !== '—'))];

  return { byColumn, afiliaciones, doctores };
}

export async function updateEstado(id: number, nuevoEstado: KanbanSlug): Promise<void> {
  await jsonFetch<unknown>('/v2/solicitudes/actualizar-estado', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify({ id, estado: nuevoEstado }),
  });
}

// ---- State rebuild (for optimistic updates) -------------------

export function rebuildState(sol: Solicitud, newSlug: KanbanSlug): Solicitud {
  const col = COLUMNS.find((c) => c.slug === newSlug) ?? COLUMNS[0];
  const { checklist, checklist_progress } = buildChecklist(newSlug);
  const sla = newSlug === 'completado'
    ? { sla_status: 'ok' as const, sla_hours_remaining: null, sla_label: 'Cerrada' }
    : { sla_status: sol.sla_status, sla_hours_remaining: sol.sla_hours_remaining, sla_label: sol.sla_label };
  const showTurno = newSlug === 'recibida' || newSlug === 'llamado';
  return {
    ...sol, ...sla,
    estado: newSlug,
    estado_label: col.label,
    turno: showTurno ? sol.turno : null,
    checklist,
    checklist_progress,
  };
}
