import React, { useState } from 'react';
import type { SedeOption } from './types';

const PERIOD_LABELS: Record<string, string> = {
  mes: 'Mes', trim: 'Trim', sem: 'Sem', año: 'Año',
};

function fmt(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function datesForPreset(preset: string): { start: string; end: string } {
  const today = new Date();
  const y = today.getFullYear();
  const m = today.getMonth(); // 0-indexed

  switch (preset) {
    case 'mes':
      return { start: fmt(new Date(y, m, 1)), end: fmt(today) };
    case 'trim': {
      const quarterStart = Math.floor(m / 3) * 3;
      return { start: fmt(new Date(y, quarterStart, 1)), end: fmt(today) };
    }
    case 'sem': {
      const semStart = m < 6 ? 0 : 6;
      return { start: fmt(new Date(y, semStart, 1)), end: fmt(today) };
    }
    case 'año':
      return { start: fmt(new Date(y, 0, 1)), end: fmt(today) };
    default:
      return { start: fmt(new Date(y, m, 1)), end: fmt(today) };
  }
}

function activePeriodKey(startDate: string, endDate: string): string | null {
  const today = new Date();
  const todayStr = fmt(today);
  if (endDate !== todayStr) return null;

  const y = today.getFullYear();
  const m = today.getMonth();

  if (startDate === fmt(new Date(y, m, 1))) return 'mes';
  const quarterStart = Math.floor(m / 3) * 3;
  if (startDate === fmt(new Date(y, quarterStart, 1))) return 'trim';
  const semStart = m < 6 ? 0 : 6;
  if (startDate === fmt(new Date(y, semStart, 1))) return 'sem';
  if (startDate === fmt(new Date(y, 0, 1))) return 'año';
  return null;
}

interface ToolbarProps {
  startDate: string;
  endDate: string;
  sede: string;
  sedeOptions: SedeOption[];
}

export function Toolbar({ startDate, endDate, sede, sedeOptions }: ToolbarProps) {
  const activePeriod = activePeriodKey(startDate, endDate);
  const [loading, setLoading] = useState(false);

  const navigate = (params: Record<string, string>) => {
    setLoading(true);
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    window.location.href = url.toString();
  };

  const selectPeriod = (key: string) => {
    const { start, end } = datesForPreset(key);
    navigate({ start_date: start, end_date: end });
  };

  return (
    <>
      {loading && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 99999, background: 'rgba(6,11,40,.45)', display: 'grid', placeItems: 'center' }}>
          <div style={{ background: '#fff', borderRadius: 12, padding: '24px 32px', display: 'flex', alignItems: 'center', gap: 14, font: '500 14px "IBM Plex Sans",sans-serif', color: '#172b4c', boxShadow: '0 8px 32px rgba(16,24,40,.18)' }}>
            <span style={{ width: 20, height: 20, border: '2px solid #e4e6ef', borderTopColor: '#0e9bb3', borderRadius: '50%', display: 'inline-block', animation: 'spin 0.7s linear infinite' }}></span>
            Cargando reporte…
          </div>
        </div>
      )}
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
      <div className="rep-toolbar">
        <div className="rep-toolbar-inner">
          <div className="rep-tb-brand">
            <span className="rep-tb-tag">
              Reporte ejecutivo<small>Imágenes</small>
            </span>
          </div>
          <div className="rep-filters">
            <span className="rep-flabel">Período</span>
            <div className="rep-seg">
              {Object.entries(PERIOD_LABELS).map(([key, label]) => (
                <button
                  key={key}
                  className={activePeriod === key ? 'is-active' : ''}
                  onClick={() => selectPeriod(key)}
                  disabled={loading}
                >
                  {label}
                </button>
              ))}
            </div>
            {sedeOptions.length > 1 && (
              <>
                <span className="rep-flabel">Sede</span>
                <div className="rep-seg rep-seg--solid">
                  {sedeOptions.map(o => (
                    <button
                      key={o.value}
                      className={sede === o.value ? 'is-active' : ''}
                      onClick={() => navigate({ sede: o.value })}
                      disabled={loading}
                    >
                      {o.label}
                    </button>
                  ))}
                </div>
              </>
            )}
          </div>
          <a
            className="rep-btn"
            href={`/v2/imagenes/dashboard/export/excel?start_date=${startDate}&end_date=${endDate}&sede=${sede}`}
            style={{ textDecoration: 'none' }}
          >
            <i className="mdi mdi-microsoft-excel"></i>Excel
          </a>
          <button className="rep-btn rep-btn--primary" onClick={() => window.print()} disabled={loading}>
            <i className="mdi mdi-file-pdf-box"></i>PDF
          </button>
        </div>
      </div>
    </>
  );
}
