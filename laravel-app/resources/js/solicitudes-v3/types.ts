export type SlaStatus = 'en_rango' | 'advertencia' | 'critico' | 'vencido' | 'sin_fecha' | 'cerrado';

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
}

export interface Solicitud {
  id: number;
  hc_number: string;
  paciente: string;
  doctor: string;
  procedimiento: string;
  tipo: string;
  afiliacion: string;
  estado: KanbanSlug;
  prioridad: string;
  prioridad_origen?: string;
  prioridad_automatica?: string;
  sla_status: SlaStatus;
  fecha_programada?: string;
  sede?: string;
  ojo?: string;
  // CRM stats
  crm_total_notas?: number;
  crm_total_adjuntos?: number;
  crm_tareas_pendientes?: number;
  crm_tareas_total?: number;
  // Alerts
  alert_reprogramacion?: boolean;
  alert_pendiente_consentimiento?: boolean;
  alert_documentos_faltantes?: boolean;
  alert_autorizacion_pendiente?: boolean;
  alert_derivacion_vencida?: boolean;
  alert_derivacion_por_vencer?: boolean;
  alert_derivacion_pendiente?: boolean;
  alert_tarea_vencida?: boolean;
  alert_sin_responsable?: boolean;
  alert_sin_contacto?: boolean;
  // Dates
  created_at?: string;
  updated_at?: string;
}

export interface KanbanData {
  data: Record<KanbanSlug, Solicitud[]>;
  options: {
    afiliaciones: string[];
    doctores: string[];
  };
  metrics?: {
    total: number;
    criticos: number;
    sin_programar: number;
    completados_hoy: number;
  };
}

export interface Filters {
  search: string;
  afiliacion: string;
  doctor: string;
  prioridad: string;
  sede: string;
  date_from: string;
  date_to: string;
}

export interface AppConfig {
  kanbanEndpoint: string;
  actualizarEstadoEndpoint: string;
  estadoEndpoint: string;
  kanbanColumns: KanbanColumn[];
  initialFilters: Partial<Filters>;
  realtimeConfig: {
    enabled: boolean;
    key: string;
    cluster: string;
    channel: string;
    events: Record<string, string>;
  };
}
