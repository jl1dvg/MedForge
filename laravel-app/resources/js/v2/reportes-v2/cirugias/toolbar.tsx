import React from 'react';
import type { SedeOption } from './types';

const PERIODS: Record<string, { label: string; days: number }> = {
  mes:  { label: 'Mes',  days: 30  },
  trim: { label: 'Trim', days: 90  },
  sem:  { label: 'Sem',  days: 180 },
  año:  { label: 'Año',  days: 365 },
};

function datesForPreset(preset: string): { start: string; end: string } {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - (PERIODS[preset]?.days ?? 30));
  return {
    end: end.toISOString().slice(0, 10),
    start: start.toISOString().slice(0, 10),
  };
}

function activePeriodKey(startDate: string, endDate: string): string {
  const days = Math.round((new Date(endDate).getTime() - new Date(startDate).getTime()) / 86400000);
  if (days <= 32)  return 'mes';
  if (days <= 92)  return 'trim';
  if (days <= 185) return 'sem';
  return 'año';
}

interface ToolbarProps {
  startDate: string;
  endDate: string;
  sede: string;
  sedeOptions: SedeOption[];
}

export function Toolbar({ startDate, endDate, sede, sedeOptions }: ToolbarProps) {
  const activePeriod = activePeriodKey(startDate, endDate);

  const navigate = (params: Record<string, string>) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    window.location.href = url.toString();
  };

  const selectPeriod = (key: string) => {
    const { start, end } = datesForPreset(key);
    navigate({ start_date: start, end_date: end });
  };

  return (
    <div className="rep-toolbar">
      <div className="rep-toolbar-inner">
        <div className="rep-tb-brand">
          <span className="rep-tb-tag">
            Reporte ejecutivo<small>Cirugías</small>
          </span>
        </div>
        <div className="rep-filters">
          <span className="rep-flabel">Período</span>
          <div className="rep-seg">
            {Object.entries(PERIODS).map(([key, p]) => (
              <button
                key={key}
                className={activePeriod === key ? 'is-active' : ''}
                onClick={() => selectPeriod(key)}
              >
                {p.label}
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
                  >
                    {o.label}
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
        <button className="rep-btn rep-btn--primary" onClick={() => window.print()}>
          <i className="mdi mdi-file-pdf-box"></i>Exportar PDF
        </button>
      </div>
    </div>
  );
}
