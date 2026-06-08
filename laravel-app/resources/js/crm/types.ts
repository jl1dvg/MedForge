export type Resolution = 'provisional' | 'identified' | 'linked';
export type Source = 'whatsapp' | 'solicitud' | 'examen' | 'manual' | 'legacy';
export type Phase = 'operational' | 'commercial';
export type Stage =
  | 'nuevo'
  | 'contactado'
  | 'en_evaluacion'
  | 'propuesta'
  | 'comprometido'
  | 'ganado'
  | 'perdido';

export type ActivityType =
  | 'nota' | 'llamada' | 'cambio_etapa' | 'email'
  | 'examen' | 'solicitud' | 'whatsapp';

export interface CrmContact {
  id: number;
  patient_id: number | null;
  name: string;
  phone: string;
  email: string | null;
  cedula: string | null;
  resolution: Resolution;
  source: Source;
  created_at: string;
  updated_at: string;
}

export interface CrmActivity {
  id: number;
  opportunity_id: number;
  type: ActivityType;
  description: string;
  user_id: number | null;
  source_id: number | null;
  source_type: string | null;
  created_at: string;
}

export interface CrmOpportunity {
  id: number;
  contact_id: number;
  title: string;
  stage: Stage;
  phase: Phase;
  source: Source;
  effective_source?: Source;
  effective_sources?: Source[];
  source_id: number | null;
  source_type: string | null;
  afiliacion_tipo?: string;
  assigned_to: number | null;
  lost_reason: string | null;
  last_activity_at: string | null;
  escalation_at: string | null;
  created_at: string;
  updated_at: string;
  contact?: CrmContact;
  activities?: CrmActivity[];
  /** Estimated opportunity value calculated dynamically from the tarifario. */
  valor_estimado?: number;
  source_data?: {
    procedimiento: string | null;
    ojo: string | null;
    doctor: string | null;
  } | null;
}

export interface PanelStats {
  urgent: number;
  active: number;
  won_this_month: number;
  avg_response_h: number;
  conversion_rate: number;
}

export interface ApiMeta {
  total: number;
  limit: number;
  offset: number;
}

export interface OpportunitiesResponse {
  data: CrmOpportunity[];
  meta: ApiMeta;
}

// ─── Rich view type used by UI components ───────────────────────────────────

export interface TimelineItem {
  tipo: string;
  txt: string;
  by: string;
  at: string;
}

export interface NextAction {
  tipo: string;
  label: string;
  due_at: string;
  estado: 'vencida' | 'hoy' | 'futura';
}

export interface Cierre {
  resultado: 'ganada' | 'perdida';
  motivo: string | null;
  motivo_label?: string;
  valor_final?: number | null;
  at: string | null;
}

export interface Cobertura {
  estado: 'aprobada' | 'pendiente' | 'no_aplica';
  label: string;
  aseguradora: string;
  codigo?: string;
}

export interface Tarea {
  titulo: string;
  resp: string;
  due: string;
  prioridad: 'alta' | 'normal';
  done: boolean;
}

export interface Nota {
  txt: string;
  by: string;
  at: string;
}

export interface Comunicacion {
  canal: 'whatsapp' | 'llamada' | 'correo';
  dir: 'in' | 'out';
  txt: string;
  at: string;
  by: string;
}

export interface PropuestaItem {
  cod: string;
  desc: string;
  cant: number;
  valor: number;
}

export interface Propuesta {
  estado: 'borrador' | 'enviada' | 'aceptada' | 'rechazada';
  items: PropuestaItem[];
  subtotal: number;
  iva: number;
  total: number;
  vigencia: string;
}

export interface OpportunityView {
  // raw API fields
  id: number;
  contact_id: number;
  stage: Stage;
  source: Source;
  afiliacion_tipo?: string;
  assigned_to: number | null;
  lost_reason: string | null;
  last_activity_at: string | null;
  escalation_at: string | null;
  created_at: string;
  updated_at: string;
  contact?: CrmContact;
  activities?: CrmActivity[];
  // derived display fields
  full_name: string;
  initials: string;
  hc_number: string | null;
  telefono: string;
  procedimiento_short: string;
  afiliacion: string;
  afiliacion_label: string;
  afiliacion_tone: string;
  fuente: string;
  fuente_label: string;
  fuente_icon: string;
  tipo: 'quirurgico' | 'examen' | 'lead';
  proc_icon: string;
  temperatura: 'caliente' | 'tibia' | 'fria';
  valor: number | null;
  probabilidad: number;
  proxima_accion: NextAction | null;
  cierre: Cierre | null;
  timeline: TimelineItem[];
  responsable_name: string;
  prioridad: 'urgente' | 'normal';
  // optional / defaulted
  edad: string;
  ojo: string;
  diagnostico: string;
  sede: string;
  doctor: string;
  tareas: Tarea[];
  notas: Nota[];
  comunicaciones: Comunicacion[];
  propuesta: Propuesta | null;
  cobertura: Cobertura;
}
