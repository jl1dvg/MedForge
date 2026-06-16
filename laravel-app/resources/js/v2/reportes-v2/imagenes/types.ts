import type { ExecMap, PeriodMeta, SedeMeta, SynthCellData } from '../shared/types';

export interface ImagenesMetrics {
  realizados: number;
  facturados: number;
  pendFact: number;
  pendPago: number;
  solicTotal: number;
  solAgendadas: number;
  cumplPct: number | null;
  produccionFact: number;
  montoPend: number;
  ticket: number;
  tatProm: number | null;
  tatMed: number | null;
  tatP90: number | null;
  sla48Pct: number | null;
}

export interface LabelTotal {
  label: string;
  total: number;
  color?: string;
}

export interface MonthPoint {
  label: string;
  realizados: number;
  informados: number;
}

export interface ImagenesReport {
  unit: string;
  unitLabel: string;
  unitIcon: string;
  generatedAt: string;
  period: PeriodMeta;
  sede: SedeMeta;
  synth: SynthCellData[];
  exec: ExecMap;
  metrics: ImagenesMetrics;
  produccionMensual: MonthPoint[];
  trazabilidad: LabelTotal[];
  topExamenes: LabelTotal[];
  topDoctores: LabelTotal[];
  porConvenio: LabelTotal[];
}

export interface SedeOption {
  value: string;
  label: string;
}

declare global {
  interface Window {
    MF_IMG_REPORT: ImagenesReport;
    MF_IMG_SEDE_OPTIONS: SedeOption[];
    MF_IMG_FILTERS: { startDate: string; endDate: string; sede: string };
  }
}
