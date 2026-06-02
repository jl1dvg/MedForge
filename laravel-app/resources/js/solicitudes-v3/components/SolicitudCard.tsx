import React from 'react';
import type { Solicitud } from '../types';
import { SlaChip } from './SlaChip';
import { AlertBadges } from './AlertBadges';

function initials(name: string): string {
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0] ?? '')
    .join('')
    .toUpperCase();
}

function prioridadColor(p: string): string {
  switch (p?.toLowerCase()) {
    case 'urgente': return '#ef4444';
    case 'alta':    return '#f97316';
    case 'media':   return '#eab308';
    default:        return '#64748b';
  }
}

interface Props {
  sol: Solicitud;
  onOpen: (sol: Solicitud) => void;
}

export function SolicitudCard({ sol, onOpen }: Props) {
  const hasAlerts = [
    sol.alert_reprogramacion,
    sol.alert_documentos_faltantes,
    sol.alert_derivacion_vencida,
    sol.alert_tarea_vencida,
  ].some(Boolean);

  return (
    <article
      className={`v3-card${hasAlerts ? ' v3-card--alert' : ''}`}
      onClick={() => onOpen(sol)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => e.key === 'Enter' && onOpen(sol)}
      aria-label={`Solicitud ${sol.paciente}`}
    >
      <div className="v3-card-head">
        <div className="v3-avatar" style={{ borderColor: prioridadColor(sol.prioridad) }}>
          {initials(sol.paciente)}
        </div>
        <div className="v3-card-info">
          <p className="v3-card-name">{sol.paciente}</p>
          <p className="v3-card-proc">{sol.procedimiento || sol.tipo}</p>
        </div>
        <div className="v3-card-badges-col">
          {sol.prioridad && sol.prioridad.toLowerCase() !== 'normal' && (
            <span
              className="v3-prioridad-dot"
              style={{ background: prioridadColor(sol.prioridad) }}
              title={sol.prioridad}
            />
          )}
        </div>
      </div>

      <div className="v3-card-meta">
        <span className="v3-card-meta-item" title="Doctor">
          {sol.doctor || '—'}
        </span>
        {sol.afiliacion && (
          <span className="v3-card-meta-item v3-chip-afil">{sol.afiliacion}</span>
        )}
      </div>

      <div className="v3-card-footer">
        <SlaChip status={sol.sla_status} />
        <div className="v3-card-stats">
          {!!sol.crm_total_notas && (
            <span title="Notas">📝 {sol.crm_total_notas}</span>
          )}
          {!!sol.crm_tareas_pendientes && (
            <span title="Tareas pendientes">✅ {sol.crm_tareas_pendientes}</span>
          )}
          {!!sol.crm_total_adjuntos && (
            <span title="Adjuntos">📎 {sol.crm_total_adjuntos}</span>
          )}
        </div>
      </div>

      <AlertBadges sol={sol} />
    </article>
  );
}
