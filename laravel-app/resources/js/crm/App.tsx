import React, { useState, useCallback } from 'react';
import type { CrmOpportunity } from './types';
import type { ActiveFilters } from './components/FilterChips';
import { useOpportunities } from './hooks/useOpportunities';
import { useStats } from './hooks/useStats';
import { StatsBar } from './components/StatsBar';
import { FilterChips } from './components/FilterChips';
import { OpportunityTable } from './components/OpportunityTable';
import { DetailPanel } from './components/DetailPanel';

const DEFAULT_FILTERS: ActiveFilters = { stage: '', source: '', urgent: false, search: '' };

export default function App() {
  const [filters, setFilters] = useState<ActiveFilters>(DEFAULT_FILTERS);
  const [selected, setSelected] = useState<CrmOpportunity | null>(null);

  const apiFilters = {
    stage: filters.stage || undefined,
    source: filters.source || undefined,
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
    <div className="flex flex-col bg-slate-100" style={{ minHeight: 'calc(100vh - 120px)' }}>
      <div className="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
        <div>
          <h1 className="text-lg font-bold text-slate-900">Oportunidades Comerciales</h1>
          <p className="text-xs text-slate-500">Pipeline centralizado — todas las fuentes</p>
        </div>
        <div className="flex items-center gap-3">
          {stats && stats.urgent > 0 && (
            <span className="bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full">
              {stats.urgent} urgentes
            </span>
          )}
          <button className="bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
            + Nueva oportunidad
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-6">
        {error && (
          <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {error}
          </div>
        )}
        <StatsBar stats={stats} />
        <FilterChips
          filters={filters}
          total={meta.total}
          urgentCount={stats?.urgent ?? 0}
          onChange={handleFilterChange}
        />
        <OpportunityTable
          opportunities={data}
          loading={loading}
          onSelect={setSelected}
        />
      </div>

      {selected && (
        <DetailPanel
          opportunity={selected}
          onClose={() => setSelected(null)}
          onUpdated={handleUpdated}
        />
      )}
    </div>
  );
}
