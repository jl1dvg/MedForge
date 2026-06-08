import type { CrmOpportunity } from './types';
import type { OpportunityView } from './types';
import { STAGE_MAP } from './stages';

const MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

export function fmtMoney(v: number | null | undefined): string {
  if (v == null) return '—';
  return '$' + v.toLocaleString('es-EC', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
}

export function fmtDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return `${d.getDate()} ${MESES[d.getMonth()]}`;
}

export function fmtDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return `${d.getDate()} ${MESES[d.getMonth()]} · ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

export function relTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const now = Date.now();
  const diff = new Date(iso).getTime() - now;
  const abs = Math.abs(diff);
  const future = diff > 0;
  if (abs < 3600000) {
    const m = Math.max(1, Math.round(abs / 60000));
    return future ? `en ${m} min` : `hace ${m} min`;
  }
  const h = Math.round(abs / 3600000);
  if (h < 24) return future ? `en ${h} h` : `hace ${h} h`;
  const d = Math.round(h / 24);
  if (d === 1) return future ? 'mañana' : 'ayer';
  return future ? `en ${d} días` : `hace ${d} días`;
}

export function nextActionState(op: OpportunityView): 'none' | 'vencida' | 'hoy' | 'futura' {
  if (!op.proxima_accion) return 'none';
  return op.proxima_accion.estado || 'futura';
}

export function initials(name: string): string {
  return (name || '').split(/\s+/).filter(w => /[A-Za-záéíóúñÁÉÍÓÚÑ]/.test(w)).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

export function hexToSoft(hex: string): string {
  const n = parseInt(hex.slice(1), 16);
  const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  const mix = (c: number) => Math.round(c + (255 - c) * 0.86);
  return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
}

const ACTIVITY_TYPE_MAP: Record<string, string> = {
  nota: 'note',
  llamada: 'call',
  cambio_etapa: 'stage',
  email: 'note',
  examen: 'stage',
  solicitud: 'stage',
  whatsapp: 'call',
};

export function adaptOpportunity(raw: CrmOpportunity): OpportunityView {
  const stage = raw.stage;
  const stageConf = STAGE_MAP[stage];

  const full_name = raw.contact?.name || raw.title;
  const inits = initials(full_name);

  const procedimiento_short = (
    raw.source_data?.procedimiento ||
    raw.title
      .replace(/^Solicitud:\s*/i, '')
      .replace(/^Examen:\s*/i, '')
      .replace(/^Lead migrado:\s*/i, '')
      .replace(/^Lead WhatsApp:\s*/i, '')
  ).trim() || '—';

  const afiliacion = raw.afiliacion_tipo || 'sin_dato';
  const afiliacion_label_map: Record<string, string> = {
    particular: 'Particular',
    privado: 'Privado',
    publico: 'IESS',
    fundacional: 'Fundacional',
    sin_dato: 'Sin dato',
  };
  const afiliacion_label = afiliacion_label_map[afiliacion] || afiliacion;
  const afiliacion_tone = afiliacion === 'publico' ? 'visita' : afiliacion === 'particular' ? 'neutral' : 'examen';

  const fuente = raw.source;
  const fuente_label_map: Record<string, string> = {
    whatsapp: 'WhatsApp', solicitud: 'Solicitud', examen: 'Examen', manual: 'Manual',
  };
  const fuente_icon_map: Record<string, string> = {
    whatsapp: 'mdi-whatsapp', solicitud: 'mdi-file-document', examen: 'mdi-microscope', manual: 'mdi-account-plus',
  };
  const fuente_label = fuente_label_map[fuente] || fuente;
  const fuente_icon = fuente_icon_map[fuente] || 'mdi-help-circle-outline';

  const tipo = fuente === 'examen' ? 'examen' : 'quirurgico';
  const proc_icon = tipo === 'examen' ? 'mdi-microscope' : 'mdi-eye-outline';

  // temperatura based on last_activity_at
  let temperatura: 'caliente' | 'tibia' | 'fria' = 'fria';
  if (raw.last_activity_at) {
    const daysSince = (Date.now() - new Date(raw.last_activity_at).getTime()) / 86400000;
    if (daysSince < 2) temperatura = 'caliente';
    else if (daysSince < 5) temperatura = 'tibia';
  }

  // proxima_accion from escalation_at
  let proxima_accion: OpportunityView['proxima_accion'] = null;
  if (raw.escalation_at) {
    const escDate = new Date(raw.escalation_at);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const escDay = new Date(escDate.getFullYear(), escDate.getMonth(), escDate.getDate());
    let estado: 'vencida' | 'hoy' | 'futura' = 'futura';
    if (escDate < now) estado = 'vencida';
    else if (escDay.getTime() === today.getTime()) estado = 'hoy';
    proxima_accion = { tipo: 'llamada', label: 'Dar seguimiento', due_at: raw.escalation_at, estado };
  }

  // cierre
  let cierre: OpportunityView['cierre'] = null;
  if (stage === 'ganado' || stage === 'perdido') {
    cierre = {
      resultado: stage === 'ganado' ? 'ganada' : 'perdida',
      motivo: raw.lost_reason,
      motivo_label: raw.lost_reason || undefined,
      at: raw.last_activity_at || raw.updated_at,
    };
  }

  // timeline from activities
  const timeline = (raw.activities || []).map(a => ({
    tipo: ACTIVITY_TYPE_MAP[a.type] || 'note',
    txt: a.description,
    by: 'Sistema',
    at: a.created_at,
  }));

  const prioridad: 'urgente' | 'normal' =
    proxima_accion?.estado === 'vencida' ? 'urgente' : 'normal';

  return {
    id: raw.id,
    contact_id: raw.contact_id,
    stage,
    source: raw.source,
    assigned_to: raw.assigned_to,
    lost_reason: raw.lost_reason,
    last_activity_at: raw.last_activity_at,
    escalation_at: raw.escalation_at,
    created_at: raw.created_at,
    updated_at: raw.updated_at,
    contact: raw.contact,
    activities: raw.activities,
    // derived
    full_name,
    initials: inits,
    hc_number: raw.contact?.cedula || null,
    telefono: raw.contact?.phone || '—',
    procedimiento_short,
    afiliacion,
    afiliacion_tipo: raw.afiliacion_tipo,
    afiliacion_label,
    afiliacion_tone,
    fuente,
    fuente_label,
    fuente_icon,
    tipo,
    proc_icon,
    temperatura,
    valor: null,
    probabilidad: stageConf?.prob ?? 0,
    proxima_accion,
    cierre,
    timeline,
    responsable_name: raw.assigned_to ? `Usuario #${raw.assigned_to}` : '—',
    prioridad,
    // optional empty defaults
    edad: '—',
    ojo: raw.source_data?.ojo || '',
    diagnostico: '',
    sede: '—',
    doctor: raw.source_data?.doctor || '—',
    tareas: [],
    notas: [],
    comunicaciones: [],
    propuesta: null,
    cobertura: { estado: 'no_aplica', label: 'No aplica', aseguradora: '—' },
  };
}
