import React from 'react';
import type { CrmActivity, ActivityType } from '../types';

const DOT_COLOR: Record<ActivityType, string> = {
  nota: 'bg-amber-400', llamada: 'bg-blue-400',
  cambio_etapa: 'bg-violet-500', email: 'bg-pink-400',
};

function formatDate(d: string): string {
  const diff = (Date.now() - new Date(d).getTime()) / 60_000;
  if (diff < 60) return `Hace ${Math.floor(diff)} min`;
  if (diff < 1440) return `Hace ${Math.floor(diff / 60)}h`;
  return `Hace ${Math.floor(diff / 1440)}d`;
}

interface Props { activities: CrmActivity[] }

export function ActivityTimeline({ activities }: Props) {
  if (activities.length === 0) {
    return <p className="text-sm text-slate-400 text-center py-4">Sin actividades registradas</p>;
  }
  return (
    <div className="relative pl-5">
      <div className="absolute left-2 top-0 bottom-0 w-0.5 bg-slate-200" />
      <div className="flex flex-col gap-3">
        {activities.map(a => (
          <div key={a.id} className="relative">
            <div className={`absolute -left-3 top-1 w-2.5 h-2.5 rounded-full ${DOT_COLOR[a.type]}`} />
            <div className="bg-white border border-slate-200 rounded-lg px-3 py-2.5 shadow-sm">
              <p className="text-xs text-slate-700 leading-relaxed">{a.description}</p>
              <p className="text-xs text-slate-400 mt-1">
                {formatDate(a.created_at)} · {a.user_id ? `Usuario #${a.user_id}` : 'Sistema'}
              </p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
