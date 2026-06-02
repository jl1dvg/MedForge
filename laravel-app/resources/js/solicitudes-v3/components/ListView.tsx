import React from 'react';
import type { KanbanSlug, Solicitud } from '../types';
import { SlaChip } from './SlaChip';
import { AlertBadges } from './AlertBadges';

const ESTADO_LABELS: Record<KanbanSlug, string> = {
  'recibida':            'Recibida',
  'llamado':             'Llamado',
  'revision-codigos':    'Revisión códigos',
  'espera-documentos':   'Documentación',
  'apto-oftalmologo':    'Apto oftalm.',
  'apto-anestesia':      'Apto anestesia',
  'listo-para-agenda':   'Listo agenda',
  'programada':          'Programada',
  'completado':          'Completado',
};

interface Props {
  items: Solicitud[];
  onOpen: (sol: Solicitud) => void;
}

export function ListView({ items, onOpen }: Props) {
  if (!items.length) {
    return <p className="v3-empty v3-empty-list">No hay solicitudes que coincidan con los filtros.</p>;
  }

  return (
    <div className="v3-list-table-wrap">
      <table className="v3-list-table" role="grid">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Procedimiento</th>
            <th>Doctor</th>
            <th>Afiliación</th>
            <th>Estado</th>
            <th>SLA</th>
            <th>Alertas</th>
            <th>CRM</th>
          </tr>
        </thead>
        <tbody>
          {items.map((sol) => (
            <tr
              key={sol.id}
              className="v3-list-row"
              onClick={() => onOpen(sol)}
              role="button"
              tabIndex={0}
              onKeyDown={(e) => e.key === 'Enter' && onOpen(sol)}
            >
              <td>
                <span className="v3-list-name">{sol.paciente}</span>
                <span className="v3-list-hc">HC: {sol.hc_number}</span>
              </td>
              <td>{sol.procedimiento || sol.tipo}</td>
              <td>{sol.doctor || '—'}</td>
              <td>{sol.afiliacion || '—'}</td>
              <td><span className="v3-estado-tag">{ESTADO_LABELS[sol.estado] ?? sol.estado}</span></td>
              <td><SlaChip status={sol.sla_status} /></td>
              <td><AlertBadges sol={sol} /></td>
              <td className="v3-list-crm">
                {!!sol.crm_tareas_pendientes && <span title="Tareas">✅ {sol.crm_tareas_pendientes}</span>}
                {!!sol.crm_total_notas && <span title="Notas">📝 {sol.crm_total_notas}</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
