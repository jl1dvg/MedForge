export type PrescriptionStatus = 'pendiente' | 'procesada' | 'parcial' | 'entregada' | 'cancelada';
export type Availability = 'disponible' | 'parcial' | 'no_disponible';
export type InventoryCategory =
  | 'colirios' | 'unguentos' | 'oral' | 'inyectables' | 'lagrimas'
  | 'antiglaucomatosos' | 'antibioticos' | 'antiinflamatorios' | 'otros';
export type DeliveryStatus = 'preparando' | 'en_camino' | 'entregada' | 'cancelada';

export interface PharmacyPatient {
  id: number;
  nombres: string;
  apellidos: string;
  identificacion: string;
  telefono?: string;
  whatsapp?: string;
  email?: string;
  clinica?: string;
}

export interface PrescriptionItem {
  id: number;
  nombre_medicamento: string;
  presentacion: string;
  dosis: string;
  frecuencia: string;
  duracion_dias?: number;
  indicaciones?: string;
  disponibilidad: Availability;
  inventory_id?: number;
}

export interface WhatsappLog {
  id: number;
  mensaje: string;
  estado: string;
  created_at: string;
}

export interface Prescription {
  id: number;
  estado: PrescriptionStatus;
  clinica?: string;
  medico?: string;
  notas?: string;
  fecha_prescripcion: string;
  created_at: string;
  patient: PharmacyPatient;
  items: PrescriptionItem[];
  whatsapp_logs?: WhatsappLog[];
}

export interface InventoryItem {
  id: number;
  nombre: string;
  principio_activo?: string;
  categoria: InventoryCategory;
  presentacion: string;
  stock: number;
  stock_minimo: number;
  precio?: number;
  estado: 'activo' | 'inactivo';
}

export interface DashboardMetrics {
  recetas_pendientes: number;
  procesadas_este_mes: number;
  stock_bajo: number;
  entregas_activas: number;
  recordatorios_proximos: number;
  top_medicamentos: Array<{ nombre: string; count: number }>;
}

export interface PaginatedMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}
