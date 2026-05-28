import React from 'react';
import type { CrmOpportunity } from '../types';
import { OpportunityRow } from './OpportunityRow';

interface Props {
  opportunities: CrmOpportunity[];
  loading: boolean;
  onSelect: (opp: CrmOpportunity) => void;
}

export function OpportunityTable({ opportunities, loading, onSelect }: Props) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table className="w-full">
        <thead>
          <tr className="bg-slate-50 border-b border-slate-200">
            {['Paciente / Contacto', 'Etapa', 'Origen', 'Asignado a', 'Tiempo', 'Accion'].map(h => (
              <th key={h} className="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr><td colSpan={6} className="text-center py-10 text-slate-400 text-sm">Cargando...</td></tr>
          )}
          {!loading && opportunities.length === 0 && (
            <tr><td colSpan={6} className="text-center py-10 text-slate-400 text-sm">No hay oportunidades con estos filtros</td></tr>
          )}
          {!loading && opportunities.map(opp => (
            <OpportunityRow key={opp.id} opp={opp} onClick={onSelect} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
