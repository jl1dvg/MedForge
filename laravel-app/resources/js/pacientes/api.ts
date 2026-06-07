import type { Patient } from './types';
import { MEDICO_MAP, SEDE_MAP, AFIL_MAP, ESTADO_SOL, SOL_ACTIVA, TIPO_CITA } from './data';
import { initials } from './utils';

const csrfToken = (): string => (window as any).csrfToken || '';

function buildTimeline(p: any): any[] {
  const ev: any[] = [];
  (p.citas || []).forEach((c: any) => {
    const t = TIPO_CITA[c.tipo] || { label: c.tipo, icon: 'mdi-calendar', cat: 'consulta' };
    ev.push({ at: c.fecha, tipo: 'cita', icon: t.icon, txt: `${t.label}: ${c.det || ''}`, by: MEDICO_MAP[c.medico]?.full || c.medico, estado: c.estado });
  });
  (p.solicitudes || []).forEach((s: any) => ev.push({ at: s.fecha, tipo: 'solicitud', icon: s.tipo === 'quirurgica' ? 'mdi-hospital-box-outline' : 'mdi-flask-outline', txt: `Solicitud ${s.id}: ${s.titulo}`, by: 'Sistema' }));
  (p.examenes || []).forEach((x: any) => ev.push({ at: x.fecha, tipo: 'examen', icon: x.tipo === 'img' ? 'mdi-image-outline' : 'mdi-file-pdf-box', txt: `Examen subido: ${x.nombre}`, by: MEDICO_MAP[x.med]?.full || '—' }));
  (p.notas || []).forEach((n: any) => ev.push({ at: n.at, tipo: 'nota', icon: 'mdi-note-text-outline', txt: n.txt, by: n.by }));
  (p.facturas || []).forEach((f: any) => ev.push({ at: f.fecha, tipo: 'factura', icon: 'mdi-receipt-text-outline', txt: `Factura ${f.num} · ${f.concepto}`, by: 'Facturación' }));
  (p.comunicaciones || []).forEach((c: any) => ev.push({ at: c.at, tipo: 'com', icon: c.canal === 'whatsapp' ? 'mdi-whatsapp' : c.canal === 'llamada' ? 'mdi-phone' : 'mdi-email-outline', txt: c.txt, by: c.dir === 'out' ? c.by : 'Paciente' }));
  ev.sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime());
  return ev;
}

function normalizePatient(raw: any, id: number): Patient {
  const nombres = String(raw.nombres || raw.fname || '').trim();
  const apellidos = String(raw.apellidos || `${raw.lname || ''} ${raw.lname2 || ''}`.trim()).trim();
  const displayName = `${nombres} ${apellidos}`.trim();
  const fullName = `${apellidos} ${nombres}`.trim();
  const ini = initials(nombres || 'P', apellidos || 'P');
  const solicitudes = raw.solicitudes || [];
  const activeSols = solicitudes.filter((s: any) => SOL_ACTIVA.has(s.estado));

  // Compute age from fecha_nacimiento if edad not provided
  const fechaNac = String(raw.fecha_nac || raw.fecha_nacimiento || '');
  let edad = raw.edad || 0;
  if (!edad && fechaNac) {
    const diff = Date.now() - new Date(fechaNac).getTime();
    edad = Math.floor(diff / (365.25 * 24 * 3600 * 1000));
  }

  // Resolve sede from list endpoint (id_sede field) or detail
  const sedeRaw = String(raw.sede || raw.sede_id || raw.id_sede || '').toLowerCase().trim();
  const sede = sedeRaw || 'ceibos';

  // Resolve medico: list endpoint returns medico_nombre (raw doctor name), detail has medico_id
  const medico = String(raw.medico || raw.medico_id || raw.doctor_id || raw.medico_nombre || '');

  // Próxima cita: list endpoint returns proxima_fecha/hora/tipo, detail has proxima_cita object
  let proximaCita = raw.proxima_cita || null;
  if (!proximaCita && raw.proxima_fecha) {
    const fechaStr = raw.proxima_hora
      ? `${raw.proxima_fecha}T${raw.proxima_hora}`
      : raw.proxima_fecha;
    proximaCita = {
      fecha: fechaStr,
      medico: raw.proxima_doctor || medicoRaw,
      tipo: raw.proxima_tipo || 'consulta',
    };
  }

  const solActiva = raw.sol_activa != null
    ? Number(raw.sol_activa)
    : activeSols.length;

  const p: Patient = {
    id: raw.id || id,
    hc_number: String(raw.hc_number || raw.hc || ''),
    nombres,
    apellidos,
    full_name: fullName,
    display_name: displayName,
    initials: ini,
    cedula: String(raw.cedula || raw.identificacion || ''),
    fecha_nac: fechaNac,
    edad,
    sexo: String(raw.sexo || raw.genero || 'M'),
    telefono: String(raw.telefono || raw.tel || raw.celular || raw.telefono_movil || ''),
    telefono_alt: raw.telefono_alt || raw.tel2 || null,
    email: raw.email || null,
    direccion: String(raw.direccion || raw.dir || ''),
    ciudad: String(raw.ciudad || 'Guayaquil'),
    sede,
    medico,
    afiliacion: String(raw.afiliacion || 'privado'),
    aseguradora: raw.aseguradora || null,
    poliza: raw.poliza || raw.num_poliza || null,
    titular: raw.titular || null,
    emergencia: raw.emergencia || { nombre: '—', rel: '—', tel: '—' },
    ultima_visita: String(raw.ultima_visita || raw.ultima_fecha || raw.ultima || new Date().toISOString()),
    proxima_cita: proximaCita,
    alerta: raw.alerta || null,
    deuda: Number(raw.deuda || 0),
    citas: raw.citas || [],
    solicitudes,
    examenes: raw.examenes || [],
    notas: raw.notas || [],
    facturas: raw.facturas || [],
    comunicaciones: raw.comunicaciones || [],
    sol_activa: solActiva,
    created_at: String(raw.created_at || new Date().toISOString()),
    timeline: [],
  };
  p.timeline = buildTimeline(p);
  return p;
}

export async function fetchPatientList(): Promise<Patient[]> {
  const res = await fetch('/v2/pacientes?limit=5000&offset=0', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!res.ok) throw new Error('Error cargando lista de pacientes');
  const json = await res.json();
  const rows: any[] = json.data || [];
  return rows.map((r, i) => normalizePatient(r, i + 1));
}

export async function fetchPatientDetail(hcNumber: string): Promise<Patient | null> {
  const res = await fetch(`/v2/pacientes/detalles?hc_number=${encodeURIComponent(hcNumber)}`, {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error('Error cargando detalle de paciente');
  const json = await res.json();
  const raw = json.data?.patientData || json.data || {};
  return normalizePatient(raw, 1);
}

export async function fetchPatientSection(hcNumber: string, section: string): Promise<{ rows: any[]; summary: any }> {
  const res = await fetch(`/v2/pacientes/detalles/section?hc_number=${encodeURIComponent(hcNumber)}&section=${section}&limit=50`, {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });
  if (!res.ok) return { rows: [], summary: {} };
  const json = await res.json();
  return { rows: json.data || [], summary: json.meta?.summary || {} };
}

export async function createPatient(data: Record<string, any>): Promise<{ hc_number: string }> {
  const res = await fetch('/v2/pacientes/crear', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(data),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Error al crear paciente');
  }
  return res.json();
}
