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
  source_id: number | null;
  source_type: string | null;
  assigned_to: number | null;
  lost_reason: string | null;
  last_activity_at: string | null;
  escalation_at: string | null;
  created_at: string;
  updated_at: string;
  contact?: CrmContact;
  activities?: CrmActivity[];
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
