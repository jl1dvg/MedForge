import React from 'react';
import type { KanbanColumn as Col, Solicitud } from '../types';
import { SolicitudCard } from './SolicitudCard';

const SLUG_ACCENT: Record<string, string> = {
  recibida:            '#6366f1',
  llamado:             '#0ea5e9',
  'revision-codigos':  '#8b5cf6',
  'espera-documentos': '#f59e0b',
  'apto-oftalmologo':  '#10b981',
  'apto-anestesia':    '#14b8a6',
  'listo-para-agenda': '#22c55e',
  programada:          '#3b82f6',
  completado:          '#64748b',
};

interface Props {
  col: Col;
  items: Solicitud[];
  onOpen: (sol: Solicitud) => void;
}

export function KanbanColumn({ col, items, onOpen }: Props) {
  const accent = SLUG_ACCENT[col.slug] ?? '#94a3b8';
  return (
    <div className="v3-col" style={{ '--col-accent': accent } as React.CSSProperties}>
      <div className="v3-col-head">
        <span className="v3-col-accent-bar" />
        <h3 className="v3-col-title">{col.label}</h3>
        <span className="v3-col-count">{items.length}</span>
      </div>
      <div className="v3-col-body">
        {items.length === 0 ? (
          <p className="v3-empty">Sin solicitudes</p>
        ) : (
          items.map((sol) => (
            <SolicitudCard key={sol.id} sol={sol} onOpen={onOpen} />
          ))
        )}
      </div>
    </div>
  );
}
