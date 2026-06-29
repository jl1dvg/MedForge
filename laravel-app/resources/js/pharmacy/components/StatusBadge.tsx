import React from 'react';
import type { PrescriptionStatus, Availability } from '../types';

const STATUS_STYLES: Record<PrescriptionStatus, React.CSSProperties> = {
  pendiente:  { background: 'var(--bg-surface)', color: 'var(--fg-2)', border: '1px solid var(--border)' },
  procesada:  { background: 'var(--info-light)', color: 'var(--info)' },
  parcial:    { background: 'var(--warning-light)', color: 'var(--warning)' },
  entregada:  { background: 'var(--success-light)', color: 'var(--success)' },
  cancelada:  { background: 'var(--danger-light)', color: 'var(--danger)' },
};

const STATUS_LABELS: Record<PrescriptionStatus, string> = {
  pendiente: 'Pendiente',
  procesada: 'Procesada',
  parcial:   'Parcial',
  entregada: 'Entregada',
  cancelada: 'Cancelada',
};

const AVAIL_STYLES: Record<Availability, React.CSSProperties> = {
  disponible:    { background: 'var(--success-light)', color: 'var(--success)' },
  parcial:       { background: 'var(--warning-light)', color: 'var(--warning)' },
  no_disponible: { background: 'var(--danger-light)',  color: 'var(--danger)' },
};

const AVAIL_LABELS: Record<Availability, string> = {
  disponible:    'Disponible',
  parcial:       'Parcial',
  no_disponible: 'No disponible',
};

const BASE: React.CSSProperties = {
  display: 'inline-block',
  fontSize: '.6875rem',
  fontWeight: 700,
  padding: '.15rem .5rem',
  borderRadius: 'var(--radius-pill)',
};

export function StatusBadge({ estado }: { estado: PrescriptionStatus }) {
  return (
    <span style={{ ...BASE, ...STATUS_STYLES[estado] }}>
      {STATUS_LABELS[estado]}
    </span>
  );
}

export function AvailabilityBadge({ disponibilidad }: { disponibilidad: Availability }) {
  return (
    <span style={{ ...BASE, ...AVAIL_STYLES[disponibilidad] }}>
      {AVAIL_LABELS[disponibilidad]}
    </span>
  );
}
