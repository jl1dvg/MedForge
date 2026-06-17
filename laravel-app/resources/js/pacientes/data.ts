import type { Sede, Medico, Afiliacion } from './types';

export const SEDES: Sede[] = [
  { id: 'matriz', label: 'MATRIZ', short: 'MAT', color: '#5156be' },
  { id: 'ceibos', label: 'CEIBOS', short: 'CEI', color: '#3596f7' },
];

export const MEDICOS: Medico[] = [
  { id: 'vera', full: 'Dra. Andrea Vera', esp: 'Córnea y segmento anterior', color: '#1f9d7a' },
  { id: 'moran', full: 'Dr. Luis Morán', esp: 'Retina y vítreo', color: '#6f67d8' },
  { id: 'castro', full: 'Dra. Paola Castro', esp: 'Glaucoma', color: '#d59623' },
  { id: 'icaza', full: 'Dr. Javier Icaza', esp: 'Catarata y refractiva', color: '#3d7ac7' },
  { id: 'leon', full: 'Dra. Mónica León', esp: 'Oculoplástica', color: '#d34b5b' },
];

export const AFILIACIONES: Afiliacion[] = [
  { id: 'privado', label: 'Privado', tone: 'neutral' },
  { id: 'iess', label: 'IESS', tone: 'consulta' },
  { id: 'seguro', label: 'Seguro privado', tone: 'visita' },
];

export const ASEGURADORAS = ['Salud S.A.', 'BMI', 'Ecuasanitas', 'Humana', 'Confiamed'];

export const MEDICO_MAP = Object.fromEntries(MEDICOS.map(m => [m.id, m]));
export const SEDE_MAP = Object.fromEntries(SEDES.map(s => [s.id, s]));
export const AFIL_MAP = Object.fromEntries(AFILIACIONES.map(a => [a.id, a]));

export const TIPO_CITA: Record<string, { label: string; icon: string; cat: string }> = {
  consulta: { label: 'Consulta', icon: 'mdi-stethoscope', cat: 'consulta' },
  control: { label: 'Control post-op', icon: 'mdi-clipboard-pulse-outline', cat: 'consulta' },
  optometria: { label: 'Optometría', icon: 'mdi-glasses', cat: 'optometria' },
  examen: { label: 'Examen', icon: 'mdi-microscope', cat: 'examen' },
  cirugia: { label: 'Cirugía', icon: 'mdi-hospital-box-outline', cat: 'cirugia' },
};

export const ESTADO_SOL: Record<string, { label: string; tone: string }> = {
  ingresada: { label: 'Ingresada', tone: 'neutral' },
  cotizacion: { label: 'En cotización', tone: 'examen' },
  en_proceso: { label: 'En proceso', tone: 'visita' },
  autorizada: { label: 'Autorizada', tone: 'consulta' },
  completada: { label: 'Completada', tone: 'ok' },
};

export const SOL_ACTIVA = new Set(['ingresada', 'cotizacion', 'en_proceso', 'autorizada']);
