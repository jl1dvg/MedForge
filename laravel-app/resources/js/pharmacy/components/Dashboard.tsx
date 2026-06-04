import React from 'react';
import { useDashboard } from '../hooks/useDashboard';

const CARD_CONFIGS = [
  { key: 'recetas_pendientes',   label: 'Recetas pendientes',       accent: 'var(--primary)' },
  { key: 'procesadas_este_mes',  label: 'Procesadas este mes',       accent: 'var(--success)' },
  { key: 'stock_bajo',           label: 'Stock bajo',                accent: 'var(--danger)' },
  { key: 'entregas_activas',     label: 'Entregas activas',          accent: 'var(--warning)' },
  { key: 'recordatorios_proximos', label: 'Recordatorios (7 días)', accent: 'var(--info)' },
] as const;

type MetricKey = typeof CARD_CONFIGS[number]['key'];

export function Dashboard() {
  const { data, loading, error } = useDashboard();

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--fg-mute)', fontSize: '.8125rem' }}>
        Cargando...
      </div>
    );
  }

  if (error) {
    return (
      <div style={{
        margin: '1rem', background: 'var(--danger-light)', border: '1px solid var(--danger)',
        color: 'var(--danger)', padding: '.75rem 1rem', borderRadius: 'var(--radius)', fontSize: '.8125rem',
      }}>
        {error}
      </div>
    );
  }

  return (
    <div style={{ padding: '1rem' }}>
      {/* Metric cards */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))',
        gap: '1rem',
        marginBottom: '1.5rem',
      }}>
        {CARD_CONFIGS.map(({ key, label, accent }) => (
          <div key={key} style={{
            background: 'var(--bg-card)',
            border: '1px solid var(--border-1)',
            borderRadius: 'var(--radius)',
            padding: '1rem',
            borderTop: `3px solid ${accent}`,
          }}>
            <div style={{ fontSize: '1.75rem', fontWeight: 700, color: 'var(--fg-1)', lineHeight: 1 }}>
              {data ? String(data[key as MetricKey]) : '—'}
            </div>
            <div style={{ fontSize: '.75rem', color: 'var(--fg-mute)', marginTop: '.375rem' }}>
              {label}
            </div>
          </div>
        ))}
      </div>

      {/* Top medications */}
      <div style={{
        background: 'var(--bg-card)',
        border: '1px solid var(--border-1)',
        borderRadius: 'var(--radius)',
        overflow: 'hidden',
        maxWidth: 480,
      }}>
        <div style={{
          padding: '.75rem 1rem',
          borderBottom: '1px solid var(--border-1)',
          fontSize: '.8125rem',
          fontWeight: 700,
          color: 'var(--fg-1)',
        }}>
          Top 5 medicamentos más solicitados
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '.8125rem' }}>
          <thead>
            <tr style={{ background: 'var(--bg-surface)' }}>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>#</th>
              <th style={{ padding: '.5rem 1rem', textAlign: 'left', color: 'var(--fg-mute)', fontWeight: 600 }}>Medicamento</th>
              <th style={{ padding: '.5rem 1rem', textAlign: 'right', color: 'var(--fg-mute)', fontWeight: 600 }}>Solicitudes</th>
            </tr>
          </thead>
          <tbody>
            {(!data || data.top_medicamentos.length === 0) && (
              <tr>
                <td colSpan={3} style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--fg-mute)' }}>
                  Sin datos
                </td>
              </tr>
            )}
            {data && data.top_medicamentos.map((med, idx) => (
              <tr key={idx} style={{ borderTop: '1px solid var(--border-1)' }}>
                <td style={{ padding: '.5rem 1rem', color: 'var(--fg-mute)' }}>{idx + 1}</td>
                <td style={{ padding: '.5rem 1rem', color: 'var(--fg-1)' }}>{med.nombre}</td>
                <td style={{ padding: '.5rem 1rem', textAlign: 'right' }}>
                  <span style={{
                    background: 'var(--accent)', color: '#fff',
                    fontSize: '.6875rem', fontWeight: 700,
                    padding: '.15rem .5rem', borderRadius: 'var(--radius-pill)',
                  }}>
                    {med.count}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
