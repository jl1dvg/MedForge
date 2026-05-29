import React from 'react';
import type { CrmOpportunity, Stage, Source } from '../types';

const STAGE_BADGE: Record<Stage, string> = {
  nuevo:          'bg-sky-100 text-sky-700',
  contactado:     'bg-yellow-100 text-yellow-700',
  en_evaluacion:  'bg-violet-100 text-violet-700',
  propuesta:      'bg-pink-100 text-pink-700',
  comprometido:   'bg-teal-100 text-teal-700',
  ganado:         'bg-green-100 text-green-700',
  perdido:        'bg-red-100 text-red-700',
};
const STAGE_LABEL: Record<Stage, string> = {
  nuevo: 'Nuevo', contactado: 'Contactado', en_evaluacion: 'En evaluación',
  propuesta: 'Propuesta', comprometido: 'Comprometido', ganado: 'Ganado', perdido: 'Perdido',
};
const SOURCE_LABEL: Record<Source, string> = {
  whatsapp: 'WhatsApp', solicitud: 'Solicitud', examen: 'Examen', manual: 'Manual',
};
const ACTION_LABEL: Partial<Record<Stage, string>> = {
  nuevo: 'Contactar', contactado: 'Avanzar',
  en_evaluacion: 'Avanzar', propuesta: 'Seguimiento',
};

function timeAgo(dateStr: string): { label: string; urgent: boolean } {
  const diffH = (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
  if (diffH < 1) return { label: 'hace < 1h', urgent: false };
  if (diffH < 6) return { label: `hace ${Math.floor(diffH)}h`, urgent: false };
  if (diffH < 24) return { label: `${Math.floor(diffH)}h sin resp.`, urgent: true };
  return { label: `${Math.floor(diffH / 24)}d sin resp.`, urgent: true };
}

interface Props {
  opp: CrmOpportunity;
  onClick: (opp: CrmOpportunity) => void;
}

export function OpportunityRow({ opp, onClick }: Props) {
  const time = timeAgo(opp.updated_at);
  const isUrgent = time.urgent && !['ganado', 'perdido'].includes(opp.stage);

  return (
    <tr
      onClick={() => onClick(opp)}
      className={`border-b border-slate-100 cursor-pointer transition-colors
        ${isUrgent ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-slate-50'}`}
    >
      <td className="px-4 py-3">
        <div className="font-bold text-slate-900 text-sm">{opp.contact?.name ?? '—'}</div>
        <div className="text-xs text-slate-400 mt-0.5">
          {opp.contact?.cedula ? opp.contact.cedula : opp.contact?.phone ?? '—'}
        </div>
      </td>
      <td className="px-4 py-3">
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${STAGE_BADGE[opp.stage]}`}>
          {STAGE_LABEL[opp.stage]}
        </span>
      </td>
      <td className="px-4 py-3 text-xs text-slate-500">{SOURCE_LABEL[opp.source]}</td>
      <td className="px-4 py-3 text-xs text-slate-500">—</td>
      <td className={`px-4 py-3 text-xs font-semibold ${time.urgent ? 'text-red-600' : 'text-slate-400'}`}>
        {time.label}
      </td>
      <td className="px-4 py-3">
        {ACTION_LABEL[opp.stage] && (
          <button className="bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-blue-600">
            {ACTION_LABEL[opp.stage]}
          </button>
        )}
      </td>
    </tr>
  );
}
