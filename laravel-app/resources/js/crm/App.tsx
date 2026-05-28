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

  const { data, meta, loading, refresh } = useOpportunities(apiFilters);
  const { stats } = useStats();

  const handleFilterChange = useCallback((partial: Partial<ActiveFilters>) => {
    setFilters(f => ({ ...f, ...partial }));
  }, []);

  const handleUpdated = useCallback((updated: CrmOpportunity) => {
    setSelected(updated);
    void refresh();
  }, [refresh]);

  return (
    <div className="flex h-screen bg-slate-100 overflow-hidden">
      <aside className="w-52 bg-slate-800 flex flex-col flex-shrink-0">
        <div className="px-4 py-5 border-b border-slate-700">
          <p className="text-white font-bold text-base">MedForge</p>
          <p className="text-slate-400 text-xs">Panel Comercial</p>
        </div>
        <nav className="p-2 flex-1">
          {[
            { icon: 'O', label: 'Oportunidades', active: true, badge: stats?.urgent ?? 0 },
            { icon: 'C', label: 'Contactos',     active: false, badge: 0 },
            { icon: 'R', label: 'Reportes',      active: false, badge: 0 },
          ].map(({ icon, label, active, badge }) => (
            <div
              key={label}
              className={`flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm cursor-pointer mb-0.5
                ${active ? 'bg-blue-500 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white'}`}
            >
              <span>{icon}</span>
              <span>{label}</span>
              {badge > 0 && (
                <span className="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                  {badge}
                </span>
              )}
            </div>
          ))}
        </nav>
      </aside>

      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="bg-white border-b border-slate-200 px-6 h-14 flex items-center justify-between flex-shrink-0">
          <h1 className="text-lg font-bold text-slate-900">Oportunidades</h1>
          <button className="bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-600">
            + Nueva oportunidad
          </button>
        </header>

        <main className="flex-1 overflow-y-auto p-6">
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
        </main>
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
