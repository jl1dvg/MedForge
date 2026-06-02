import React from 'react';
import type { Solicitud } from '../types';

const ALERTS: Array<{ field: keyof Solicitud; label: string; cls: string }> = [
  { field: 'alert_reprogramacion',          label: 'Reprogramar',      cls: 'danger' },
  { field: 'alert_pendiente_consentimiento', label: 'Consentimiento',   cls: 'warning' },
  { field: 'alert_documentos_faltantes',    label: 'Docs faltantes',   cls: 'warning' },
  { field: 'alert_autorizacion_pendiente',  label: 'Autorización',     cls: 'info' },
  { field: 'alert_derivacion_vencida',      label: 'Deriv. vencida',   cls: 'danger' },
  { field: 'alert_derivacion_por_vencer',   label: 'Deriv. por vencer', cls: 'warning' },
  { field: 'alert_derivacion_pendiente',    label: 'Deriv. pendiente', cls: 'secondary' },
  { field: 'alert_tarea_vencida',           label: 'Tarea vencida',    cls: 'danger' },
  { field: 'alert_sin_responsable',         label: 'Sin responsable',  cls: 'secondary' },
  { field: 'alert_sin_contacto',            label: 'Sin contacto',     cls: 'secondary' },
];

export function AlertBadges({ sol }: { sol: Solicitud }) {
  const active = ALERTS.filter((a) => sol[a.field]);
  if (!active.length) return null;
  return (
    <div className="v3-alert-badges">
      {active.map((a) => (
        <span key={a.field} className={`v3-badge v3-badge-${a.cls}`}>{a.label}</span>
      ))}
    </div>
  );
}
