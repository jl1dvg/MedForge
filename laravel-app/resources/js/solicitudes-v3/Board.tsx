// ============================================================
// MedForge · Solicitudes v3 — Toolbar, Column, Board, TableView
// ============================================================
import React from 'react';
import type { Solicitud, KanbanColumn, Phase, Filters } from './types';
import { Card, AfilChip, SlaBadge, type DndContext } from './components';

// ---- Toolbar ------------------------------------------------

interface ToolbarProps {
  filters: Filters;
  setFilters: React.Dispatch<React.SetStateAction<Filters>>;
  preset: string;
  setPreset: (p: string) => void;
  view: string;
  setView: (v: string) => void;
  doctores: string[];
  afiliaciones: string[];
  sedes: string[];
  onExportExcel: () => void;
  onExportPdf: () => void;
  lastRefreshedLabel: string | null;
}

export function Toolbar({ filters, setFilters, preset, setPreset, view, setView, doctores, afiliaciones, sedes, onExportExcel, onExportPdf, lastRefreshedLabel }: ToolbarProps) {
  return (
    <div className="toolbar">
      <div className="search-box">
        <i className="mdi mdi-magnify"></i>
        <input
          placeholder="Buscar paciente, HC o procedimiento…"
          value={filters.search}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFilters((f: Filters) => ({ ...f, search: e.target.value }))}
        />
      </div>

      <select
        className="filter-select"
        value={filters.afiliacion}
        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters((f: Filters) => ({ ...f, afiliacion: e.target.value }))}
      >
        <option value="">Todas las aseguradoras</option>
        {afiliaciones.map((a) => <option key={a} value={a}>{a}</option>)}
      </select>

      <select
        className="filter-select"
        value={filters.doctor}
        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters((f: Filters) => ({ ...f, doctor: e.target.value }))}
      >
        <option value="">Todos los doctores</option>
        {doctores.map((d) => <option key={d} value={d}>{d}</option>)}
      </select>

      <select
        className="filter-select"
        value={filters.sede}
        onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setFilters((f: Filters) => ({ ...f, sede: e.target.value }))}
      >
        <option value="">Todas las sedes</option>
        {sedes.map((s) => <option key={s} value={s}>{s}</option>)}
      </select>

      <div className="date-range-filter" aria-label="Rango de fechas">
        <i className="mdi mdi-calendar-range-outline"></i>
        <input
          type="date"
          value={filters.date_from}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFilters((f: Filters) => ({ ...f, date_from: e.target.value }))}
        />
        <span>—</span>
        <input
          type="date"
          value={filters.date_to}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFilters((f: Filters) => ({ ...f, date_to: e.target.value }))}
        />
      </div>

      <div className="preset-group">
        <button
          className={`preset is-danger ${preset === 'urgentes' ? 'is-active' : ''}`}
          onClick={() => setPreset(preset === 'urgentes' ? '' : 'urgentes')}
        >
          <i className="mdi mdi-alert-circle-outline"></i>Urgentes
        </button>
      </div>

      <div className="toolbar-spacer"></div>

      {lastRefreshedLabel && (
        <span style={{ fontSize: 11, color: 'var(--fg-mute)', whiteSpace: 'nowrap', paddingRight: 4 }}>
          {lastRefreshedLabel}
        </span>
      )}

      <div className="preset-group">
        <a
          href="/v2/solicitudes/turnero"
          target="_blank"
          rel="noreferrer"
          className="preset"
          style={{ textDecoration: 'none' }}
          title="Abrir pantalla de turnero"
        >
          <i className="mdi mdi-television-play"></i>Turnero
        </a>
        <button className="preset" onClick={onExportExcel} title="Exportar a Excel">
          <i className="mdi mdi-microsoft-excel"></i>Excel
        </button>
        <button className="preset" onClick={onExportPdf} title="Exportar a PDF">
          <i className="mdi mdi-file-pdf-box"></i>PDF
        </button>
      </div>

      <div className="view-toggle">
        <button className={view === 'kanban' ? 'is-active' : ''} onClick={() => setView('kanban')}>
          <i className="mdi mdi-view-column-outline"></i>Kanban
        </button>
        <button className={view === 'tabla' ? 'is-active' : ''} onClick={() => setView('tabla')}>
          <i className="mdi mdi-table"></i>Tabla
        </button>
        <button className={view === 'conciliacion' ? 'is-active' : ''} onClick={() => setView('conciliacion')}>
          <i className="mdi mdi-sync"></i>Conciliación
        </button>
      </div>
    </div>
  );
}

// ---- Column -------------------------------------------------

const PHASE_COL_DOT: Record<string, string> = {
  ingreso: '#3d7ac7', validacion: '#6f67d8', aptitud: '#1f9d7a', agenda: '#d59623',
};

interface ColumnProps {
  col: KanbanColumn;
  items: Solicitud[];
  onOpen: (id: number) => void;
  onAdvance: (id: number) => void;
  dnd: DndContext;
  lastSlug: string;
}

