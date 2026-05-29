import React from 'react';
import type { CrmOpportunity } from '../types';
import { OpportunityRow } from './OpportunityRow';

interface Props {
  opportunities: CrmOpportunity[];
  loading: boolean;
  onSelect: (opp: CrmOpportunity) => void;
}

const HEADERS = ['Paciente', 'Etapa', 'Fase', 'Origen', 'Última actividad', 'Acción'];

export function OpportunityTable({ opportunities, loading, onSelect }: Props) {
  return (
    <div className="crm-table-wrap">
      <table className="crm-table">
        <thead>
          <tr>
            {HEADERS.map(h => <th key={h}>{h}</th>)}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr>
              <td colSpan={6} style={{ textAlign: 'center', padding: '2.5rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
                Cargando...
              </td>
            </tr>
          )}
          {!loading && opportunities.length === 0 && (
            <tr>
              <td colSpan={6} style={{ textAlign: 'center', padding: '2.5rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
                No hay oportunidades con estos filtros
              </td>
            </tr>
          )}
          {!loading && opportunities.map(opp => (
            <OpportunityRow key={opp.id} opp={opp} onClick={onSelect} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
