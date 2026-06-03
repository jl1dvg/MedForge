import React, { useState, useCallback } from 'react';
import type { CrmOpportunity, Phase } from './types';
import type { ActiveFilters } from './components/FilterChips';
import { useOpportunities } from './hooks/useOpportunities';
import { useStats } from './hooks/useStats';
import { StatsBar } from './components/StatsBar';
import { FilterChips } from './components/FilterChips';
import { OpportunityTable } from './components/OpportunityTable';
import { DetailPanel } from './components/DetailPanel';

const DEFAULT_FILTERS: ActiveFilters = { stage: '', source: '', phase: '', afiliacion: '', urgent: false, search: '' };
const PAGE_SIZE = 25;

export default function App() {
  const [filters, setFilters] = useState<ActiveFilters>(DEFAULT_FILTERS);
  const [selected, setSelected] = useState<CrmOpportunity | null>(null);
  const [page, setPage] = useState(1);

  const apiFilters = {
    stage: filters.stage || undefined,
    source: filters.source || undefined,
    phase: (filters.phase || undefined) as Phase | undefined,
    afiliacion: filters.afiliacion || undefined,
    urgent: filters.urgent || undefined,
    search: filters.search || undefined,
    limit: PAGE_SIZE,
    offset: (page - 1) * PAGE_SIZE,
  };

  const { data, meta, loading, error, refresh } = useOpportunities(apiFilters);
  const { stats } = useStats();
  const totalPages = Math.max(1, Math.ceil(meta.total / PAGE_SIZE));

  const handleFilterChange = useCallback((partial: Partial<ActiveFilters>) => {
    setFilters(f => ({ ...f, ...partial }));
    setPage(1); // reset to first page on filter change
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
        {/* Pagination */}
        {totalPages > 1 && (
          <div style={{
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            gap: '.75rem', padding: '1rem 0', fontSize: '.8125rem', color: 'var(--fg-2)',
          }}>
            <button
              className="btn btn-sm"
              disabled={page <= 1 || loading}
              onClick={() => setPage(p => p - 1)}
              style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
              ← Anterior
            </button>
            <span style={{ fontWeight: 600 }}>
              Página {page} de {totalPages}
              <span style={{ fontWeight: 400, color: 'var(--fg-mute)', marginLeft: '.375rem' }}>
                ({meta.total} total)
              </span>
            </span>
            <button
              className="btn btn-sm"
              disabled={page >= totalPages || loading}
              onClick={() => setPage(p => p + 1)}
              style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
              Siguiente →
            </button>
          </div>
        )}
      </div>

      {selected && (
        <DetailPanel opportunity={selected} onClose={() => setSelected(null)} onUpdated={handleUpdated} />
      )}
    </div>
  );
}
