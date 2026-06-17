import React, { useState } from 'react';
import type { SedeOption } from './types';

interface ToolbarProps {
  startDate: string;
  endDate: string;
  sede: string;
  sedeOptions: SedeOption[];
}

export function Toolbar({ startDate, endDate, sede, sedeOptions }: ToolbarProps) {
  const [loading, setLoading] = useState(false);
  const [start, setStart] = useState(startDate);
  const [end, setEnd] = useState(endDate);

  const navigate = (params: Record<string, string>) => {
    setLoading(true);
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    window.location.href = url.toString();
  };

  const applyRange = () => {
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
            <img src="/images/logo-light-text.png" alt="MedForge" />
            <span className="rep-tb-div"></span>
            <span className="rep-tb-tag">
              Reporte ejecutivo<small>Imágenes</small>
            </span>
          </div>
          <div className="rep-filters">
            <span className="rep-flabel">Período</span>
            <div className="rep-seg rep-seg--solid" style={{ gap: 6, padding: '4px 8px' }}>
              <input
                type="date"
                value={start}
                max={end}
                onChange={e => setStart(e.target.value)}
                disabled={loading}
                className="rep-date-input"
              />
              <span>→</span>
              <input
                type="date"
                value={end}
                min={start}
                onChange={e => setEnd(e.target.value)}
                disabled={loading}
                className="rep-date-input"
              />
              <button onClick={applyRange} disabled={loading}>Aplicar</button>
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
