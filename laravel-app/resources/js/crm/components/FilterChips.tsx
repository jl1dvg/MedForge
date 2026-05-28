import React from 'react';

export interface ActiveFilters {
  stage: string;
  source: string;
  urgent: boolean;
  search: string;
}

interface Props {
  filters: ActiveFilters;
  total: number;
  urgentCount: number;
  onChange: (f: Partial<ActiveFilters>) => void;
}

const STAGES = [
  { value: '', label: 'Todas' },
  { value: '__urgent__', label: 'Urgentes' },
  { value: 'nuevo', label: 'Nuevas' },
  { value: 'propuesta_enviada', label: 'Propuesta' },
];

const SOURCES = [
  { value: '', label: 'Todos los origenes' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'solicitud', label: 'Solicitudes' },
  { value: 'examen', label: 'Examenes' },
];

export function FilterChips({ filters, total, urgentCount, onChange }: Props) {
  return (
    <div className="flex items-center gap-2 mb-4 flex-wrap">
      {STAGES.map(({ value, label }) => {
        const isActive = value === '__urgent__' ? filters.urgent : filters.stage === value && !filters.urgent;
        return (
          <button
            key={value}
            onClick={() => value === '__urgent__'
              ? onChange({ urgent: !filters.urgent, stage: '' })
              : onChange({ stage: value, urgent: false })}
            className={`px-3 py-1.5 rounded-full text-xs font-semibold border-2 transition-all
              ${isActive
                ? value === '__urgent__'
                  ? 'bg-red-100 text-red-700 border-red-300'
                  : 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'}`}
          >
            {label} {value === '' && `(${total})`}
            {value === '__urgent__' && `(${urgentCount})`}
          </button>
        );
      })}

      <div className="h-5 w-px bg-slate-200 mx-1" />

      {SOURCES.slice(1).map(({ value, label }) => (
        <button
          key={value}
          onClick={() => onChange({ source: filters.source === value ? '' : value })}
          className={`px-3 py-1.5 rounded-full text-xs font-semibold border-2 transition-all
            ${filters.source === value
              ? 'bg-slate-700 text-white border-slate-700'
              : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'}`}
        >
          {label}
        </button>
      ))}

      <input
        className="ml-auto border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-600 bg-white outline-none w-52"
        placeholder="Buscar paciente o cedula..."
        value={filters.search}
        onChange={e => onChange({ search: e.target.value })}
      />
    </div>
  );
}
