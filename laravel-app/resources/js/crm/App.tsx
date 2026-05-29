import React, { useState, useCallback } from 'react';
import type { CrmOpportunity, Phase } from './types';
import type { ActiveFilters } from './components/FilterChips';
import { useOpportunities } from './hooks/useOpportunities';
import { useStats } from './hooks/useStats';
import { StatsBar } from './components/StatsBar';
import { FilterChips } from './components/FilterChips';
import { OpportunityTable } from './components/OpportunityTable';
import { DetailPanel } from './components/DetailPanel';

const DEFAULT_FILTERS: ActiveFilters = { stage: '', source: '', phase: '', urgent: false, search: '' };

export default function App() {
  const [filters, setFilters] = useState<ActiveFilters>(DEFAULT_FILTERS);
  const [selected, setSelected] = useState<CrmOpportunity | null>(null);

  const apiFilters = {
    stage: filters.stage || undefined,
    source: filters.source || undefined,
    phase: (filters.phase || undefined) as Phase | undefined,
    urgent: filters.urgent || undefined,
    search: filters.search || undefined,
  };

  const { data, meta, loading, error, refresh } = useOpportunities(apiFilters);
  const { stats } = useStats();

  const handleFilterChange = useCallback((partial: Partial<ActiveFilters>) => {
    setFilters(f => ({ ...f, ...partial }));
  }, []);

  const handleUpdated = useCallback((updated: CrmOpportunity) => {
    setSelected(updated);
    void refresh();
  }, [refresh]);

  return (
    <div className="crm-panel-root">
      <div className="crm-panel-header">
        <div>
          <h1 style={{ margin: 0, fontSize: '1rem', fontWeight: 700, color: 'var(--fg-1)', fontFamily: 'var(--font-display)' }}>
            Pipeline Comercial
          </h1>
          <p style={{ margin: 0, fontSize: '.75rem', color: 'var(--fg-mute)' }}>
            Oportunidades centralizadas — todas las fuentes
          </p>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem' }}>
          {stats && stats.urgent > 0 && (
            <span style={{
              background: 'var(--danger-light)', color: 'var(--danger)',
              fontSize: '.75rem', fontWeight: 700, padding: '.25rem .75rem', borderRadius: 'var(--radius-pill)',
            }}>
              {stats.urgent} sin contactar
            </span>
          )}
          <button className="btn btn-primary btn-sm">+ Nueva oportunidad</button>
        </div>
      </div>

      <div className="crm-panel-body">
        {error && (
          <div style={{
            marginBottom: '1rem', background: 'var(--danger-light)', border: `1px solid var(--danger)`,
            color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
          }}>
            {error}
          </div>
        )}
        <StatsBar stats={stats} />
        <FilterChips filters={filters} total={meta.total} urgentCount={stats?.urgent ?? 0} onChange={handleFilterChange} />
        <OpportunityTable opportunities={data} loading={loading} onSelect={setSelected} />
      </div>

      {selected && (
        <DetailPanel opportunity={selected} onClose={() => setSelected(null)} onUpdated={handleUpdated} />
      )}
    </div>
  );
}
