// ============================================================
// MedForge · Solicitudes v3 — TypeScript types
// ============================================================

export type SlaStatus = 'ok' | 'critico' | 'vencido';

export type KanbanSlug =
  | 'recibida'
  | 'llamado'
  | 'revision-codigos'
  | 'espera-documentos'
  | 'apto-oftalmologo'
  | 'apto-anestesia'
  | 'listo-para-agenda'
  | 'programada'
  | 'completado';

export interface KanbanColumn {
  slug: KanbanSlug;
  label: string;
  phase: string;
}

export interface Phase {
  key: string;
  label: string;
  icon: string;
}

export interface Alert {
  key: string;
  label: string;
  icon: string;
  tone: 'warning' | 'danger';
}

export interface ChecklistStep {
  slug: string;
  label: string;
  completed: boolean;
  can_toggle: boolean;
}

export interface ChecklistProgress {
  completed: number;
  total: number;
  percent: number;
  next_label: string;
}

export interface CrmInfo {
  responsable: string;
  telefono: string;
  email: string;
  fuente: string;
  notas: number;
  adjuntos: number;
  tareas_pendientes: number;
  tareas_total: number;
  proximo_vencimiento: string | null;
  pipeline: string;
}

export interface Nota {
  txt: string;
  by: string;
  at: string;
}

export interface Tarea {
  titulo: string;
  asignado: string;
  fecha: string;
  prioridad: string;
  done: boolean;
}

export interface Adjunto {
  nombre: string;
  icon: string;
  peso: string;
  at: string;
}

export interface DiagnosticoCIE {
  cie: string;
  desc: string;
}

export interface Derivacion {
  tiene: boolean;
  cod: string | null;
  aseguradora: string;
  plan: string;
  dias_vigencia: number | null;
  vencida: boolean;
  archivo: boolean;
  autorizacion_pendiente: boolean;
}

export interface PreopStep {
  label: string;
  done: boolean;
}

export interface PropostaItem {
  cod: string;
  desc: string;
  cant: number;
  valor: number;
}

export interface Propuesta {
  titulo: string;
  estado: string;
  vigencia: string;
  items: PropostaItem[];
  subtotal: number;
  iva: number;
  total: number;
}

export interface Examen {
  av_od: string;
  av_oi: string;
  pio_od: number;
  pio_oi: number;
  plan: string;
}

export interface Agenda {
  sala: string;
  fecha: string | null;
  duracion: number;
  anestesia: string;
}

export interface PacienteDetalle {
  edad: number;
  sexo: string;
  cedula: string;
  direccion: string;
}

export interface Detalle {
  paciente: PacienteDetalle;
  diagnosticos: DiagnosticoCIE[];
  derivacion: Derivacion;
  preop: PreopStep[];
  notas: Nota[];
  tareas: Tarea[];
  propuestas: Propuesta[];
  adjuntos: Adjunto[];
  examen: Examen;
  agenda: Agenda;
}

export interface Protocolo {
  form_id: string;
  lateralidad: string;
  fecha_inicio: string;
  membrete: string;
  confirmado_at?: string;
  confirmado_by?: string;
}

export interface Solicitud {
  id: number;
  form_id: string;
  hc_number: string;
  full_name: string;
  avatar_initials: string;
  doctor: string;
  afiliacion: string;
  afiliacion_label: string;
  afiliacion_tone: string;
  procedimiento: string;
  procedimiento_short: string;
  ojo: string;
  prioridad: 'urgente' | 'normal';
  estado: KanbanSlug;
  estado_label: string;
  fecha: string;
  sede: string;
  observacion: string;
  sla_status: SlaStatus;
  sla_hours_remaining: number | null;
  sla_label: string;
  checklist: ChecklistStep[];
  checklist_progress: ChecklistProgress;
  protocolo_confirmado: Protocolo | null;
  protocolo_posterior_compatible: Protocolo | null;
  crm: CrmInfo;
  alerts: Alert[];
  turno: string | null;
  detalle: Detalle;
}

// ---- API raw types (from /v2/solicitudes/kanban-data) -------------------

export interface ApiSolicitud {
  id: number;
  form_id?: string;
  hc_number?: string;
  paciente?: string;
  full_name?: string;
  doctor?: string;
  afiliacion?: string;
  procedimiento?: string;
  ojo?: string;
  prioridad?: string;
  estado?: string;
  fecha?: string;
  created_at?: string;
  sede?: string;
  observacion?: string;
  sla_status?: string;
  sla_label?: string;
  turno?: string | null;
  crm_responsable?: string;
  crm_telefono?: string;
  crm_email?: string;
  crm_fuente?: string;
  crm_notas?: number;
  crm_adjuntos?: number;
  crm_tareas_pendientes?: number;
  crm_tareas_total?: number;
  crm_proximo_vencimiento?: string | null;
  alert_docs?: boolean;
  alert_auth?: boolean;
  alert_exam?: boolean;
  [key: string]: unknown;
}

export interface ApiKanbanResponse {
  data: Record<string, ApiSolicitud[]>;
  options?: {
    afiliaciones?: string[];
    doctores?: string[];
  };
}

export interface Filters {
  search: string;
  afiliacion: string;
  doctor: string;
}

export interface TweakValues {
  direction: 'a' | 'b' | 'c';
  density: 'comodo' | 'compacto';
  afilColor: boolean;
  groupPhases: boolean;
  showDoctorAvatar: boolean;
  accent: string;
}
