export interface WaPeriodMeta {
  key: string;
  label: string;
  fromLabel: string;
  toLabel: string;
}

export interface WaSedeMeta {
  id: string;
  label: string;
}

export interface WaTrendPoint {
  label: string;
  conversaciones: number;
  atendidas: number | null;
  bot: number | null;
  citas: number;
}

export interface WaShareItem {
  id?: string;
  label: string;
  total: number;
  share: number;
  identified?: number;
  bookings?: number;
  bookingRate?: number;
}

export interface WaFunnelStep {
  label: string;
  value: number;
}

export interface WaFrictionItem {
  label: string;
  total: number;
  share: number;
}

export interface WaAgentRow {
  name: string;
  attended: number;
  avgRespMin: number | null;
}

export interface WaTeamRow {
  name: string;
  total: number;
  queued: number;
  assigned: number;
  resolved: number;
}

export interface WaInsight {
  tone: 'success' | 'warning' | 'danger';
  title: string;
  body: string;
}

export interface WaSummary {
  conversationsNew: number;
  peopleInbound: number;
  messagesIn: number;
  messagesOut: number;
  messagesTotal: number;
  attentionRate: number;
  attendedHuman: number;
  lostNeedsHuman: number;
  resolvedBot: number;
  resolved: number;
  medianFirstResp: number | null;
  p75FirstResp: number | null;
  slaRate: number;
  bookings: number;
  bookingPatients: number;
  bookingFailures: number;
  bookingRate: number;
  handoffs: number;
  handoffRate: number;
  identificationRate: number;
  containmentRate: number;
  csat: number | null;
  reactivationRate: number | null;
  deltas: Record<string, number>;
}

export interface WhatsappReport {
  period: WaPeriodMeta;
  sede: WaSedeMeta;
  generatedAt: string;
  slaTarget: number;
  summary: WaSummary;
  trend: WaTrendPoint[];
  sources: WaShareItem[];
  intents: WaShareItem[];
  lifecycle: WaShareItem[];
  funnel: WaFunnelStep[];
  frictions: WaFrictionItem[];
  agents: WaAgentRow[];
  teams: WaTeamRow[];
  insights: WaInsight[];
  recommendations: string[];
}

declare global {
  interface Window {
    MF_WA_REPORT: WhatsappReport;
    MF_WA_FILTERS: { period: string; agentId: number | null };
  }
}
