import React from 'react';
import type { PanelStats } from '../types';

interface Props { stats: PanelStats | null }

const CARDS: Array<{ key: keyof PanelStats; label: string; urgent: boolean; suffix?: string }> = [
  { key: 'urgent',          label: 'Sin contactar',  urgent: true  },
  { key: 'active',          label: 'Activas',         urgent: false },
  { key: 'won_this_month',  label: 'Ganadas mes',     urgent: false },
  { key: 'avg_response_h',  label: 'Respuesta prom.', urgent: false, suffix: 'h' },
  { key: 'conversion_rate', label: 'Conversión',      urgent: false, suffix: '%' },
];

export function StatsBar({ stats }: Props) {
  return (
    <div className="crm-kpi-grid">
      {CARDS.map(({ key, label, urgent, suffix }) => (
        <div key={key} className={`crm-kpi-card${urgent ? ' urgent' : ''}`}>
          <div className="crm-kpi-value">
            {stats != null ? String(stats[key]) : '—'}{stats != null && suffix ? suffix : ''}
          </div>
          <div className="crm-kpi-label">{label}</div>
        </div>
      ))}
    </div>
  );
}
