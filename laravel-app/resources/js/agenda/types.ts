export interface AgendaOption {
  value: string;
  label: string;
}

export interface AgendaBootstrap {
  defaults: {
    fecha: string;
    hora: string;
  };
  options: {
    tiposAtencion: AgendaOption[];
    doctores: AgendaOption[];
    sedes: AgendaOption[];
    afiliaciones: AgendaOption[];
  };
}

export interface AppointmentForm {
  hc_number: string;
  paciente: string;
  telefono: string;
  fecha: string;
  hora: string;
  tipo_atencion: string;
  codigo_atencion: string;
  detalle_atencion: string;
  doctor: string;
  sede: string;
  afiliacion: string;
}

export interface AppointmentResponse {
  ok: boolean;
  error?: string;
  errors?: Record<string, string[]>;
  data?: {
    form_id: number;
    hc_number: string;
    fecha: string;
    hora: string;
    estado_agenda: string;
    procedimiento_proyectado: string;
  };
}
