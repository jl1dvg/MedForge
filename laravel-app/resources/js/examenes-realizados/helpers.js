export function fmtDate(iso) {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}-${m}-${y}`;
}

export function daysBetween(aIso, bIso) {
  const a = new Date(aIso + 'T00:00:00');
  const b = new Date(bIso + 'T00:00:00');
  return Math.round((a - b) / 86400000);
}

export function deadlineInfo(iso, today) {
  if (!iso) return null;
  const diff = daysBetween(iso, today);
  if (diff < 0) return { state: 'over', label: `Vencido hace ${Math.abs(diff)}d` };
  if (diff === 0) return { state: 'soon', label: 'Vence hoy' };
  if (diff === 1) return { state: 'soon', label: 'Vence mañana' };
  return { state: 'ok', label: `En ${diff} días` };
}

export function initials(name) {
  return name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

// Extract ojo from procedure text (mirrors parseProcedimiento in blade)
export function inferOjo(procedimiento) {
  if (!procedimiento) return '';
  const m = procedimiento.match(/\s-\s(AMBOS\s+OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i);
  if (!m) return '';
  const ojoMap = { OD: 'Derecho', OI: 'Izquierdo', AO: 'Ambos ojos', DERECHO: 'Derecho', IZQUIERDO: 'Izquierdo', 'AMBOS OJOS': 'Ambos ojos' };
  return ojoMap[m[1].toUpperCase().replace(/\s+/g, ' ')] || m[1];
}

// Derive tipo_key from procedure text for rows coming from the backend
export function inferTipoKey(procedimiento) {
  if (!procedimiento) return 'OTRO';
  const p = procedimiento.toUpperCase();
  if (p.includes('CAMPO VISUAL') || p.includes('CAMPO_VISUAL') || p.includes('CAMPIMETRIA')) return 'CAMPO_VISUAL';
  if (p.includes('ANGIOGRAF')) return 'ANGIOGRAFIA';
  if (p.includes('RETINOGRAF')) return 'RETINOGRAFIA';
  if (p.includes('OCT') && (p.includes('NERV') || p.includes('RNFL') || p.includes('PAPILA'))) return 'OCT_NERVIO';
  if (p.includes('OCT')) return 'OCT_MACULA';
  if (p.includes('TOPOGRAF') || p.includes('PENTACAM')) return 'TOPOGRAFIA';
  if (p.includes('PAQUIMETR')) return 'PAQUIMETRIA';
  if (p.includes('MICROESPEC') || p.includes('ESPECULAR') || p.includes('ENDOTELIAL')) return 'MICROESPECULAR';
  if (p.includes('BIOMETR') || p.includes('IOLMASTER') || p.includes('LIO')) return 'BIOMETRIA';
  if (p.includes('ECOGRAF')) return 'ECOGRAFIA';
  return 'OTRO';
}

// Read local bandeja overrides (client-side priority layer)
const LS_KEY = 'medf_bandeja_v1';

export function getBandejaStore() {
  try {
    return JSON.parse(localStorage.getItem(LS_KEY) || '{}');
  } catch {
    return {};
  }
}

export function setBandejaStore(store) {
  try {
    localStorage.setItem(LS_KEY, JSON.stringify(store));
  } catch { /* storage unavailable */ }
}
