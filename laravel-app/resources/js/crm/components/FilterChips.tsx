import React from 'react';
import type { Stage, Phase } from '../types';

export interface ActiveFilters {
  stage: Stage | '';
  source: string;
  phase: Phase | '';
  afiliacion: string;   // particular | privado | fundacional | publico | ''
  urgent: boolean;
  search: string;
}

interface Props {
  filters: ActiveFilters;
  total: number;
  urgentCount: number;
  onChange: (partial: Partial<ActiveFilters>) => void;
}

const STAGES: { value: Stage | ''; label: string }[] = [
  { value: '', label: 'Todas las etapas' },
  { value: 'nuevo', label: 'Nuevo' },
  { value: 'contactado', label: 'Contactado' },
  { value: 'en_evaluacion', label: 'En evaluación' },
  { value: 'propuesta', label: 'Propuesta' },
  { value: 'comprometido', label: 'Comprometido' },
  { value: 'ganado', label: 'Ganado' },
  { value: 'perdido', label: 'Perdido' },
];

export function FilterChips({ filters, total, urgentCount, onChange }: Props) {
  return (
    <div style={{ marginBottom: '1rem' }}>
      <div className="crm-filter-row" style={{ marginBottom: '.5rem' }}>
        <span style={{ fontSize: '.75rem', color: 'var(--fg-mute)', marginRight: '.25rem' }}>
          {total} oportunidades
        </span>
        {urgentCount > 0 && (
          <button
            className={`crm-chip${filters.urgent ? ' active' : ''}`}
            onClick={() => onChange({ urgent: !filters.urgent })}
          >
            ⚠ Sin contactar ({urgentCount})
          </button>
        )}
        <button
          className={`crm-chip${filters.phase === 'operational' ? ' active' : ''}`}
          onClick={() => onChange({ phase: filters.phase === 'operational' ? '' : 'operational' })}
        >
          Operativo
        </button>
        <button
          className={`crm-chip${filters.phase === 'commercial' ? ' active' : ''}`}
          onClick={() => onChange({ phase: filters.phase === 'commercial' ? '' : 'commercial' })}
        >
          Comercial
        </button>
        <span style={{ width: 1, background: 'var(--border)', margin: '0 .25rem', alignSelf: 'stretch' }} />
        <button
          className={`crm-chip${filters.source === 'solicitud' ? ' active' : ''}`}
          onClick={() => onChange({ source: filters.source === 'solicitud' ? '' : 'solicitud' })}
        >
          🔬 Solicitudes
        </button>
        <button
          className={`crm-chip${filters.source === 'examen' ? ' active' : ''}`}
          onClick={() => onChange({ source: filters.source === 'examen' ? '' : 'examen' })}
        >
          🧪 Exámenes
        </button>
        <button
          className={`crm-chip${filters.source === 'whatsapp' ? ' active' : ''}`}
          onClick={() => onChange({ source: filters.source === 'whatsapp' ? '' : 'whatsapp' })}
        >
          💬 WhatsApp
        </button>
        <span style={{ width: 1, background: 'var(--border)', margin: '0 .25rem', alignSelf: 'stretch' }} />
        {[
          { value: 'particular', label: '💳 Particular' },
          { value: 'privado',    label: '🏥 Privado' },
          { value: 'fundacional',label: '🤝 Fundacional' },
        ].map(({ value, label }) => (
          <button
            key={value}
            className={`crm-chip${filters.afiliacion === value ? ' active' : ''}`}
            onClick={() => onChange({ afiliacion: filters.afiliacion === value ? '' : value })}
          >
            {label}
          </button>
        ))}
        <input
          type="text"
          placeholder="Buscar paciente..."
          value={filters.search}
          onChange={e => onChange({ search: e.target.value })}
          style={{
            height: '2rem', padding: '.25rem .625rem', borderRadius: 'var(--radius-pill)',
            border: '1.5px solid var(--border)', fontSize: '.75rem', outline: 'none',
            background: 'var(--bg-surface)', color: 'var(--fg-1)', marginLeft: 'auto',
          }}
        />
      </div>
      <div className="crm-filter-row">
        {STAGES.map(({ value, label }) => (
          <button
            key={value || 'all'}
            className={`crm-chip${filters.stage === value ? ' active' : ''}`}
            onClick={() => onChange({ stage: value as Stage | '' })}
          >
            {label}
          </button>
        ))}
      </div>
    </div>
  );
}
