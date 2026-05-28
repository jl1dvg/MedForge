import React from 'react';
import type { PanelStats } from '../types';

interface Props { stats: PanelStats | null }

const cards = [
  { key: 'urgent',          label: 'Sin contactar',    colorClass: 'text-red-600',    bg: 'border-red-200 bg-red-50' },
  { key: 'active',          label: 'Activas total',    colorClass: 'text-blue-600',   bg: '' },
  { key: 'won_this_month',  label: 'Ganadas mes',      colorClass: 'text-green-600',  bg: '' },
  { key: 'avg_response_h',  label: 'Resp. prom. (h)',  colorClass: 'text-amber-600',  bg: '' },
  { key: 'conversion_rate', label: 'Conversion %',     colorClass: 'text-violet-600', bg: '' },
] as const;

export function StatsBar({ stats }: Props) {
  return (
    <div className="grid grid-cols-5 gap-3 mb-5">
      {cards.map(({ key, label, colorClass, bg }) => (
        <div key={key} className={`bg-white rounded-xl border p-4 ${bg}`}>
          <div className={`text-3xl font-extrabold leading-none mb-1 ${colorClass}`}>
            {stats ? String((stats as Record<string, number>)[key]) : '—'}
            {key === 'conversion_rate' && stats ? '%' : ''}
          </div>
          <div className="text-xs text-slate-500">{label}</div>
        </div>
      ))}
    </div>
  );
}
