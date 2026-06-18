import React, { useState } from 'react';

interface ToolbarProps {
  period: string;
}

function isoDaysAgo(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() - days);
  return d.toISOString().slice(0, 10);
}

const PRESETS: { key: string; label: string; days: number }[] = [
  { key: 'hoy', label: 'Hoy', days: 0 },
  { key: '7d', label: '7 días', days: 6 },
  { key: '30d', label: '30 días', days: 29 },
  { key: '90d', label: '90 días', days: 89 },
];

export function Toolbar({ period }: ToolbarProps) {
  const [loading, setLoading] = useState(false);
  const active = PRESETS.find(p => p.key === period) ?? PRESETS[2];
  const today = new Date().toISOString().slice(0, 10);
  const dateFrom = isoDaysAgo(active.days);

  const applyPreset = (key: string) => {
    setLoading(true);
    const url = new URL(window.location.href);
    url.searchParams.set('period', key);
    window.location.href = url.toString();
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
              Reporte ejecutivo<small>WhatsApp</small>
            </span>
          </div>
          <div className="rep-filters">
            <span className="rep-flabel">Período</span>
            <div className="rep-seg rep-seg--solid">
              {PRESETS.map(p => (
                <button
                  key={p.key}
                  onClick={() => applyPreset(p.key)}
                  disabled={loading}
                  aria-pressed={p.key === active.key}
                  className={p.key === active.key ? 'is-active' : undefined}
                >
                  {p.label}
                </button>
              ))}
            </div>
          </div>
          <a
            className="rep-btn"
            href={`/whatsapp/api/kpis/export?date_from=${dateFrom}&date_to=${today}`}
            style={{ textDecoration: 'none' }}
          >
            <i className="mdi mdi-microsoft-excel"></i>Excel
          </a>
          <a
            className="rep-btn rep-btn--primary"
            href={`/whatsapp/api/kpis/export/pdf?date_from=${dateFrom}&date_to=${today}`}
            style={{ textDecoration: 'none' }}
          >
            <i className="mdi mdi-file-pdf-box"></i>PDF
          </a>
        </div>
      </div>
    </>
  );
}
