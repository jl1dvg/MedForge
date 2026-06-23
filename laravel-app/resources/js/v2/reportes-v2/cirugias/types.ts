import type { ExecMap, SynthCellData, PeriodMeta, SedeMeta } from '../shared/types';

export interface NameRealizadas {
  name: string;
  realizadas: number;
}

export interface NameTotal {
  name: string;
  total: number;
}

export interface LabelTotal {
  label: string;
  total: number;
}

export interface TrendPoint {
  label: string;
  realizadas: number;
  facturadas: number;
}

export interface DonutItem {
  label: string;
  total: number;
  color: string;
}

export interface CirugiasMetrics {
  solicitudes: number;
  programadas: number;
  realizadas: number;
  informadas: number;
  facturadas: number;
  pendienteFacturar: number;
  pendientePagoN: number;
  cumplimiento: number;
  duracionProm: number;
  tatProm: number;
  tatMed: number;
  tatP90: number;
  tatMuestra: number;
  reingreso: number;
}

export interface CirugiasReport {
  unit: string;
  unitLabel: string;
  unitIcon: string;
  generatedAt: string;
  period: PeriodMeta;
  sede: SedeMeta;
  synth: SynthCellData[];
  exec: ExecMap;
  metrics: CirugiasMetrics;
  produccionMensual: TrendPoint[];
  trazabilidad: DonutItem[];
  topProcedimientos: LabelTotal[];
  topProcIngreso: LabelTotal[];
  topCirujanos: NameRealizadas[];
  topSolicitantes: NameTotal[];
  porConvenio: LabelTotal[];
  mixCategoria: DonutItem[];
}

export interface SedeOption {
  value: string;
  label: string;
}

declare global {
  interface Window {
    MF_CIR_REPORT: CirugiasReport;
    MF_CIR_SEDE_OPTIONS: SedeOption[];
    MF_CIR_FILTERS: { startDate: string; endDate: string; sede: string };
  }
}