export function Column({ col, items, onOpen, onAdvance, dnd, lastSlug }: ColumnProps) {
  const breaches = items.filter((s) => s.sla_status === 'vencido').length;
  return (
    <div
      className={`col ${dnd.dropTarget === col.slug ? 'is-droptarget' : ''}`}
      onDragOver={(e: React.DragEvent<HTMLDivElement>) => dnd.onDragOver(e, col.slug)}
      onDragLeave={dnd.onDragLeave}
      onDrop={(e: React.DragEvent<HTMLDivElement>) => dnd.onDrop(e, col.slug)}
    >
      <div className="col-head">
        <span className="col-dot" style={{ background: PHASE_COL_DOT[col.phase] }}></span>
        <h3 className="col-title">{col.label}</h3>
        {breaches > 0 && (
          <span className="col-breach"><i className="mdi mdi-alert"></i>{breaches}</span>
        )}
        <span className="col-count">{items.length}</span>
      </div>
      <div className="col-body">
        {items.length === 0 && <div className="col-empty">Sin solicitudes</div>}
        {items.map((sol) => (
          <Card
            key={sol.id}
            sol={sol}
            onOpen={onOpen}
            onAdvance={onAdvance}
            isLast={col.slug === lastSlug}
            dnd={dnd}
          />
        ))}
      </div>
    </div>
  );
}

// ---- Board --------------------------------------------------

interface BoardProps {
  columns: KanbanColumn[];
  phases: Phase[];
  byColumn: Record<string, Solicitud[]>;
  onOpen: (id: number) => void;
  onAdvance: (id: number) => void;
  dnd: DndContext;
  groupPhases: boolean;
}

export function Board({ columns, phases, byColumn, onOpen, onAdvance, dnd, groupPhases }: BoardProps) {
  const lastSlug = columns[columns.length - 1].slug;
  if (!groupPhases) {
    return (
      <div className="board-scroll">
        <div className="board flat">
          <div className="pg-cols">
            {columns.map((col) => (
              <Column key={col.slug} col={col} items={byColumn[col.slug] || []}
                onOpen={onOpen} onAdvance={onAdvance} dnd={dnd} lastSlug={lastSlug} />
            ))}
          </div>
        </div>
      </div>
    );
  }
  return (
    <div className="board-scroll">
      <div className="board">
        {phases.map((ph) => {
          const phaseCols = columns.filter((c) => c.phase === ph.key);
          return (
            <div className="phase-group" key={ph.key}>
              <div className="phase-band">
                <i className={`mdi ${ph.icon}`}></i>{ph.label}<span className="ph-line"></span>
              </div>
              <div className="pg-cols">
                {phaseCols.map((col) => (
                  <Column
                    key={col.slug}
                    col={col}
                    items={byColumn[col.slug] || []}
                    onOpen={onOpen}
                    onAdvance={onAdvance}
                    dnd={dnd}
                    lastSlug={lastSlug}
                  />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ---- TableView ----------------------------------------------

interface TableViewProps {
  rows: Solicitud[];
  onOpen: (id: number) => void;
}

export function TableView({ rows, onOpen }: TableViewProps) {
  if (!rows.length) {
    return (
      <div className="board-scroll">
        <div className="empty-state">
          <i className="mdi mdi-clipboard-search-outline"></i>
          <h3>Sin resultados</h3>
          <p>Ajusta los filtros para ver solicitudes.</p>
        </div>
      </div>
    );
  }
  return (
    <div className="board-scroll">
      <div className="table-wrap">
        <table className="sol-table">
          <thead>
            <tr>
              <th>Paciente</th><th>Procedimiento</th><th>Doctor</th><th>Afiliación</th>
              <th>Estado</th><th>Progreso</th><th>SLA</th><th>Turno</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((sol) => (
              <tr key={sol.id} onClick={() => onOpen(sol.id)}>
                <td>
                  <div className="t-name" style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                    {sol.prioridad === 'urgente' && <span className="urgent-flag"></span>}
                    {sol.full_name}
                  </div>
                  <div className="t-sub">HC {sol.hc_number} · {sol.form_id}</div>
                </td>
                <td>
                  <div style={{ fontWeight: 600, fontSize: 12.5 }}>
                    {sol.procedimiento_short}{' '}
                    <span className="card-sla sla-ok" style={{ padding: '1px 6px', fontSize: 10 }}>{sol.ojo}</span>
                  </div>
                </td>
                <td style={{ fontSize: 12.5 }}>{sol.doctor}</td>
                <td><AfilChip sol={sol} /></td>
                <td><span className="state-pill">{sol.estado_label}</span></td>
                <td>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span className="t-mini-bar"><i style={{ width: `${sol.checklist_progress.percent}%` }}></i></span>
                    <span style={{ fontSize: 11, color: 'var(--fg-mute)', fontWeight: 600 }}>{sol.checklist_progress.percent}%</span>
                  </div>
                </td>
                <td><SlaBadge sol={sol} /></td>
                <td style={{ fontSize: 12, color: 'var(--fg-3)' }}>{sol.turno || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
