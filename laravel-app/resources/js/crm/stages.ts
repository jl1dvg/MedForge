export interface StageConfig {
  slug: string;
  label: string;
  short: string;
  prob: number;
  color: string;
  icon: string;
  phase: string;
}

export interface PhaseConfig {
  slug: string;
  label: string;
  icon: string;
}

export const STAGES: StageConfig[] = [
  { slug: 'nuevo',        label: 'Nueva',         short: 'Nueva',      prob: 10,  color: '#5156be', icon: 'mdi-tray-arrow-down',           phase: 'ingreso' },
  { slug: 'contactado',   label: 'En contacto',   short: 'Contacto',   prob: 28,  color: '#3596f7', icon: 'mdi-phone-in-talk-outline',     phase: 'ingreso' },
  { slug: 'en_evaluacion',label: 'Cotizada',       short: 'Cotizada',   prob: 48,  color: '#6f67d8', icon: 'mdi-file-document-outline',     phase: 'comercial' },
  { slug: 'propuesta',    label: 'Autorización',  short: 'Autor.',     prob: 62,  color: '#d59623', icon: 'mdi-shield-check-outline',      phase: 'comercial' },
  { slug: 'comprometido', label: 'Por agendar',   short: 'Agenda',     prob: 80,  color: '#0c6fb0', icon: 'mdi-calendar-clock-outline',    phase: 'cierre' },
  { slug: 'ganado',       label: 'Ganada',         short: 'Ganada',     prob: 100, color: '#05825f', icon: 'mdi-trophy-variant-outline',    phase: 'resueltas' },
  { slug: 'perdido',      label: 'Perdida',        short: 'Perdida',    prob: 0,   color: '#ee3158', icon: 'mdi-close-octagon-outline',     phase: 'resueltas' },
];

export const STAGE_MAP: Record<string, StageConfig> = Object.fromEntries(
  STAGES.map(s => [s.slug, s])
);

export const PHASES: PhaseConfig[] = [
  { slug: 'ingreso',    label: 'Ingreso',    icon: 'mdi-tray-arrow-down' },
  { slug: 'comercial',  label: 'Comercial',  icon: 'mdi-briefcase-outline' },
  { slug: 'cierre',     label: 'Cierre',     icon: 'mdi-calendar-clock-outline' },
  { slug: 'resueltas',  label: 'Resueltas',  icon: 'mdi-check-circle-outline' },
];
