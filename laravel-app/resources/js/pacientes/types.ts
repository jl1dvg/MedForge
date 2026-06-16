export interface Sede {
  id: string;
  label: string;
  short?: string;
  color?: string;
  nombre?: string;
}

export interface Medico {
  id: string;
  full: string;
  esp: string;
  color?: string;
  nombre?: string;
  especialidad?: string;
}

export interface Afiliacion {
  id: string;
  label: string;
  tone: string;
  nombre?: string;
  tipo_afiliacion?: string;
}

export interface TipoAfiliacion {
  id: string;
  label: string;
  tone: string;
  color?: string;
}

export interface PacientesCatalogos {
  medicos: Medico[];
  sedes: Sede[];
  afiliaciones: Afiliacion[];
  tipos_afiliacion: TipoAfiliacion[];
  aseguradoras: string[];
}

export interface ProximaCita {
  fecha: string;
  medico: string;
  tipo: string;
}

export interface Cita {
  fecha: string;
  medico: string;
  tipo: string;
  estado: string;
  det: string;
}

export interface Solicitud {
  id: string;
  tipo: string;
  titulo: string;
  estado: string;
  fecha: string;
}

export interface Examen {
  nombre: string;
  fecha: string;
  tipo: string;
  med: string;
}

export interface Nota {
  txt: string;
  by: string;
  at: string;
}

export interface Factura {
  num: string;
  fecha: string;
  concepto: string;
  total: number;
  pagado: number;
  estado: string;
}

export interface Comunicacion {
  canal: string;
  dir: string;
  txt: string;
  at: string;
  by: string | null;
}

export interface TimelineEvent {
  at: string;
  tipo: string;
  icon: string;
  txt: string;
  by: string;
  estado?: string;
}

export interface Emergencia {
  nombre: string;
  rel: string;
  tel: string;
}

export interface Patient {
  id: number;
  hc_number: string;
  nombres: string;
  apellidos: string;
  full_name: string;
  display_name: string;
  initials: string;
  cedula: string;
  fecha_nac: string;
  edad: number;
  sexo: string;
  telefono: string;
  telefono_alt: string | null;
  email: string | null;
  direccion: string;
  ciudad: string;
  sede: string;
  sede_info: { id: string; nombre: string; origen: string } | null;
  medico: string;
  medico_tratante: {
    id: number;
    nombre: string;
    especialidad: string;
    procedimientos_count: number;
    ultima_fecha: string | null;
    confirmado: boolean;
  } | null;
  afiliacion: string;
  tipo_afiliacion: string;
  afiliacion_info: { nombre: string; tipo: string } | null;
  aseguradora: string | null;
  poliza: string | null;
  titular: string | null;
  emergencia: Emergencia;
  ultima_visita: string;
  proxima_cita: ProximaCita | null;
  alerta: string | null;
  deuda: number;
  citas: Cita[];
  solicitudes: Solicitud[];
  examenes: Examen[];
  notas: Nota[];
  facturas: Factura[];
  comunicaciones: Comunicacion[];
  sol_activa: number;
  created_at: string;
  timeline: TimelineEvent[];
}

export interface WizardFormData {
  docTipo: 'cedula' | 'pasaporte';
  cedula: string;
  nombres: string;
  apellidos: string;
  fecha_nac: string;
  sexo: string;
  telefono: string;
  telefono_alt: string;
  email: string;
  direccion: string;
  ciudad: string;
  afiliacion: string;
  aseguradora: string;
  poliza: string;
  titular: string;
  medico: string;
  sede: string;
  motivo: string;
  alerta: string;
}

export type AppRoute = 'list' | 'detail' | 'create' | 'edit';

export interface Toast {
  msg: string;
  icon: string;
  kind: string;
}

export interface AgendarState {
  id: number | null;
  open: boolean;
}
