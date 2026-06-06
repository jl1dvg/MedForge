// ============================================================
// MedForge · Solicitudes v3 — API layer + transformer
// ============================================================
import type {
  ApiKanbanResponse, ApiSolicitud, Filters, KanbanSlug,
  Solicitud, ChecklistStep, ChecklistProgress, Alert, Detalle,
  CrmCaseState, CrmCaseProposal, CrmCaseProposalItem,
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

function sortEs(a: string, b: string): number {
  return a.localeCompare(b, 'es', { sensitivity: 'base' });
}

function cleanString(value: unknown): string | null {
  if (value == null) return null;
  const text = String(value).trim();
  return text === '' ? null : text;
}

function stringValue(value: unknown, fallback = ''): string {
  return cleanString(value) ?? fallback;
}

function stringOrNull(value: unknown): string | null {
  return cleanString(value);
}

function numberValue(value: unknown, fallback = 0): number {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

function arrayValue<T = unknown>(value: unknown): T[] {
  return Array.isArray(value) ? value as T[] : [];
}

function objectValue(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};
}

function proposalStatusLabel(status: unknown): string {
  const value = stringValue(status, 'draft').toLowerCase();
  if (['sent', 'enviada', 'enviado'].includes(value)) return 'Enviada';
  if (['accepted', 'aceptada', 'aceptado'].includes(value)) return 'Aceptada';
  if (['rejected', 'rechazada', 'cancelled', 'cancelada', 'anulada'].includes(value)) return 'Rechazada';
  return 'Borrador';
}

function mapProposalItem(raw: any): CrmCaseProposalItem {
  const quantity = numberValue(raw?.quantity ?? raw?.cantidad, 1);
  const unitPrice = numberValue(raw?.unit_price ?? raw?.unitPrice ?? raw?.precio ?? raw?.valor, 0);
  const discountPercent = numberValue(raw?.discount_percent ?? raw?.discountPercent ?? raw?.descuento, 0);
  const rawTotal = raw?.total ?? raw?.line_total ?? raw?.subtotal;
  const computedTotal = quantity * unitPrice * (1 - discountPercent / 100);

  return {
    id: numberValue(raw?.id),
    code: stringValue(raw?.code ?? raw?.codigo ?? raw?.cod ?? raw?.catalog_code),
    description: stringValue(raw?.description ?? raw?.descripcion ?? raw?.desc, 'Item de propuesta'),
    quantity,
    unitPrice,
    discountPercent,
    total: rawTotal == null ? computedTotal : numberValue(rawTotal, computedTotal),
  };
}

function mapProposal(raw: any): CrmCaseProposal {
  const id = numberValue(raw?.id);
  const items = arrayValue<any>(raw?.items).map(mapProposalItem);
  const subtotal = raw?.subtotal == null
    ? items.reduce((sum, item) => sum + item.total, 0)
    : numberValue(raw?.subtotal);
  const taxTotal = numberValue(raw?.tax_total ?? raw?.taxTotal ?? raw?.iva);
  const total = raw?.total == null ? subtotal + taxTotal : numberValue(raw?.total);

  return {
    id,
    number: stringValue(raw?.proposal_number ?? raw?.number ?? raw?.numero, id > 0 ? `#${id}` : 'Propuesta'),
    title: stringValue(raw?.title ?? raw?.titulo ?? raw?.name ?? raw?.nombre, 'Propuesta sin título'),
    status: stringValue(raw?.status ?? raw?.estado, 'draft'),
    statusLabel: proposalStatusLabel(raw?.status ?? raw?.estado),
    validUntil: stringOrNull(raw?.valid_until ?? raw?.validUntil ?? raw?.vigencia),
    subtotal,
    taxTotal,
    total,
    currency: stringValue(raw?.currency, 'USD'),
    itemsCount: numberValue(raw?.items_count ?? raw?.itemsCount, items.length),
    pdfUrl: id > 0 ? `/v3/crm/proposals/${id}/pdf` : stringValue(raw?.pdf_url ?? raw?.pdfUrl),
    publicUrl: stringOrNull(raw?.public_url ?? raw?.publicUrl),
    items,
  };
}

function normalizeAseguradoraLabel(...values: Array<unknown>): string {
  const raw = values.map(cleanString).find(Boolean) ?? 'Particular';
  const text = raw.replace(/\s+/g, ' ').trim();
  const parts = text.split(/\s+-\s+/).map((part) => part.trim()).filter(Boolean);

  if (parts.length >= 2) {
    const firstLooksLikeCode = /^[A-Z]{2,}\d{0,4}$/i.test(parts[0]) || /^(PAR|IESS|ISSFA|ISSPOL|MSP)$/i.test(parts[0]);
    if (firstLooksLikeCode) return parts[1];
  }

  return text
    .replace(/\s+\([^)]*\)\s*/g, ' ')
    .replace(/\s+NIVEL\s+\w+$/i, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizePlanSeguroLabel(plan: unknown, afiliacion: unknown, empresa: string): string {
  const rawPlan = cleanString(plan);
  if (rawPlan && rawPlan !== empresa) {
    const planParts = rawPlan.split(/\s+-\s+/).map((part) => part.trim()).filter(Boolean);
    return planParts.length >= 3 ? planParts.slice(2).join(' - ') : rawPlan;
  }

  const text = cleanString(afiliacion);
  if (!text) return '—';
  const parts = text.split(/\s+-\s+/).map((part) => part.trim()).filter(Boolean);
  if (parts.length >= 3) return parts.slice(2).join(' - ');
  if (parts.length === 2) return parts[1];
  return text;
}

function cleanHtml(value: unknown): string {
  return cleanString(value)?.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim() ?? '';
}

function daysUntil(dateValue: unknown): number | null {
  const raw = cleanString(dateValue);
  if (!raw) return null;
  const target = new Date(raw);
  if (Number.isNaN(target.getTime())) return null;
  const today = new Date();
  target.setHours(0, 0, 0, 0);
  today.setHours(0, 0, 0, 0);
  return Math.ceil((target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function minutesBetween(start: unknown, end: unknown): number | null {
  const s = cleanString(start);
  const e = cleanString(end);
  if (!s || !e) return null;
  const startDate = new Date(s);
  const endDate = new Date(e);
  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return null;
  const diff = Math.round((endDate.getTime() - startDate.getTime()) / (1000 * 60));
  return diff > 0 ? diff : null;
}

function labelFromSlug(value: unknown): string {
  const raw = cleanString(value);
  if (!raw) return 'Checklist operativo';
  return raw
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function deriveArchivoHref(deriv: Record<string, unknown>, derivacionTab: Record<string, unknown>): string | null {
  const actions = (derivacionTab.actions ?? {}) as Record<string, unknown>;
  const download = (actions.download_pdf ?? {}) as Record<string, unknown>;
  const href = cleanString(download.href);
  if (href) return href;

  const id = cleanString(deriv.id) ?? cleanString(deriv.derivacion_id);
  if (id) return `/derivaciones/archivo/${id}`;

  const path = cleanString(deriv.archivo_derivacion_path) ?? cleanString(deriv.archivo_path) ?? cleanString(deriv.archivo);
  if (!path) return null;
  if (/^https?:\/\//i.test(path) || path.startsWith('/')) return path;
  return `/${path.replace(/^\/+/, '')}`;
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
    paciente: { edad: 0, sexo: '—', cedula: '—', direccion: '—', telefono: '—', fecha_nacimiento: null },
    diagnosticos: [],
    derivacion: {
      tiene: false, cod: null, aseguradora: '—', plan: '—',
      fecha_registro: null, fecha_vigencia: null, vigencia_text: 'Sin derivación registrada', vigencia_label: 'Sin derivación',
      dias_vigencia: null, vencida: false, archivo: false, archivo_href: null, autorizacion_pendiente: false,
    },
    preop: [],
    notas: [],
    tareas: [],
    propuestas: [],
    adjuntos: [],
    examen: { av_od: '—', av_oi: '—', pio_od: 0, pio_oi: 0, plan: '—', examen_fisico: '' },
    agenda: { sala: '—', fecha: null, fecha_fin: null, duracion: 30, anestesia: '—', doctor: '—', origen: 'Sin agenda', sigcenter_agenda_id: null },
  };
}

// ---- Main transformer -----------------------------------------

export function buildSolicitudFromApi(raw: ApiSolicitud, estadoSlug: KanbanSlug): Solicitud {
  const col = COLUMNS.find((c) => c.slug === estadoSlug) ?? COLUMNS[0];
  const name = raw.full_name ?? raw.paciente ?? 'Paciente';
  const { afiliacion, afiliacion_label, afiliacion_tone } = resolveAfiliacion(raw.afiliacion);
  const empresa_seguro = normalizeAseguradoraLabel(raw.empresa_seguro, raw.afiliacion, afiliacion_label);
  const plan_seguro = normalizePlanSeguroLabel(raw.plan_seguro, raw.afiliacion, empresa_seguro);
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
    empresa_seguro,
    plan_seguro,
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

// ---- CRM V3 case contract -------------------------------------

export function mapCrmCasePayload(raw: any): CrmCaseState {
  return {
    caseId: stringValue(raw?.case?.case_id),
    sourceType: stringValue(raw?.case?.source_type),
    sourceId: numberValue(raw?.case?.source_id),
    responsibleName: stringValue(raw?.crm?.responsible_name, 'Coordinación'),
    source: stringValue(raw?.crm?.source, '—'),
    insurancePlan: stringValue(raw?.crm?.insurance_plan, '—'),
    contacts: {
      primaryPhone: stringValue(raw?.contacts?.primary_phone, '—'),
      alternatePhones: arrayValue<unknown>(raw?.contacts?.alternate_phones).map((phone) => stringValue(phone)).filter(Boolean),
      primaryEmail: stringValue(raw?.contacts?.primary_email, '—'),
      alternateEmails: arrayValue<unknown>(raw?.contacts?.alternate_emails).map((email) => stringValue(email)).filter(Boolean),
    },
    notes: arrayValue<any>(raw?.notes).map((note) => ({
      id: numberValue(note?.id),
      body: stringValue(note?.body),
      authorName: stringValue(note?.author_name, 'Usuario'),
      createdAt: stringValue(note?.created_at),
      canDelete: note?.can_delete === true,
    })),
    tasks: arrayValue<any>(raw?.tasks).map((task) => ({
      id: numberValue(task?.id),
      title: stringValue(task?.title),
      status: stringValue(task?.status, 'pending'),
      priority: stringValue(task?.priority, 'normal'),
      assignedTo: task?.assigned_to == null ? null : numberValue(task.assigned_to),
      dueAt: stringOrNull(task?.due_at),
    })),
    activity: arrayValue<any>(raw?.activity).map((activity) => ({
      id: stringValue(activity?.id),
      type: stringValue(activity?.type),
      occurredAt: stringValue(activity?.occurred_at),
      author: stringValue(activity?.author, 'Sistema'),
      description: stringValue(activity?.description),
      reference: objectValue(activity?.reference),
    })),
    proposals: arrayValue<any>(raw?.proposals).map(mapProposal).filter((proposal) => proposal.id > 0),
    documents: arrayValue(raw?.documents),
  };
}

async function crmJson(url: string, init: RequestInit = {}): Promise<CrmCaseState> {
  const response = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
      ...(init.headers ?? {}),
    },
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok || body.success === false) {
    throw new Error(body.error || body.message || 'No se pudo completar la acción');
  }
  return mapCrmCasePayload(body.data);
}

async function crmDataJson<T>(url: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken(),
      ...(init.headers ?? {}),
    },
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok || body.success === false) {
    throw new Error(body.error || body.message || 'No se pudo completar la acción');
  }

  return body.data as T;
}

function crmCaseUrl(sourceType: string, sourceId: number): string {
  return `/v3/crm/cases/${encodeURIComponent(sourceType)}/${sourceId}`;
}

export async function fetchCrmCase(sourceType: string, sourceId: number): Promise<CrmCaseState> {
  return crmJson(crmCaseUrl(sourceType, sourceId));
}

export async function createCrmNote(sourceType: string, sourceId: number, body: string): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/notes`, {
    method: 'POST',
    body: JSON.stringify({ body }),
  });
}

export async function deleteCrmNote(sourceType: string, sourceId: number, noteId: number): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/notes/${noteId}`, {
    method: 'DELETE',
  });
}

export async function createCrmTask(
  sourceType: string,
  sourceId: number,
  payload: { title: string; priority: string; due_at?: string | null },
): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/tasks`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function updateCrmTask(
  sourceType: string,
  sourceId: number,
  taskId: number,
  payload: { status?: string; title?: string; priority?: string; due_at?: string | null },
): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/tasks/${taskId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
}

export async function sendCrmWhatsapp(
  sourceType: string,
  sourceId: number,
  payload: { recipients: string[]; message: string },
): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/whatsapp`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function sendCrmEmail(
  sourceType: string,
  sourceId: number,
  payload: { to: string[]; cc?: string[]; subject: string; body: string },
): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/email`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function storeCrmProposal(
  sourceType: string,
  sourceId: number,
  payload: Record<string, unknown>,
): Promise<CrmCaseState> {
  return crmJson(`${crmCaseUrl(sourceType, sourceId)}/proposals`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function crmProposalPdfUrl(proposalId: number): string {
  return `/v3/crm/proposals/${encodeURIComponent(String(proposalId))}/pdf`;
}

export async function sendCrmProposalEmail(
  proposalId: number,
  payload: { to: string; subject?: string; body?: string; attach_pdf?: boolean },
): Promise<Record<string, unknown>> {
  return crmDataJson<Record<string, unknown>>(`/v3/crm/proposals/${encodeURIComponent(String(proposalId))}/send-email`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function sendCrmProposalWhatsapp(
  proposalId: number,
  payload: { solicitud_id: number; message?: string },
): Promise<Record<string, unknown>> {
  return crmDataJson<Record<string, unknown>>(`/v3/crm/proposals/${encodeURIComponent(String(proposalId))}/send-whatsapp`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function searchCrmCatalogCodes(query: string, affiliation: string): Promise<Array<Record<string, unknown>>> {
  const params = new URLSearchParams({
    q: query,
    affiliation,
  });

  return crmDataJson<Array<Record<string, unknown>>>(`/v3/crm/catalog/codes?${params.toString()}`);
}

export async function searchCrmCatalogPackages(query: string, affiliation: string): Promise<Array<Record<string, unknown>>> {
  const params = new URLSearchParams({
    q: query,
    affiliation,
  });

  return crmDataJson<Array<Record<string, unknown>>>(`/v3/crm/catalog/packages?${params.toString()}`);
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
  const afiliaciones = [...new Set(allSolicitudes.map((s) => s.empresa_seguro).filter(Boolean))].sort(sortEs);
  const doctores = [...new Set(allSolicitudes.map((s) => s.doctor).filter((d) => d !== '—'))].sort(sortEs);

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

// ---- Detalle completo (lazy-loaded when opening a card) -------

type DetallePayload = {
  detalle?: Record<string, unknown>;
  notas?: Array<{ nota?: string; texto?: string; autor?: string; created_at?: string }>;
  tareas?: Array<{ titulo?: string; descripcion?: string; asignado?: string; responsable_nombre?: string; fecha_vencimiento?: string; fecha?: string; prioridad?: string; estado?: string; completed_at?: string | null; done?: boolean; slug?: string }>;
  checklist?: Array<{ slug?: string; label?: string; titulo?: string; estado?: string; completed?: boolean; done?: boolean; completed_at?: string | null }>;
  propuestas?: Array<Record<string, unknown>>;
  adjuntos?: Array<{ nombre?: string; name?: string; mime_type?: string; size?: string; created_at?: string }>;
  paciente?: Record<string, unknown>;
  diagnostico?: Array<{ dx_code?: string; codigo?: string; codigo_cie?: string; cie?: string; descripcion?: string; desc?: string; diagnostico?: string; lateralidad?: string }> | { dx_code?: string; codigo?: string; codigo_cie?: string; cie?: string; descripcion?: string; desc?: string; diagnostico?: string; lateralidad?: string };
  diagnosticos?: Array<{ dx_code?: string; codigo?: string; codigo_cie?: string; cie?: string; descripcion?: string; desc?: string; diagnostico?: string; lateralidad?: string }>;
  consulta?: Record<string, unknown>;
  derivacion?: Record<string, unknown>;
  derivacion_tab?: Record<string, unknown>;
  prefactura?: Record<string, unknown>;
  bloqueos_agenda?: Array<Record<string, unknown>>;
};

export function mapDetalleResponse(d: DetallePayload): Detalle {
  const pac = d.paciente ?? {};
  const sol = d.prefactura ?? d.detalle ?? {};
  const detalle = d.detalle ?? {};
  const cons = d.consulta ?? {};
  const deriv = d.derivacion ?? {};
  const derivacionTab = d.derivacion_tab ?? {};

  const notas = (d.notas ?? []).map((n) => ({
    txt: (n.nota ?? n.texto ?? '') as string,
    by: (n.autor ?? 'Sistema') as string,
    at: (n.created_at ?? '') as string,
  }));

  const tareas = (d.tareas ?? []).map((t) => ({
    titulo: (t.titulo ?? t.descripcion ?? '') as string,
    asignado: (t.asignado ?? t.responsable_nombre ?? '—') as string,
    fecha: (t.fecha_vencimiento ?? t.fecha ?? '') as string,
    prioridad: (t.prioridad ?? 'normal') as string,
    done: t.done === true || t.estado === 'completada' || t.completed_at != null,
  }));

  const adjuntos = (d.adjuntos ?? []).map((a) => {
    const mime = (a.mime_type ?? '') as string;
    const icon = mime.includes('pdf') ? 'mdi-file-pdf-box' : mime.includes('image') ? 'mdi-image' : 'mdi-paperclip';
    return {
      nombre: (a.nombre ?? a.name ?? 'Archivo') as string,
      icon,
      peso: (a.size ?? '—') as string,
      at: (a.created_at ?? '') as string,
    };
  });

  const rawDiagnosticos = d.diagnosticos?.length
    ? d.diagnosticos
    : Array.isArray(d.diagnostico)
      ? d.diagnostico
      : d.diagnostico
        ? [d.diagnostico]
        : [];
  const diagnosticos = rawDiagnosticos
    .map((dx) => {
      const cie = cleanString(dx.dx_code) ?? cleanString(dx.codigo) ?? cleanString(dx.codigo_cie) ?? cleanString(dx.cie) ?? '—';
      const desc = cleanString(dx.descripcion) ?? cleanString(dx.desc) ?? cleanString(dx.diagnostico) ?? '—';
      const lateralidad = cleanString(dx.lateralidad);
      return {
        cie,
        desc: lateralidad ? `${desc} · ${lateralidad}` : desc,
      };
    })
    .filter((dx) => dx.cie !== '—' || dx.desc !== '—');

  const propuestas = (d.propuestas ?? []).map((p) => {
    const items = (p.items as Array<Record<string, unknown>> ?? []).map((i) => ({
      cod: String(i.cod ?? i.codigo ?? ''),
      desc: String(i.desc ?? i.descripcion ?? ''),
      cant: Number(i.cant ?? i.cantidad ?? 1),
      valor: Number(i.valor ?? i.precio ?? 0),
    }));
    const subtotal = items.reduce((s, i) => s + i.cant * i.valor, 0);
    return {
      titulo: String(p.titulo ?? p.nombre ?? 'Propuesta'),
      estado: String(p.estado ?? 'borrador'),
      vigencia: String(p.vigencia ?? p.fecha_vencimiento ?? '—'),
      items,
      subtotal,
      iva: subtotal * 0.15,
      total: subtotal * 1.15,
    };
  });

  const fechaNac = pac.fecha_nacimiento as string | null | undefined;
  let edad = 0;
  if (fechaNac) {
    const diff = Date.now() - new Date(fechaNac).getTime();
    edad = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
  }

  const codDerivacion = cleanString(deriv.cod_derivacion)
    ?? cleanString(deriv.codigo_derivacion)
    ?? cleanString(deriv.cod);
  const fechaVigencia = cleanString(deriv.fecha_vigencia);
  const fechaRegistro = cleanString(deriv.fecha_registro) ?? cleanString(deriv.created_at);
  const archivoHref = deriveArchivoHref(deriv, derivacionTab);
  const diasVigencia = deriv.dias_vigencia != null ? Number(deriv.dias_vigencia) : daysUntil(fechaVigencia);
  const actions = (derivacionTab.actions ?? {}) as Record<string, unknown>;
  const authorization = (actions.authorization ?? {}) as Record<string, unknown>;
  const vigencia = (derivacionTab.vigencia ?? {}) as Record<string, unknown>;
  const vigenciaBadge = (vigencia.badge ?? {}) as Record<string, unknown>;
  const vencida = deriv.vencida != null
    ? !!deriv.vencida
    : deriv.estado === 'vencida' || (diasVigencia != null && diasVigencia < 0);
  const vigenciaText = cleanHtml(vigencia.text)
    || (fechaVigencia ? (vencida ? `Vencida: ${fechaVigencia}` : `Vigente hasta ${fechaVigencia}`) : 'Sin fecha de vigencia registrada');

  const checklistRows = d.checklist ?? [];
  const preop = checklistRows.map((row) => ({
    slug: cleanString(row.slug) ?? undefined,
    label: cleanString(row.label) ?? cleanString(row.titulo) ?? labelFromSlug(row.slug),
    done: row.done === true || row.completed === true || row.estado === 'completada' || row.completed_at != null,
  }));

  const bloqueo = d.bloqueos_agenda?.[0] ?? null;
  const sigcenterId = cleanString(sol.sigcenter_agenda_id) ?? cleanString(detalle.sigcenter_agenda_id);
  const fechaAgenda = cleanString(bloqueo?.fecha_inicio)
    ?? cleanString(sol.sigcenter_fecha_inicio)
    ?? cleanString(sol.fecha_programada)
    ?? cleanString(detalle.fecha_programada);
  const fechaFinAgenda = cleanString(bloqueo?.fecha_fin) ?? cleanString(sol.sigcenter_fecha_fin);
  const duracionAgenda = minutesBetween(fechaAgenda, fechaFinAgenda)
    ?? Number(sol.duracion ?? detalle.duracion ?? 30);

  return {
    paciente: {
      edad,
      sexo: String(pac.sexo ?? '—'),
      cedula: String(detalle.hc_number ?? sol.hc_number ?? pac.hc_number ?? '—'),
      direccion: cleanString(pac.direccion) ?? cleanString(pac.direccion_domicilio) ?? cleanString(pac.domicilio) ?? cleanString(pac.address) ?? '—',
      telefono: cleanString(detalle.crm_contacto_telefono) ?? cleanString(pac.celular) ?? '—',
      fecha_nacimiento: fechaNac ?? null,
    },
    diagnosticos,
    derivacion: {
      tiene: !!(codDerivacion ?? cleanString(deriv.id) ?? cleanString(deriv.derivacion_id) ?? archivoHref),
      cod: codDerivacion,
      aseguradora: String(sol.afiliacion ?? detalle.afiliacion ?? deriv.aseguradora ?? deriv.empresa ?? '—'),
      plan: String(cons.plan ?? deriv.plan ?? '—'),
      fecha_registro: fechaRegistro,
      fecha_vigencia: fechaVigencia,
      vigencia_text: vigenciaText,
      vigencia_label: cleanString(vigenciaBadge.texto) ?? (vencida ? 'Vencida' : fechaVigencia ? 'Vigente' : 'Sin vigencia'),
      dias_vigencia: Number.isFinite(diasVigencia) ? diasVigencia : null,
      vencida,
      archivo: !!archivoHref,
      archivo_href: archivoHref,
      autorizacion_pendiente: authorization.visible === true || !!deriv.autorizacion_pendiente,
    },
    preop,
    notas,
    tareas,
    propuestas,
    adjuntos,
    examen: {
      av_od: String(cons.av_od ?? '—'),
      av_oi: String(cons.av_oi ?? '—'),
      pio_od: cons.pio_od != null ? Number(cons.pio_od) : 0,
      pio_oi: cons.pio_oi != null ? Number(cons.pio_oi) : 0,
      plan: String(cons.plan ?? '—'),
      examen_fisico: String(cons.examen_fisico ?? ''),
    },
    agenda: {
      sala: String(bloqueo?.sala ?? sol.sala ?? '—'),
      fecha: fechaAgenda,
      fecha_fin: fechaFinAgenda,
      duracion: Number.isFinite(duracionAgenda) ? duracionAgenda : 30,
      anestesia: String(sol.tipo_anestesia ?? sol.anestesia ?? '—'),
      doctor: String(bloqueo?.doctor ?? sol.doctor ?? detalle.doctor ?? '—'),
      origen: bloqueo ? 'Bloqueo agenda' : sigcenterId ? 'SIGCENTER' : fechaAgenda ? 'Fecha programada' : 'Sin agenda',
      sigcenter_agenda_id: sigcenterId,
    },
  };
}

export async function fetchDetalle(id: number): Promise<Detalle> {
  type DetalleResponse = {
    success: boolean;
    data: DetallePayload;
  };

  const res = await jsonFetch<DetalleResponse>(`/v2/solicitudes/${id}/detalle`);
  if (!res.success) throw new Error('Error loading detalle');
  return mapDetalleResponse(res.data);
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
