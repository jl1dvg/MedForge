import React, { useRef } from 'react';
import type { Filters } from '../types';

interface Props {
  filters: Filters;
  options: { afiliaciones: string[]; doctores: string[] };
  loading: boolean;
  onFilterChange: (f: Partial<Filters>) => void;
  onRefresh: () => void;
  viewMode: 'kanban' | 'list';
  onViewChange: (v: 'kanban' | 'list') => void;
}

export function FilterBar({ filters, options, loading, onFilterChange, onRefresh, viewMode, onViewChange }: Props) {
  const searchRef = useRef<HTMLInputElement>(null);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    onFilterChange({ search: searchRef.current?.value ?? '' });
  }

  return (
    <div className="v3-toolbar" role="search">
      <form className="v3-search-form" onSubmit={handleSearch}>
        <input
          ref={searchRef}
          type="search"
          className="v3-input"
          placeholder="Buscar paciente, HC, doctor…"
          defaultValue={filters.search}
          aria-label="Buscar solicitudes"
        />
        <button className="v3-btn v3-btn-primary" type="submit" disabled={loading}>
          Buscar
        </button>
      </form>

      <div className="v3-filter-chips">
        <select
          className="v3-select"
          value={filters.afiliacion}
          onChange={(e) => onFilterChange({ afiliacion: e.target.value })}
          aria-label="Filtrar por afiliación"
        >
          <option value="">Todas las afiliaciones</option>
          {options.afiliaciones.map((a) => (
            <option key={a} value={a}>{a}</option>
          ))}
        </select>

        <select
          className="v3-select"
          value={filters.doctor}
          onChange={(e) => onFilterChange({ doctor: e.target.value })}
          aria-label="Filtrar por doctor"
        >
          <option value="">Todos los doctores</option>
          {options.doctores.map((d) => (
            <option key={d} value={d}>{d}</option>
          ))}
        </select>

        <select
          className="v3-select"
          value={filters.prioridad}
          onChange={(e) => onFilterChange({ prioridad: e.target.value })}
          aria-label="Filtrar por prioridad"
        >
          <option value="">Todas las prioridades</option>
          {['Urgente', 'Alta', 'Media', 'Normal'].map((p) => (
            <option key={p} value={p}>{p}</option>
          ))}
        </select>

        <input
          type="date"
          className="v3-input v3-input-date"
          value={filters.date_from}
          onChange={(e) => onFilterChange({ date_from: e.target.value })}
          aria-label="Desde"
          title="Desde"
        />
        <input
          type="date"
          className="v3-input v3-input-date"
          value={filters.date_to}
          onChange={(e) => onFilterChange({ date_to: e.target.value })}
          aria-label="Hasta"
          title="Hasta"
        />

        {Object.values(filters).some(Boolean) && (
          <button
            className="v3-btn v3-btn-ghost"
            onClick={() => onFilterChange({ search: '', afiliacion: '', doctor: '', prioridad: '', sede: '', date_from: '', date_to: '' })}
            type="button"
          >
            ✕ Limpiar
          </button>
        )}
      </div>

      <div className="v3-toolbar-end">
        <div className="v3-view-toggle" role="group" aria-label="Modo de vista">
          <button
            className={`v3-btn v3-btn-icon${viewMode === 'kanban' ? ' active' : ''}`}
            onClick={() => onViewChange('kanban')}
            type="button"
            title="Vista kanban"
            aria-pressed={viewMode === 'kanban'}
          >
            ▦
          </button>
          <button
            className={`v3-btn v3-btn-icon${viewMode === 'list' ? ' active' : ''}`}
            onClick={() => onViewChange('list')}
            type="button"
            title="Vista lista"
            aria-pressed={viewMode === 'list'}
          >
            ☰
          </button>
        </div>
        <button
          className="v3-btn v3-btn-ghost"
          onClick={onRefresh}
          disabled={loading}
          type="button"
          aria-label="Actualizar"
        >
          {loading ? '↻ Cargando…' : '↻ Actualizar'}
        </button>
      </div>
    </div>
  );
}
