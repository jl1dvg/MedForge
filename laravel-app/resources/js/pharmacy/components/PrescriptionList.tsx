import React, { useState } from 'react';
import { usePrescriptions } from '../hooks/usePrescriptions';
import { StatusBadge } from './StatusBadge';
import type { PrescriptionStatus } from '../types';

interface Props {
  onSelect: (id: number) => void;
}

const STATUS_OPTIONS: Array<{ value: PrescriptionStatus | ''; label: string }> = [
  { value: '', label: 'Todos los estados' },
  { value: 'pendiente', label: 'Pendiente' },
  { value: 'procesada', label: 'Procesada' },
  { value: 'parcial', label: 'Parcial' },
  { value: 'entregada', label: 'Entregada' },
  { value: 'cancelada', label: 'Cancelada' },
];

const TH: React.CSSProperties = {
  padding: '.5rem 1rem',
  textAlign: 'left',
  color: 'var(--fg-mute)',
  fontWeight: 600,
  fontSize: '.75rem',
  borderBottom: '1px solid var(--border-1)',
  background: 'var(--bg-surface)',
  whiteSpace: 'nowrap',
};

const TD: React.CSSProperties = {
  padding: '.5rem 1rem',
  fontSize: '.8125rem',
  color: 'var(--fg-2)',
  borderBottom: '1px solid var(--border-1)',
  verticalAlign: 'middle',
};

export function PrescriptionList({ onSelect }: Props) {
  const [estado, setEstado] = useState<PrescriptionStatus | ''>('');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [page, setPage] = useState(1);

  // Debounce search
  const handleSearchChange = (val: string) => {
    setSearch(val);
    clearTimeout((window as any).__pharmSearchTimer);
    (window as any).__pharmSearchTimer = setTimeout(() => {
      setDebouncedSearch(val);
      setPage(1);
    }, 350);
  };

  const { data, meta, loading, error } = usePrescriptions({ estado, search: debouncedSearch, page });
  const totalPages = Math.max(1, meta.last_page);

  return (
    <div style={{ padding: '1rem' }}>
      {/* Filter bar */}
      <div style={{
        display: 'flex', gap: '.75rem', marginBottom: '1rem', flexWrap: 'wrap', alignItems: 'center',
      }}>
        <select
          value={estado}
          onChange={e => { setEstado(e.target.value as PrescriptionStatus | ''); setPage(1); }}
          style={{
            padding: '.375rem .75rem', fontSize: '.8125rem',
            border: '1px solid var(--border-1)', borderRadius: 'var(--radius)',
            background: 'var(--bg-card)', color: 'var(--fg-1)',
          }}
        >
          {STATUS_OPTIONS.map(opt => (
            <option key={opt.value} value={opt.value}>{opt.label}</option>
          ))}
        </select>
        <input
          type="text"
          placeholder="Buscar paciente, médico, clínica..."
          value={search}
          onChange={e => handleSearchChange(e.target.value)}
          style={{
            padding: '.375rem .75rem', fontSize: '.8125rem',
            border: '1px solid var(--border-1)', borderRadius: 'var(--radius)',
            background: 'var(--bg-card)', color: 'var(--fg-1)',
            minWidth: 240,
          }}
        />
        <span style={{ fontSize: '.75rem', color: 'var(--fg-mute)', marginLeft: 'auto' }}>
          {meta.total} receta{meta.total !== 1 ? 's' : ''}
        </span>
      </div>

      {error && (
        <div style={{
          marginBottom: '1rem', background: 'var(--danger-light)', border: '1px solid var(--danger)',
          color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
        }}>
          {error}
        </div>
      )}

      {/* Table */}
      <div style={{
        background: 'var(--bg-card)', border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)', overflow: 'hidden',
      }}>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr>
                <th style={TH}>Paciente</th>
                <th style={TH}>Identificación</th>
                <th style={TH}>Clínica</th>
                <th style={TH}>Médico</th>
                <th style={TH}>Ítems</th>
                <th style={TH}>Estado</th>
                <th style={TH}>Fecha</th>
                <th style={TH}>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={8} style={{ ...TD, textAlign: 'center', color: 'var(--fg-mute)', padding: '2rem' }}>
                    Cargando...
                  </td>
                </tr>
              )}
              {!loading && data.length === 0 && (
                <tr>
                  <td colSpan={8} style={{ ...TD, textAlign: 'center', color: 'var(--fg-mute)', padding: '2rem' }}>
                    Sin recetas
                  </td>
                </tr>
              )}
              {!loading && data.map(rx => (
                <tr key={rx.id} style={{ cursor: 'pointer' }} onClick={() => onSelect(rx.id)}>
                  <td style={{ ...TD, color: 'var(--fg-1)', fontWeight: 600 }}>
                    {rx.patient.nombres} {rx.patient.apellidos}
                  </td>
                  <td style={TD}>{rx.patient.identificacion}</td>
                  <td style={TD}>{rx.clinica ?? '—'}</td>
                  <td style={TD}>{rx.medico ?? '—'}</td>
                  <td style={TD}>{rx.items.length}</td>
                  <td style={TD}><StatusBadge estado={rx.estado} /></td>
                  <td style={{ ...TD, whiteSpace: 'nowrap' }}>
                    {rx.fecha_prescripcion.slice(0, 10)}
                  </td>
                  <td style={TD}>
                    <button
                      className="btn btn-sm"
                      onClick={e => { e.stopPropagation(); onSelect(rx.id); }}
                      style={{
                        background: 'var(--bg-surface)', border: '1px solid var(--border-1)',
                        color: 'var(--fg-2)', fontSize: '.75rem', padding: '.25rem .6rem',
                        borderRadius: 'var(--radius)', cursor: 'pointer',
                      }}
                    >
                      Ver
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

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
            style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-1)', cursor: 'pointer' }}
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
            style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-1)', cursor: 'pointer' }}
          >
            Siguiente →
          </button>
        </div>
      )}
    </div>
  );
}
