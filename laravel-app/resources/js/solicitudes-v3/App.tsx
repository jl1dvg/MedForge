import React, { useCallback, useEffect, useRef, useState } from 'react';
import type { AppConfig, Filters, KanbanColumn, KanbanSlug, Solicitud } from './types';
import { fetchKanbanData } from './api';
import { FilterBar } from './components/FilterBar';
import { MetricsBar } from './components/MetricsBar';
import { KanbanColumn as KanbanCol } from './components/KanbanColumn';
import { ListView } from './components/ListView';
import { SolicitudModal } from './components/SolicitudModal';

const VIEW_KEY = 'medf:solicitudes-v3:view-mode';

const DEFAULT_FILTERS: Filters = {
  search: '',
  afiliacion: '',
  doctor: '',
  prioridad: '',
  sede: '',
  date_from: '',
  date_to: '',
};

interface Props {
  config: AppConfig;
}

export function App({ config }: Props) {
  const [data, setData] = useState<Record<KanbanSlug, Solicitud[]> | null>(null);
  const [options, setOptions] = useState<{ afiliaciones: string[]; doctores: string[] }>({ afiliaciones: [], doctores: [] });
  const [filters, setFilters] = useState<Filters>({ ...DEFAULT_FILTERS, ...config.initialFilters });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<Solicitud | null>(null);
  const [viewMode, setViewMode] = useState<'kanban' | 'list'>(
    () => (localStorage.getItem(VIEW_KEY) as 'kanban' | 'list') ?? 'kanban'
  );

  const abortRef = useRef<AbortController | null>(null);

  const load = useCallback(async (f: Filters) => {
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    setError(null);
    try {
      const result = await fetchKanbanData(config.kanbanEndpoint, f);
      setData(result.data as Record<KanbanSlug, Solicitud[]>);
      setOptions(result.options);
    } catch (e) {
      if ((e as Error).name !== 'AbortError') {
        setError('No se pudo cargar las solicitudes. Intente de nuevo.');
      }
    } finally {
      setLoading(false);
    }
  }, [config.kanbanEndpoint]);

  useEffect(() => { load(filters); }, []);

  function handleFilterChange(partial: Partial<Filters>) {
    const next = { ...filters, ...partial };
    setFilters(next);
    load(next);
  }

  function handleViewChange(v: 'kanban' | 'list') {
    setViewMode(v);
    localStorage.setItem(VIEW_KEY, v);
  }

  function handleEstadoUpdated(id: number, nuevoEstado: KanbanSlug) {
    if (!data) return;
    // move card to new column optimistically
    const next = { ...data } as Record<KanbanSlug, Solicitud[]>;
    let moved: Solicitud | undefined;
    for (const slug of Object.keys(next) as KanbanSlug[]) {
      const idx = next[slug].findIndex((s) => s.id === id);
      if (idx !== -1) {
        [moved] = next[slug].splice(idx, 1);
        break;
      }
    }
    if (moved) {
      const updated = { ...moved, estado: nuevoEstado };
      next[nuevoEstado] = [updated, ...(next[nuevoEstado] ?? [])];
      if (selected?.id === id) setSelected(updated);
    }
    setData(next);
  }

  // Flat list for list view
  const allItems = data ? Object.values(data).flat() : [];

  return (
    <div className="v3-app">
      <FilterBar
        filters={filters}
        options={options}
        loading={loading}
        onFilterChange={handleFilterChange}
        onRefresh={() => load(filters)}
        viewMode={viewMode}
        onViewChange={handleViewChange}
      />

      <MetricsBar data={data} />

      {error && (
        <div className="v3-error-banner" role="alert">
          {error}
          <button className="v3-btn v3-btn-ghost" onClick={() => load(filters)}>Reintentar</button>
        </div>
      )}

      {loading && !data && (
        <div className="v3-loading" aria-live="polite">Cargando solicitudes…</div>
      )}

      {viewMode === 'kanban' && data && (
        <div className="v3-kanban-shell" aria-label="Tablero kanban">
          <div className="v3-kanban">
            {config.kanbanColumns.map((col) => (
              <KanbanCol
                key={col.slug}
                col={col}
                items={data[col.slug as KanbanSlug] ?? []}
                onOpen={setSelected}
              />
            ))}
          </div>
        </div>
      )}

      {viewMode === 'list' && data && (
        <ListView items={allItems} onOpen={setSelected} />
      )}

      <SolicitudModal
        sol={selected}
        actualizarEstadoEndpoint={config.actualizarEstadoEndpoint}
        onClose={() => setSelected(null)}
        onEstadoUpdated={handleEstadoUpdated}
      />
    </div>
  );
}
