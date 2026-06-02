import React from 'react';
import type { SlaStatus } from '../types';

const SLA_META: Record<SlaStatus, { label: string; icon: string; cls: string }> = {
  en_rango:   { label: 'En rango',       icon: '✓',  cls: 'sla-ok' },
  advertencia:{ label: 'Seguimiento 72h', icon: '⏳', cls: 'sla-warn' },
  critico:    { label: 'Crítico 24h',    icon: '⚠',  cls: 'sla-crit' },
  vencido:    { label: 'SLA vencido',    icon: '✗',  cls: 'sla-over' },
  sin_fecha:  { label: 'Sin programar',  icon: '—',  cls: 'sla-none' },
  cerrado:    { label: 'Cerrado',        icon: '🔒', cls: 'sla-closed' },
};

export function SlaChip({ status }: { status: SlaStatus }) {
  const meta = SLA_META[status] ?? SLA_META.sin_fecha;
  return (
    <span className={`v3-sla-chip ${meta.cls}`} title={meta.label}>
      {meta.icon} {meta.label}
    </span>
  );
}
