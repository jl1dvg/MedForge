const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const MESES_LARGO = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

function parseDateLocal(value: string | null | undefined): Date | null {
  if (!value) return null;
  const raw = String(value).trim();
  if (!raw) return null;

  const dateOnly = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnly) {
    return new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
  }

  const dateTime = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
  if (dateTime && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(raw)) {
    return new Date(
      Number(dateTime[1]),
      Number(dateTime[2]) - 1,
      Number(dateTime[3]),
      Number(dateTime[4]),
      Number(dateTime[5]),
      Number(dateTime[6] || 0)
    );
  }

  const d = new Date(raw);
  return Number.isNaN(d.getTime()) ? null : d;
}

export function fmtMoney(v: number | null | undefined): string {
  if (v == null) return '—';
  return '$' + v.toLocaleString('es-EC', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 });
}

export function fmtDate(iso: string | null | undefined): string {
  const d = parseDateLocal(iso);
  if (!d) return '—';
  return `${d.getDate()} ${MESES[d.getMonth()]} ${d.getFullYear()}`;
}

export function fmtDateShort(iso: string | null | undefined): string {
  const d = parseDateLocal(iso);
  if (!d) return '—';
  return `${d.getDate()} ${MESES[d.getMonth()]}`;
}

export function fmtDateLong(iso: string | null | undefined): string {
  const d = parseDateLocal(iso);
  if (!d) return '—';
  return `${d.getDate()} de ${MESES_LARGO[d.getMonth()]} de ${d.getFullYear()}`;
}

export function fmtTime(iso: string): string {
  const timeOnly = String(iso || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
  if (timeOnly) return `${timeOnly[1].padStart(2, '0')}:${timeOnly[2]}`;
  const d = parseDateLocal(iso);
  if (!d) return '—';
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export function fmtDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  return `${fmtDateShort(iso)} · ${fmtTime(iso)}`;
}

export function hasTime(iso: string): boolean {
  if (/^\d{4}-\d{2}-\d{2}$/.test(String(iso || '').trim())) return false;
  const d = parseDateLocal(iso);
  if (!d) return false;
  return d.getHours() !== 0 || d.getMinutes() !== 0;
}

export function relDays(iso: string | null | undefined): string {
  const a = parseDateLocal(iso);
  if (!a) return '—';
  a.setHours(0, 0, 0, 0);
  const b = new Date(); b.setHours(0, 0, 0, 0);
  const d = Math.round((a.getTime() - b.getTime()) / 86400000);
  if (d === 0) return 'hoy';
  if (d === 1) return 'mañana';
  if (d === -1) return 'ayer';
  if (d > 0) return d < 30 ? `en ${d} días` : `en ${Math.round(d / 30)} mes${Math.round(d / 30) > 1 ? 'es' : ''}`;
  const ad = -d;
  if (ad < 30) return `hace ${ad} días`;
  const mo = Math.round(ad / 30);
  if (mo < 12) return `hace ${mo} mes${mo > 1 ? 'es' : ''}`;
  return `hace ${Math.round(ad / 365)} año${Math.round(ad / 365) > 1 ? 's' : ''}`;
}

export function isFuture(iso: string): boolean {
  const d = parseDateLocal(iso);
  return !!d && d > new Date();
}

export function isToday(iso: string): boolean {
  const a = parseDateLocal(iso), b = new Date();
  if (!a) return false;
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

export function validarCedula(ced: string): boolean {
  if (!/^\d{10}$/.test(ced)) return false;
  const prov = parseInt(ced.slice(0, 2), 10);
  if (prov < 1 || (prov > 24 && prov !== 30)) return false;
  if (parseInt(ced[2], 10) >= 6) return false;
  const coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
  let sum = 0;
  for (let i = 0; i < 9; i++) {
    let v = parseInt(ced[i], 10) * coef[i];
    if (v >= 10) v -= 9;
    sum += v;
  }
  const dv = (10 - (sum % 10)) % 10;
  return dv === parseInt(ced[9], 10);
}

export function phoneHref(t: string | null): string {
  return 'tel:' + (t || '').replace(/\s/g, '');
}

export function waHref(t: string | null): string {
  return 'https://wa.me/593' + (t || '').replace(/\D/g, '').replace(/^0/, '');
}

export function edadDe(iso: string): number {
  if (!iso) return 0;
  const b = parseDateLocal(iso);
  if (!b) return 0;
  const hoy = new Date();
  let e = hoy.getFullYear() - b.getFullYear();
  const mo = hoy.getMonth() - b.getMonth();
  if (mo < 0 || (mo === 0 && hoy.getDate() < b.getDate())) e--;
  return e;
}

export function emailOk(e: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
}

export function telOk(t: string): boolean {
  return t.replace(/\D/g, '').length >= 9;
}

export function initials(nombres: string, apellidos: string): string {
  return ((nombres[0] || '') + (apellidos[0] || '')).toUpperCase();
}
