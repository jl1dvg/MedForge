export interface LabelValue {
  label: string;
  value: number;
}

export interface NameTotal {
  name: string;
  total: number;
}

export interface TrendPoint {
  label: string;
  realizadas: number;
  facturadas: number;
}

export interface ExecKpi {
  cls: string;
  source: string;
  label: string;
  value: string;
  hint: string;
}

export interface FlowStage {
  key: string;
  cls: string;
  label: string;
  value: number;
  context: string;
  leak?: { label: string; count: number; amount: number };
}

export interface FlowLink {
  pct: number;
}

export interface ExecSummaryRow {
  icon: string;
  label: string;
  value: string;
  hint?: string;
}

export interface ExecSummary {
  oportunidad: string;
  arrastre: string;
  sla: string;
  rows?: ExecSummaryRow[];
}

export interface ExecAction {
  severity: string;
  title: string;
  metric: string;
  owner: string;
  action: string;
}

export interface ExecLedger {
  label: string;
  value: string;
  tone?: string;
}

export interface ExecMap {
  kpis: ExecKpi[];
  flow: FlowStage[];
  links: FlowLink[];
  summary: ExecSummary;
  actions: ExecAction[];
  ledger: ExecLedger[];
}

export interface PeriodMeta {
  label: string;
  fromLabel: string;
  toLabel: string;
}

export interface SedeMeta {
  label: string;
}

export interface SynthCellData {
  label: string;
  value: string | number;
  unit?: string;
  delta?: number;
  deltaSuffix?: string;
  invert?: boolean;
}
